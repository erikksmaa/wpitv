<?php

namespace App\Services;

use App\Models\InventoryUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EquipmentStock
{
    public const LABELS = ['total' => 'Total unit', 'available' => 'Tersedia', 'borrowed' => 'Dipinjam', 'damaged' => 'Rusak', 'lost' => 'Hilang', 'inactive' => 'Nonaktif'];

    public function units(?bool $expanded = null)
    {
        $expanded ??= $this->normalized();
        $group = $this->groupExpression($expanded);
        $query = InventoryUnit::query()
            ->leftJoin('equipment_loan_items as occupied', 'occupied.active_unit_id', '=', 'inventory_units.id')
            ->leftJoin('inventory_unit_members as membership', 'membership.unit_id', '=', 'inventory_units.id')
            ->leftJoin('inventory_units as owner', 'owner.id', '=', DB::raw($group));
        if ($expanded) {
            $query->leftJoin('inventory_items as stock_item', 'stock_item.id', '=', 'inventory_units.item_id');
        }
        $status = $this->statusExpression($expanded);

        return $query->select('inventory_units.*')->selectRaw("$status AS stock_status, $group AS product_id");
    }

    public function normalized(): bool
    {
        return Schema::hasColumn('inventory_units', 'item_id');
    }

    public function statusExpression(?bool $expanded = null): string
    {
        $ownerActive = ($expanded ?? $this->normalized())
            ? 'COALESCE(stock_item.is_active, owner.is_active, 1)'
            : 'COALESCE(owner.is_active, 1)';

        return "CASE WHEN occupied.lost_at IS NOT NULL THEN 'lost' WHEN occupied.id IS NOT NULL THEN 'borrowed' WHEN inventory_units.condition <> 'good' THEN 'damaged' WHEN inventory_units.is_active = 0 OR $ownerActive = 0 THEN 'inactive' ELSE 'available' END";
    }

    public function groupExpression(?bool $expanded = null): string
    {
        return ($expanded ?? $this->normalized())
            ? 'COALESCE(inventory_units.item_id, membership.parent_id, inventory_units.id)'
            : 'COALESCE(membership.parent_id, inventory_units.id)';
    }

    public function products()
    {
        $expanded = $this->normalized();
        $classified = $this->units($expanded);
        $summary = DB::query()->fromSub($classified->toBase(), 'classified')->select('product_id')->selectRaw('COUNT(*) AS total')->groupBy('product_id');
        foreach (array_keys(self::LABELS) as $status) {
            if ($status !== 'total') {
                $summary->selectRaw("SUM(stock_status = ?) AS $status", [$status]);
            }
        }

        $products = InventoryUnit::query();
        if ($expanded) {
            $masters = DB::table('inventory_units as physical')->leftJoin('inventory_items as master', 'master.id', '=', 'physical.item_id')
                ->select('physical.id', 'physical.item_id', 'physical.condition', 'physical.created_at');
            foreach (['category_id', 'name', 'brand', 'model', 'notes'] as $column) {
                $masters->selectRaw("CASE WHEN master.id IS NULL THEN physical.$column ELSE master.$column END AS $column");
            }
            $masters->selectRaw('COALESCE(master.item_code, physical.inventory_code) AS inventory_code')
                ->selectRaw('COALESCE(master.is_active, physical.is_active) AS is_active');
            $products->fromSub($masters, 'inventory_units');
        }

        return $products->joinSub($summary, 'summary', 'summary.product_id', '=', 'inventory_units.id')->select('inventory_units.*', 'summary.*')->addSelect(['similar_root_id' => DB::table('inventory_units as other')->select('other.id')->whereColumn('other.name', 'inventory_units.name')->whereColumn('other.id', '<>', 'inventory_units.id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('inventory_unit_members as child')->whereColumn('child.unit_id', 'other.id'))->limit(1)]);
    }

    public function counts($query): array
    {
        $counts = DB::query()->fromSub($query->toBase(), 'stock')->groupBy('stock_status')->selectRaw('stock_status, COUNT(*) AS quantity')->pluck('quantity', 'stock_status')->all();

        return ['total' => array_sum($counts)] + $counts + array_fill_keys(array_keys(self::LABELS), 0);
    }
}
