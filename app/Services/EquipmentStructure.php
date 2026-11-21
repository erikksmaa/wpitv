<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EquipmentStructure
{
    public static function installed(): bool
    {
        return Schema::hasColumn('inventory_units', 'item_id') && Schema::hasColumn('equipment_return_photos', 'return_receipt_id');
    }

    public static function syncItem(Connection $db, object $root): int
    {
        $item = $db->table('inventory_items')->where('id', $root->id)->lockForUpdate()->first();
        $values = [
            'category_id' => $root->category_id, 'item_code' => $root->inventory_code,
            'name' => $root->name, 'brand' => $root->brand, 'model' => $root->model, 'notes' => $root->notes,
            'is_active' => $root->is_active, 'version' => $root->version,
            'created_at' => $root->created_at, 'updated_at' => $root->updated_at,
        ];
        if ($item) {
            $db->table('inventory_items')->where('id', $item->id)->update($values);
        } else {
            $db->table('inventory_items')->insert($values + ['id' => $root->id, 'next_unit_number' => 2]);
        }
        $db->table('inventory_units')->where('id', $root->id)->whereNull('item_id')->update(['item_id' => $root->id]);

        return (int) $root->id;
    }

    /** Keep the audit receipt identity while recording the normalized batch. */
    public static function recordReceipt(Connection $db, object $event, array $results): int
    {
        $created = $event instanceof Model ? $event->getRawOriginal('created_at') : $event->created_at;
        $receipt = $db->table('equipment_return_receipts')->where('audit_event_id', $event->id)->first();
        $returnedAt = max(array_column($results, 'returned_at'));
        $values = [
            'loan_id' => $event->loan_id, 'audit_event_id' => $event->id, 'receipt_code' => 'RET-'.$event->id,
            'returned_at' => $returnedAt, 'created_at' => $created,
            'received_by' => $event->actor_id, 'received_by_name' => $event->actor_name,
        ];
        if (! $receipt) {
            $id = $db->table('equipment_return_receipts')->insertGetId($values);
        } else {
            $id = $receipt->id;
            foreach ($values as $key => $value) {
                if ((string) $receipt->{$key} !== (string) $value) {
                    throw new RuntimeException('Existing return receipt differs from the audit event.');
                }
            }
        }
        foreach ($results as $row) {
            $detail = $db->table('equipment_return_items')->where('loan_item_id', $row['item_id'])->first();
            $values = [
                'receipt_id' => $id, 'loan_item_id' => $row['item_id'], 'condition_in' => $row['condition_in'],
                'return_notes' => $row['return_notes'], 'returned_at' => $row['returned_at'],
            ];
            if (! $detail) {
                $db->table('equipment_return_items')->insert($values);
            } elseif ((array) $detail != ['id' => $detail->id] + $values) {
                throw new RuntimeException('Existing return item differs from the audit event.');
            }
        }

        return (int) $id;
    }
}
