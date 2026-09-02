<?php

namespace Tests\Feature\Public;

use App\Livewire\Admin\Peliputan\Form as AdminForm;
use App\Livewire\Public\Peliputan\Create;
use App\Livewire\Public\Peliputan\Status;
use App\Models\Coverage;
use App\Models\Role;
use App\Models\User;
use App\Support\CoverageReceiptAuthorization;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeliputanSecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('peliputan-submit:127.0.0.1');
        RateLimiter::clear('peliputan-status:127.0.0.1');
    }

    public function test_schema_model_and_public_admin_forms_have_no_email_field(): void
    {
        $this->assertFalse(Schema::hasColumn('tr_perliputan', 'email'));
        $this->assertNotContains('email', (new Coverage)->getFillable());

        $this->get(route('peliputan.create'))
            ->assertOk()
            ->assertDontSee('wire:model="email"', false)
            ->assertDontSee('type="email"', false);

        $coverage = $this->coverage(['email' => 'crafted@example.test']);
        $this->assertArrayNotHasKey('email', $coverage->getAttributes());

        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'Super Admin')->firstOrFail()->id,
            'status' => true,
        ]);
        $this->actingAs($admin)->get(route('admin.peliputan.edit', $coverage))
            ->assertOk()
            ->assertDontSee('wire:model="email"', false)
            ->assertDontSee('Alamat Email');
    }

    public function test_phone_only_submission_normalizes_phone_and_grants_receipt(): void
    {
        $component = Livewire::test(Create::class)
            ->set('name', 'Pemohon Tanpa Email')
            ->set('phone', '0812 3456-7890')
            ->set('event_name', 'Agenda Phone Only')
            ->set('event_date', '2026-10-20')
            ->set('event_time', '13:30')
            ->set('location', 'Aula Kampus')
            ->set('description', 'Permohonan sintetis tanpa email.')
            ->call('submit')
            ->assertHasNoErrors();

        $coverage = Coverage::where('event_name', 'Agenda Phone Only')->firstOrFail();
        $this->assertSame('6281234567890', $coverage->phone);
        $this->assertMatchesRegularExpression('/^PLP-\d{8}-[A-Z0-9]{4}$/', $coverage->submission_code);
        $component->assertRedirect(route('peliputan.success', $coverage->submission_code));
        $this->assertTrue(app(CoverageReceiptAuthorization::class)->allows(app('session.store'), $coverage->submission_code));
    }

    public function test_status_requires_code_and_matching_full_normalized_phone_with_generic_failures(): void
    {
        $coverage = $this->coverage(['phone' => '62812 4444-5555']);

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('phone', '0812-4444-5555')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee($coverage->event_name);

        $wrongPhone = Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('phone', '081299999999')
            ->call('check')
            ->assertHasErrors(['lookup'])
            ->assertDontSee($coverage->event_name);

        $wrongCode = Livewire::test(Status::class)
            ->set('submission_code', 'PLP-UNKNOWN-0000')
            ->set('phone', '0812-4444-5555')
            ->call('check')
            ->assertHasErrors(['lookup'])
            ->assertDontSee($coverage->event_name);

        $this->assertSame($wrongPhone->errors()->first('lookup'), $wrongCode->errors()->first('lookup'));

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('phone', 'person@example.test')
            ->call('check')
            ->assertHasErrors(['phone'])
            ->assertDontSee($coverage->event_name);
    }

    public function test_receipt_is_bound_to_the_authorized_http_session_cookie(): void
    {
        config()->set('wpitv.whatsapp.admin_number', '081200001111');
        $coverage = $this->coverage([
            'name' => 'Receipt Owner',
            'event_name' => 'Private Receipt Event',
            'location' => 'Private Receipt Location',
            'admin_note' => 'Private Internal Note',
        ]);

        $sessionA = $this->newBrowserSession();
        app(CoverageReceiptAuthorization::class)->grant(app('session.store'), $coverage->submission_code);
        app('session.store')->save();

        $this->useBrowserSession($sessionA)->get(route('peliputan.success', $coverage->submission_code))
            ->assertOk()
            ->assertSee($coverage->submission_code)
            ->assertSee('Private Receipt Event')
            ->assertDontSee('Private Receipt Location')
            ->assertDontSee('Private Internal Note')
            ->assertDontSee($coverage->phone);

        $sessionB = $this->newBrowserSession();
        $this->useBrowserSession($sessionB)->get(route('peliputan.success', $coverage->submission_code))
            ->assertNotFound()
            ->assertDontSee('Private Receipt Event');
    }

    public function test_valid_and_invalid_receipt_codes_without_proof_have_same_safe_response(): void
    {
        $coverage = $this->coverage(['event_name' => 'Enumeration Private Event']);
        $session = $this->newBrowserSession();

        $valid = $this->useBrowserSession($session)->get(route('peliputan.success', $coverage->submission_code));
        $invalid = $this->useBrowserSession($session)->get(route('peliputan.success', 'PLP-NOT-FOUND-0000'));

        $valid->assertNotFound()->assertDontSee('Enumeration Private Event');
        $invalid->assertNotFound()->assertDontSee('Enumeration Private Event');
        $this->assertSame($valid->getStatusCode(), $invalid->getStatusCode());
    }

    public function test_receipt_expires_but_phone_status_lookup_remains_available(): void
    {
        $coverage = $this->coverage(['event_name' => 'Expiring Receipt Event', 'phone' => '628133334444']);
        $session = $this->newBrowserSession();
        app(CoverageReceiptAuthorization::class)->grant(app('session.store'), $coverage->submission_code);
        app('session.store')->save();

        $this->useBrowserSession($session)->get(route('peliputan.success', $coverage->submission_code))->assertOk();

        $this->travel(CoverageReceiptAuthorization::LIFETIME_MINUTES + 1)->minutes();
        $this->useBrowserSession($session)->get(route('peliputan.success', $coverage->submission_code))
            ->assertNotFound()
            ->assertDontSee('Expiring Receipt Event');

        Livewire::test(Status::class)
            ->set('submission_code', $coverage->submission_code)
            ->set('phone', '08133334444')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSee('Expiring Receipt Event');
    }

    public function test_receipt_session_map_supports_multiple_submissions_and_is_bounded(): void
    {
        $service = app(CoverageReceiptAuthorization::class);
        $session = app('session.store');
        $codes = [];

        for ($index = 1; $index <= CoverageReceiptAuthorization::MAX_RECEIPTS + 2; $index++) {
            $codes[] = 'PLP-BOUNDED-'.$index;
            $service->grant($session, end($codes));
        }

        $stored = $session->get(CoverageReceiptAuthorization::SESSION_KEY);
        $this->assertCount(CoverageReceiptAuthorization::MAX_RECEIPTS, $stored);
        $this->assertFalse($service->allows($session, $codes[0]));
        $this->assertFalse($service->allows($session, $codes[1]));
        $this->assertTrue($service->allows($session, end($codes)));
    }

    public function test_invalid_submission_attempts_consume_the_request_budget(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            Livewire::test(Create::class)
                ->set('event_date', '')
                ->call('submit')
                ->assertHasErrors(['name']);
        }

        Livewire::test(Create::class)
            ->call('submit')
            ->assertHasErrors(['general'])
            ->assertHasNoErrors(['name']);
    }

    public function test_invalid_status_attempts_consume_the_request_budget(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            Livewire::test(Status::class)
                ->set('submission_code', '')
                ->set('phone', '')
                ->call('check')
                ->assertHasErrors(['submission_code', 'phone']);
        }

        Livewire::test(Status::class)
            ->call('check')
            ->assertHasErrors(['lookup'])
            ->assertHasNoErrors(['submission_code', 'phone']);
    }

    #[DataProvider('eventTimes')]
    public function test_public_event_time_format_is_validated(string $time, bool $valid): void
    {
        $component = Livewire::test(Create::class)
            ->set('name', 'Time Validation')
            ->set('phone', '081234567890')
            ->set('event_name', 'Time Event '.$time)
            ->set('event_date', '2026-10-20')
            ->set('event_time', $time)
            ->set('location', 'Aula')
            ->set('description', 'Time validation request.')
            ->call('submit');

        if ($valid) {
            $component->assertHasNoErrors(['event_time']);
        } else {
            $component->assertHasErrors(['event_time']);
        }
    }

    #[DataProvider('eventTimes')]
    public function test_admin_event_time_format_is_validated(string $time, bool $valid): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'Super Admin')->firstOrFail()->id,
            'status' => true,
        ]);
        $component = Livewire::actingAs($admin)
            ->test(AdminForm::class)
            ->set('name', 'Admin Time Validation')
            ->set('phone', '081234567890')
            ->set('event_name', 'Admin Time '.$time)
            ->set('event_date', '2026-10-20')
            ->set('event_time', $time)
            ->set('location', 'Aula')
            ->set('description', 'Admin time validation.')
            ->call('save');

        if ($valid) {
            $component->assertHasNoErrors(['event_time']);
        } else {
            $component->assertHasErrors(['event_time']);
        }
    }

    public static function eventTimes(): array
    {
        return [
            'morning' => ['08:00', true],
            'afternoon' => ['13:30', true],
            'hour overflow' => ['25:00', false],
            'minute overflow' => ['12:99', false],
            'text' => ['abc', false],
        ];
    }

    private function coverage(array $overrides = []): Coverage
    {
        return Coverage::create(array_merge([
            'submission_code' => 'PLP-SEC-'.Str::upper(Str::random(8)),
            'name' => 'Synthetic Applicant',
            'phone' => '6281244445555',
            'event_name' => 'Synthetic Coverage Event',
            'event_date' => '2026-10-20',
            'event_time' => '08:00',
            'location' => 'Synthetic Location',
            'description' => 'Synthetic coverage description.',
            'status' => Coverage::STATUS_PENDING,
            'admin_note' => null,
        ], $overrides));
    }

    private function newBrowserSession(): string
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        app('session.store')->save();
        app('session.store')->setId(Str::random(40));
        app('session.store')->start();
        app('session.store')->flush();

        return app('session.store')->getId();
    }

    private function useBrowserSession(string $sessionId): static
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        return $this->withCookie(config('session.cookie'), $sessionId);
    }
}
