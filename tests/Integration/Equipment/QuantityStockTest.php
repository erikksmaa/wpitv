<?php

namespace Tests\Integration\Equipment;

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Admin\EquipmentLoans\Form as Wizard;
use App\Livewire\Admin\Inventory\Units\Form;
use App\Livewire\Admin\Inventory\Units\Index;
use App\Livewire\Admin\Inventory\Units\StockAdjustment;
use App\Models\EquipmentAuditEvent;
use App\Models\InventoryUnit;
use App\Services\EquipmentQuantity;
use App\Services\EquipmentStock;
use App\Services\EquipmentStructure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\EquipmentScenarioTestCase;

class QuantityStockTest extends EquipmentScenarioTestCase
{
    public function test_batch_creation_cover_allocation_shortage_and_archival(): void
    {
        $this->actingAs($this->actor());
        $code = $this->prefix.'-KIT';
        $form = Livewire::test(Form::class)->set('inventory_code', $code)->set('name', $code)->set('brand', 'Sony')->set('model', 'A6400')->set('category_id', $this->category->id)->set('storage_location', 'Rack')->set('quantity', 3)->set('photo', UploadedFile::fake()->image('kit.png'))->call('save');
        $service = app(EquipmentQuantity::class);
        $root = InventoryUnit::where('name', $code)->orderBy('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^SON-A6400-[0-9]+$/', $root->inventory_code);
        $group = $service->members($root->id)->orderBy('inventory_units.id')->get();
        foreach ($group as $unit) {
            $this->own('units', $unit->id);
        }
        $form->assertHasNoErrors();
        $this->assertCount(3, $group);
        $suffixes = EquipmentStructure::installed() ? ['-U02', '-U03'] : ['-2', '-3'];
        $this->assertSame([$root->inventory_code, ...array_map(fn ($suffix) => $root->inventory_code.$suffix, $suffixes)], $group->pluck('inventory_code')->all());
        $this->get(route('admin.inventory.units.show', $group[1]))->assertOk()->assertSee('Unit 2')->assertDontSee('Sesuaikan jumlah stok');
        $this->assertCount(1, $service->photos($group->pluck('id'))->unique()->all());
        $product = app(EquipmentStock::class)->products()->where('inventory_units.id', $root->id)->sole();
        if (EquipmentStructure::installed()) {
            $this->assertSame([$root->id], $group->pluck('item_id')->unique()->all());
            $this->assertSame($root->inventory_code, $root->fresh()->item->item_code);
            $cover = $service->photos([$root->id])->get($root->id);
            $this->assertSame(1, $cover['cover']);
            $this->get(route('admin.inventory.photo', $cover))->assertOk();
            $this->get(route('admin.inventory.photo', [$group[1]->id, $cover[1], 'cover' => 1]))->assertNotFound();
            $this->get(route('admin.inventory.units.edit', [$root, 'barang' => 1]))->assertOk()->assertSee('Edit Barang Inventaris')->assertDontSee('Jumlah stok awal');
            Livewire::withQueryParams(['barang' => 1])->test(Form::class, ['unit' => $root])
                ->set('name', $code.' Revisi')->set('brand', 'Sony Pro')->set('reason', 'Pembaruan data')
                ->set('is_active', false)->call('save')->assertHasNoErrors();
            $this->assertSame($code, $root->fresh()->name);
            $this->assertSame($root->inventory_code, $root->fresh()->item->item_code);
            $this->assertSame(0, $service->available($root->id)->count());
            Livewire::withQueryParams(['barang' => 1])->test(Form::class, ['unit' => $root])
                ->set('is_active', true)->set('reason', 'Kembali aktif')->call('save')->assertHasNoErrors();
            $this->assertSame(3, $service->available($root->id)->count());
        }
        $this->assertEquals(3, $product->total);
        $this->assertEquals(3, $product->available);
        $this->get(route('admin.inventory.units.index', ['search' => $code]))->assertOk()->assertSee($root->inventory_code)->assertDontSee($group[1]->inventory_code);
        $cards = Livewire::test(Index::class)
            ->set('category', (string) $this->category->id)->set('search', $code)->set('availability', 'available');
        $cards->assertSee($root->inventory_code)->set('status', '0')->assertDontSee($root->inventory_code)
            ->set('status', 'all')->set('availability', 'unavailable')->assertDontSee($root->inventory_code);
        $auditCount = $root->auditEvents()->count();
        EquipmentAuditEvent::creating(function ($event) {
            if (($event->after_data['operation'] ?? null) === 'stock_added') {
                throw new \RuntimeException('synthetic audit failure');
            }
        });
        try {
            $service->adjust($this->actor(), $root->id, $root->version, 2, 'Synthetic rollback', true);
            $this->fail('Audit failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('synthetic audit failure', $e->getMessage());
        } finally {
            EquipmentAuditEvent::flushEventListeners();
        }
        $this->assertSame(3, $service->members($root->id)->count());
        $this->assertSame($auditCount, $root->auditEvents()->count());
        $this->assertSame($root->version, $root->fresh()->version);
        $this->get(route('admin.inventory.units.show', $root))->assertOk()->assertSee('Kelola stok barang')->assertSee('Unit 1')->assertDontSee('Unit induk');
        $this->get(route('admin.inventory.units.edit', $group[1]))->assertOk()->assertSee('Kelola jumlah stok di detail barang');
        $data = $this->input([$group[0]]);
        unset($data['items']);
        $wizard = Livewire::test(Wizard::class)->set('data', $data)->set('rows.0.product', $root->id);
        foreach ([0, -1, '1.5', 4] as $invalid) {
            $wizard->set('rows.0.quantity', $invalid)->call('review')->assertHasErrors('rows.0.quantity');
        }
        $wizard->set('rows.0.quantity', 2)->call('review')->assertHasNoErrors();
        $this->assertSame($group->take(2)->pluck('id')->all(), array_column($wizard->get('allocation')[0], 'unit_id'));
        $wizard->call('edit')->call('addRow')->set('rows.1.product', $root->id)->call('review')->assertHasErrors('rows.1.product');
        $wizard->set('rows.1.product', $this->units[0]->id)->set('rows.0.mode', 'manual')
            ->set('rows.0.units', [$group[0]->id, $this->units[0]->id])->call('review')->assertHasErrors('rows.0.quantity');
        $wizard->set('rows.0.units', [$group[0]->id, $group[1]->id])->call('review')->assertHasNoErrors();
        $token = $wizard->get('submissionToken');
        $this->checkout([$group[0]]);
        $wizard->call('save')->assertHasErrors(['items', 'rows.0.quantity']);
        $this->assertDatabaseMissing('equipment_loans', ['submission_token' => $token]);
        $version = $root->fresh()->version;
        foreach ([0, 3] as $bad) {
            try {
                $service->adjust($this->actor(), $root->id, $version, $bad, 'Synthetic archive', false);
                $this->fail('Invalid amount accepted');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($bad === 0 ? 'amount' : 'quantity', $error->errors());
            }
        }
        Livewire::test(StockAdjustment::class, ['unitId' => $group[1]->id])
            ->set('action', 'archive')->set('quantity', 2)->set('reason', 'Synthetic archive')
            ->call('save')->assertHasNoErrors()->assertSee('Penyesuaian stok tersimpan.');
        $this->assertTrue($root->fresh()->is_active);
        $this->assertSame(0, $service->available($root->id)->count());
        $wizard->call('edit')->set('rows.0.mode', 'auto')->set('rows.0.quantity', 1)
            ->call('review')->assertHasErrors('rows.0.quantity')->call('save')->assertHasErrors('items');
        $this->expectException(EquipmentOperationException::class);
        $service->adjust($this->actor(), $root->id, $version, 1, 'Stale', true);
    }
}
