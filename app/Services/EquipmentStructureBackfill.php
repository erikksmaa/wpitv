<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class EquipmentStructureBackfill
{
    public function run(Connection $db): array
    {
        return $db->transaction(function () use ($db) {
            $units = $db->table('inventory_units')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $parents = $db->table('inventory_unit_members')->lockForUpdate()->pluck('parent_id', 'unit_id');
            $groups = [];
            foreach ($units as $unit) {
                $parent = (int) $parents->get($unit->id, $unit->id);
                if (! $units->has($parent) || $parents->has($parent) || ($unit->item_id !== null && (int) $unit->item_id !== $parent)) {
                    throw new RuntimeException('Ambiguous inventory membership for unit '.$unit->id);
                }
                $groups[$parent][] = $unit->id;
            }
            foreach ($groups as $parent => $ids) {
                if (! $db->table('inventory_items')->where('id', $parent)->exists()) {
                    EquipmentStructure::syncItem($db, $units[$parent]);
                }
                $db->table('inventory_units')->whereIn('id', $ids)->whereNull('item_id')->update(['item_id' => $parent]);
                $next = max(count($ids) + 1, (int) $db->table('inventory_items')->where('id', $parent)->value('next_unit_number'));
                $db->table('inventory_items')->where('id', $parent)->update(['next_unit_number' => $next]);
            }

            $receipts = [];
            $issues = [];
            $claimed = [];
            $candidates = [];
            $events = $db->table('equipment_audit_events')->where('event_type', 'returned')->orderBy('id')->lockForUpdate()->get();
            foreach ($events as $event) {
                $results = json_decode($event->after_data ?? '', true)['items'] ?? [];
                $ids = array_column($results, 'item_id');
                $items = $db->table('equipment_loan_items')->whereIn('id', $ids)->get()->keyBy('id');
                $valid = $event->loan_id !== null && filled($event->actor_name) && $results && count($ids) === count($results) && count($ids) === $items->count();
                foreach ($results as $result) {
                    $item = $items->get($result['item_id'] ?? 0);
                    $valid = $valid && $item && $item->returned_at !== null && (int) $item->loan_id === (int) $event->loan_id
                        && (int) $item->inventory_unit_id === (int) ($result['unit_id'] ?? 0)
                        && $item->returned_at === ($result['returned_at'] ?? null)
                        && $item->condition_in === ($result['condition_in'] ?? null)
                        && $item->return_notes === ($result['return_notes'] ?? null);
                }
                $photoIds = $db->table('equipment_return_photos')->where('receipt_id', $event->id)->pluck('id');
                $links = $db->table('equipment_return_photo_items')->whereIn('photo_id', $photoIds)->get();
                $valid = $valid && $links->pluck('photo_id')->unique()->count() === $photoIds->count()
                    && $links->every(fn ($link) => $items->has($link->loan_item_id));
                if (! $valid) {
                    $issues[] = $event->id;

                    continue;
                }
                foreach ($ids as $id) {
                    $claimed[$id][] = $event->id;
                }
                $candidates[] = [$event, $results, $ids, $photoIds];
            }
            foreach ($candidates as [$event, $results, $ids, $photos]) {
                if (collect($ids)->contains(fn ($id) => count($claimed[$id]) !== 1)) {
                    $issues[] = $event->id;

                    continue;
                }
                $receipt = EquipmentStructure::recordReceipt($db, $event, $results);
                $db->table('equipment_return_photos')->whereIn('id', $photos)->whereNull('return_receipt_id')->update(['return_receipt_id' => $receipt]);
                $receipts[$event->id] = $receipt;
            }

            return ['groups' => array_map('count', $groups), 'receipts' => $receipts, 'review_events' => $issues,
                'unmapped_returns' => $db->table('equipment_loan_items')->whereNotNull('returned_at')->whereNotIn('id', $db->table('equipment_return_items')->select('loan_item_id'))->pluck('id')->all()];
        }, 3);
    }
}
