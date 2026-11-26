<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Social\Catalog;
use App\Models\Role;
use App\Models\User;
use App\Services\PublicSocialAccounts;
use App\Services\SocialReport;
use App\Services\WebsiteSettings;
use App\Support\CoverageWhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WebsiteSettingsAndSocialIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $productionAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $superRole = Role::firstOrCreate(['name' => User::ROLE_SUPER_ADMIN], ['description' => 'Super Admin']);
        $prodRole = Role::firstOrCreate(['name' => User::ROLE_PRODUCTION], ['description' => 'Admin Production']);

        $this->superAdmin = User::factory()->create(['role_id' => $superRole->id, 'status' => true]);
        $this->productionAdmin = User::factory()->create(['role_id' => $prodRole->id, 'status' => true]);
    }

    public function test_only_authorized_super_admin_can_access_and_save_website_settings(): void
    {
        $this->actingAs($this->productionAdmin)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('admin.settings.index'))->assertOk()->assertSee('Pengaturan Website');

        Livewire::actingAs($this->productionAdmin)
            ->test(SettingsIndex::class)
            ->assertForbidden();

        if (! Schema::hasTable('wpitv_settings')) {
            $this->markTestSkipped('wpitv_settings table not present in test schema.');
        }

        Livewire::actingAs($this->superAdmin)
            ->test(SettingsIndex::class)
            ->set('whatsappNumber', 'nomor-salah')
            ->call('save')
            ->assertHasErrors(['whatsappNumber']);

        Livewire::actingAs($this->superAdmin)
            ->test(SettingsIndex::class)
            ->set('whatsappNumber', '+62 812-9999-8888')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('6281299998888', app(WebsiteSettings::class)->coverageWhatsAppNumber());
        $this->assertSame('6281299998888', app(CoverageWhatsApp::class)->adminNumber());
        $this->assertDatabaseHas('wpitv_setting_events', [
            'setting_key' => WebsiteSettings::KEY_COVERAGE_WHATSAPP,
            'after_value' => '6281299998888',
            'actor_id' => $this->superAdmin->id,
        ]);
    }

    public function test_social_visibility_toggle_filters_public_channels_without_blocking_reports(): void
    {
        if (! app(PublicSocialAccounts::class)->visibilityColumnsReady()) {
            $this->markTestSkipped('tvclub_social_accounts visibility columns not present in test schema.');
        }

        config()->set('social-media.managers', [(string) $this->superAdmin->id]);
        $now = now('UTC');

        $visibleId = DB::table('tvclub_social_accounts')->insertGetId([
            'platform' => 'instagram',
            'account_key' => (string) Str::uuid(),
            'handle' => '@wpitv_pub_'.Str::random(5),
            'display_name' => 'WPiTV Official',
            'profile_url' => 'https://instagram.com/wpitvofficial',
            'show_on_website' => 0,
            'display_order' => 1,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $hiddenId = DB::table('tvclub_social_accounts')->insertGetId([
            'platform' => 'tiktok',
            'account_key' => (string) Str::uuid(),
            'handle' => '@wpitv_hid_'.Str::random(5),
            'display_name' => 'WPiTV Hidden',
            'profile_url' => 'https://tiktok.com/@wpitvhidden',
            'show_on_website' => 0,
            'display_order' => 2,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Catalog::class, ['section' => 'accounts'])
            ->call('togglePublicVisibility', $visibleId, 1)
            ->assertHasNoErrors();

        $public = app(PublicSocialAccounts::class)->all();
        $this->assertTrue($public->contains('id', $visibleId));
        $this->assertFalse($public->contains('id', $hiddenId));

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('https://instagram.com/wpitvofficial', false)
            ->assertSee('data-floating-instagram', false)
            ->assertSee('data-back-to-top', false)
            ->assertDontSee('https://tiktok.com/@wpitvhidden', false);

        $postId = DB::table('tvclub_social_posts')->insertGetId([
            'account_id' => $hiddenId,
            'title' => 'Hidden Account Report Post',
            'content_type' => 'short_video',
            'url' => null,
            'url_hash' => null,
            'description' => 'Testing',
            'responsible_label' => 'Tim Media',
            'published_at' => $now,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pubs = SocialReport::publications($now->setTimezone('Asia/Jakarta')->format('Y-m'));
        $this->assertTrue($pubs->contains('id', $postId));
    }

    public function test_official_contact_information_validation_persistence_and_public_integration(): void
    {
        if (! Schema::hasTable('wpitv_settings')) {
            $this->markTestSkipped('wpitv_settings table not present in test schema.');
        }

        Livewire::actingAs($this->productionAdmin)
            ->test(SettingsIndex::class)
            ->assertForbidden();

        Livewire::actingAs($this->superAdmin)
            ->test(SettingsIndex::class)
            ->set('contactEmail', 'bukan-email')
            ->set('contactPhone', 'abc')
            ->set('contactAddress', str_repeat('a', 260))
            ->call('saveContact')
            ->assertHasErrors(['contactEmail', 'contactPhone', 'contactAddress']);

        Livewire::actingAs($this->superAdmin)
            ->test(SettingsIndex::class)
            ->set('contactEmail', '  REDAKSI@WPITV.AC.ID ')
            ->set('contactPhone', '  (0285)   427999 ')
            ->set('contactAddress', "Jl. Kampus No. 1\r\nPekalongan")
            ->call('saveContact')
            ->assertHasNoErrors();

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('mailto:redaksi@wpitv.ac.id', false)
            ->assertSee('tel:0285427999', false)
            ->assertSee('Jl. Kampus No. 1');

        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('mailto:redaksi@wpitv.ac.id', false)
            ->assertSee('tel:0285427999', false);

        Livewire::actingAs($this->superAdmin)
            ->test(SettingsIndex::class)
            ->set('contactEmail', '')
            ->set('contactPhone', '')
            ->set('contactAddress', '')
            ->call('saveContact')
            ->assertHasNoErrors();

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('mailto:redaksi@wpitv.ac.id', false)
            ->assertDontSee('tel:0285427999', false);
    }
}
