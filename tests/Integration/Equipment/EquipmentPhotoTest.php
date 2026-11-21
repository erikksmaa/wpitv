<?php

namespace Tests\Integration\Equipment;

use App\Livewire\Admin\Inventory\Units\Form;
use App\Models\EquipmentAuditEvent;
use App\Models\User;
use App\Services\EquipmentLoanLoss;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentPhotos;
use App\Services\EquipmentStock;
use App\Services\EquipmentStructure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\EquipmentPhotoFixtures;
use Tests\Support\EquipmentScenarioTestCase;

class EquipmentPhotoTest extends EquipmentScenarioTestCase
{
    public function test_photo_encoding_size_signature_and_private_storage(): void
    {
        $files = app(EquipmentPhotos::class);
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $file = UploadedFile::fake()->image('alat.'.$ext);
            $meta = $files->stage($file);
            $bytes = Storage::disk('local')->get($meta['storage_key']);
            $this->assertSame(hash('sha256', $bytes), $meta['sha256']);
            $this->assertSame(strlen($bytes), $meta['byte_size']);
        }
        $this->expectException(ValidationException::class);
        $files->stage(UploadedFile::fake()->createWithContent('fake.jpg', 'bukan foto'));
    }

    public function test_oversized_file_is_rejected_and_legacy_return_remains_readable(): void
    {
        try {
            app(EquipmentPhotos::class)->stage(UploadedFile::fake()->image('large.png')->size(5121));
            $this->fail('Oversized upload accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('photos', $e->errors());
        }
        $loan = $this->checkout([$this->units[0]]);
        $input = $this->returnInput($loan, $loan->items->all());
        $receipt = $this->recordReturn($this->actor(), $this->token(), $input);
        // Construct a historical receipt fixture with the same fields as pre-photo returns.
        $legacy = $receipt->replicate(['operation_token', 'operation_actor_key', 'operation_payload_hash']);
        $legacy->operation_token = $this->token();
        $legacy->operation_actor_key = $receipt->operation_actor_key;
        $legacy->operation_payload_hash = $receipt->operation_payload_hash;
        $legacy->save();
        $this->actingAs($this->actor())->get(route('admin.equipment-loans.show', $loan))->assertOk()->assertSee('Retur historis tanpa bukti foto');
    }

    public function test_stock_priority_and_checkout_eligibility_for_all_groups(): void
    {
        $units = [...$this->units, $this->unitFixture(), $this->unitFixture()];
        $loan = $this->checkout([$units[0], $units[1]]);
        app(EquipmentLoanLoss::class)->record($this->actor(), $this->token(), $this->lossInput($loan, [$loan->items()->where('inventory_unit_id', $units[0]->id)->firstOrFail()]));
        $units[2]->condition = 'minor_damage';
        $units[2]->is_active = false;
        $units[2]->save();
        $units[3]->is_active = false;
        $units[3]->save();
        $stock = app(EquipmentStock::class);
        $counts = $stock->counts($stock->units()->whereIn('inventory_units.id', array_map(fn ($u) => $u->id, $units)));
        $this->assertSame(5, $counts['total']);
        foreach (['lost', 'borrowed', 'damaged', 'available', 'inactive'] as $state) {
            $this->assertSame(1, $counts[$state]);
        }
        $this->checkout([$units[4]]);
        $this->assertSame(0, $stock->counts($stock->units()->where('inventory_units.id', $units[4]->id))['available']);
    }

    public function test_replacement_keeps_both_files_and_stale_form_removes_only_its_new_upload(): void
    {
        $prior = Storage::disk('local')->allFiles('equipment');
        $unit = $this->units[0];
        $this->actingAs($this->actor());
        $edit = fn () => Livewire::test(Form::class, ['unit' => $unit->fresh()]);
        $stale = $edit();
        foreach ([1, 2] as $revision) {
            $edit()->set('photo', UploadedFile::fake()->image('camera.png', $revision * 10, 10))->set('reason', 'Synthetic new photo')->call('save')->assertHasNoErrors();
        }
        $before = Storage::disk('local')->allFiles('equipment');
        $stale->set('photo', UploadedFile::fake()->image('old.png'))->set('reason', 'Stale attempt')->call('save')->assertHasErrors();
        $photos = $this->primary->table('inventory_unit_photos')->where('unit_id', $unit->id)->get();
        $this->assertCount(2, $photos);
        $this->assertCount(2, array_diff($before, $prior));
        $this->assertEqualsCanonicalizing($before, Storage::disk('local')->allFiles('equipment'));
        foreach ($photos as $photo) {
            $this->get(route('admin.inventory.photo', [$unit, $photo->id]))->assertOk();
            $this->get(route('admin.inventory.photo', [$this->units[1], $photo->id]))->assertNotFound();
        }
    }

    public function test_evidence_is_mandatory_scoped_and_durable_after_partial_return(): void
    {
        if (! EquipmentPhotos::enabled()) {
            $this->markTestSkipped('Requires provisioned Equipment photo schema.');
        }
        $loan = $this->checkout(array_slice($this->units, 0, 2));
        $items = $loan->items()->orderBy('id')->get();
        $input = $this->returnInput($loan, [$items[0]], 'minor_damage');
        $token = $this->token();
        $service = app(EquipmentLoanReturn::class);
        try {
            $service->record($this->actor(), $token, $input);
            $this->fail('Missing evidence accepted');
        } catch (ValidationException $e) {
            $this->assertNull($items[0]->fresh()->returned_at);
        }
        $photos = EquipmentPhotoFixtures::evidence($input);
        $receipt = $service->record($this->actor(), $token, $input, $photos);
        $this->assertSame($receipt->id, $service->record($this->actor(), $token, $input, $photos)->id);
        $photo = $this->primary->table('equipment_return_photos')->where('receipt_id', $receipt->id)->sole();
        if (EquipmentStructure::installed()) {
            $normalized = $this->primary->table('equipment_return_receipts')->where('audit_event_id', $receipt->id)->sole();
            $detail = $this->primary->table('equipment_return_items')->where('receipt_id', $normalized->id)->sole();
            $this->assertSame($normalized->id, $photo->return_receipt_id);
            $this->assertSame($items[0]->id, $detail->loan_item_id);
            $this->assertSame('minor_damage', $detail->condition_in);
        }
        $this->assertSame(1, EquipmentAuditEvent::where('operation_token', $token)->count());
        $stock = app(EquipmentStock::class);
        $counts = $stock->counts($stock->units()->whereIn('inventory_units.id', [$items[0]->inventory_unit_id, $items[1]->inventory_unit_id]));
        $this->assertSame([2, 1, 1], [$counts['total'], $counts['damaged'], $counts['borrowed']]);
        $params = [$loan, $receipt->id, $items[0]->id, $photo->id];
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION] as $role) {
            $this->actingAs($this->actor($role))->get(route('admin.equipment-loans.photo', $params))->assertOk();
        }
        $this->get(route('admin.equipment-loans.photo', [$loan, $receipt->id, $items[1]->id, $photo->id]))->assertNotFound();
        foreach ([User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $this->actingAs($this->actor($role))->get(route('admin.equipment-loans.photo', $params))->assertForbidden();
        }
    }
}
