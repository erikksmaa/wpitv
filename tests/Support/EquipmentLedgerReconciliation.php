<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use RuntimeException;
use stdClass;

final class EquipmentLedgerReconciliation
{
    private const ORIGINAL = [
        'equipment_return_photo_items', 'equipment_return_photos', 'inventory_unit_photos',
        'equipment_audit_events', 'equipment_loan_items', 'equipment_loans',
        'inventory_unit_members', 'inventory_units', 'inventory_categories', 'users',
    ];

    private const ADDITIVE = [
        'inventory_units' => ['item_id', 'retired_at', 'retired_reason'],
        'equipment_return_photos' => ['return_receipt_id'],
    ];

    public function reconcile(Connection $db, array $ledger, array $mapping): array
    {
        $this->require(array_keys($ledger['owned']) === self::ORIGINAL, 'LEDGER_FORMAT');
        $report = ['version' => 2, 'owned' => [], 'derived' => [], 'unknown' => [], 'missing' => [], 'conflicts' => []];
        foreach (self::ORIGINAL as $table) {
            $report['unknown'][$table] = [];
            $report['owned'][$table] = [];
            $unseen = $ledger['owned'][$table];
            foreach ($db->table($table)->get() as $row) {
                $id = $this->key($table, $row);
                if (! isset($unseen[$id])) {
                    $report['unknown'][$table][] = $id;

                    continue;
                }
                $preimage = clone $row;
                foreach (self::ADDITIVE[$table] ?? [] as $column) {
                    unset($preimage->{$column});
                }
                $valid = hash_equals($unseen[$id], $this->hash($preimage));
                if ($table === 'inventory_units') {
                    $parent = $db->table('inventory_unit_members')->where('unit_id', $id)->value('parent_id') ?? $id;
                    $valid = $valid && isset($mapping['groups'][$parent]) && (int) $row->item_id === (int) $parent
                        && $row->retired_at === null && $row->retired_reason === null;
                }
                if ($table === 'equipment_return_photos') {
                    $valid = $valid && ($mapping['receipts'][$row->receipt_id] ?? null) !== null
                        && (int) $row->return_receipt_id === (int) $mapping['receipts'][$row->receipt_id];
                }
                if (! $valid) {
                    $report['conflicts'][$table][] = $id;
                }
                $report['owned'][$table][$id] = ['before' => $unseen[$id], 'after' => $this->hash($row)];
                unset($unseen[$id]);
            }
            $report['missing'][$table] = array_keys($unseen);
        }
        $report['allowed'] = ! $report['conflicts'] && ! array_filter($report['missing']);
        if (! $report['allowed']) {
            return $report;
        }

        $original = $report['owned'];
        $report['derived'] = ['inventory_items' => [], 'equipment_return_receipts' => [], 'equipment_return_items' => []];
        foreach ($mapping['groups'] as $parent => $quantity) {
            if (! isset($original['inventory_units'][$parent])) {
                continue;
            }
            $members = $db->table('inventory_units')->where('item_id', $parent)->pluck('id');
            $this->require($members->count() === $quantity && $members->every(fn ($id) => isset($original['inventory_units'][$id])), 'MIXED_GROUP');
            $item = $db->table('inventory_items')->where('id', $parent)->sole();
            $root = $db->table('inventory_units')->where('id', $parent)->sole();
            foreach (['category_id', 'name', 'brand', 'model', 'notes', 'is_active', 'version', 'created_at', 'updated_at'] as $field) {
                $this->require($item->{$field} === $root->{$field}, 'ITEM_EDITED');
            }
            $this->require($item->item_code === $root->inventory_code && $item->next_unit_number === $quantity + 1, 'ITEM_IDENTITY');
            $report['derived']['inventory_items'][$parent] = $this->hash($item);
        }
        foreach ($mapping['receipts'] as $audit => $id) {
            if (! isset($original['equipment_audit_events'][$audit])) {
                continue;
            }
            $receipt = $db->table('equipment_return_receipts')->where('id', $id)->sole();
            $event = $db->table('equipment_audit_events')->where('id', $audit)->sole();
            $results = array_column(json_decode($event->after_data, true, 512, JSON_THROW_ON_ERROR)['items'], null, 'item_id');
            $this->require((int) $receipt->audit_event_id === (int) $audit && isset($original['equipment_loans'][$receipt->loan_id])
                && $receipt->loan_id === $event->loan_id && $receipt->receipt_code === 'RET-'.$audit
                && $receipt->received_by === $event->actor_id && $receipt->received_by_name === $event->actor_name
                && $receipt->created_at === $event->created_at && $receipt->returned_at === max(array_column($results, 'returned_at'))
                && $receipt->notes === null, 'RECEIPT_OWNERSHIP');
            $report['derived']['equipment_return_receipts'][$id] = $this->hash($receipt);
            $details = $db->table('equipment_return_items')->where('receipt_id', $id)->get();
            $this->require($details->count() === count($results), 'DETAIL_COUNT');
            foreach ($details as $detail) {
                $expected = $results[$detail->loan_item_id] ?? [];
                $this->require(isset($original['equipment_loan_items'][$detail->loan_item_id]) && $expected
                    && $detail->condition_in === $expected['condition_in'] && $detail->return_notes === $expected['return_notes']
                    && $detail->returned_at === $expected['returned_at'], 'DETAIL_OWNERSHIP');
                $report['derived']['equipment_return_items'][$detail->id] = $this->hash($detail);
            }
        }

        return $report;
    }

    /** Dry-run deletes are rolled back. The caller can supply an already guarded testing connection. */
    public function verifyCleanup(Connection $db, array $report, string $serverUuid, bool $delete = false): array
    {
        $session = $db->selectOne('SELECT DATABASE() db, CURRENT_USER() account, @@server_uuid uuid, @@foreign_key_checks fk');
        $this->require((($session->db === 'wpitv_6c4j_validation_testing' && $session->account === 'validation_runner@localhost')
                || ($session->db === 'wpitv_testing' && $session->account === 'wpitv_test_runner@localhost'))
            && $session->uuid === $serverUuid && (int) $session->fk === 1 && $report['allowed'], 'CLEANUP_TARGET');
        $order = [
            'equipment_return_photo_items', 'equipment_return_photos', 'equipment_return_items', 'equipment_return_receipts',
            'inventory_unit_photos', 'inventory_unit_members', 'equipment_audit_events', 'equipment_loan_items',
            'equipment_loans', 'inventory_units', 'inventory_items', 'inventory_categories', 'users',
        ];
        $owned = $report['derived'];
        foreach ($report['owned'] as $table => $values) {
            $owned[$table] = array_map(fn ($row) => $row['after'], $values);
        }
        $level = $db->transactionLevel();
        $db->beginTransaction();
        try {
            foreach ($order as $table) {
                foreach ($owned[$table] as $id => $hash) {
                    $row = $this->query($db, $table, $id)->lockForUpdate()->sole();
                    $this->require(hash_equals($hash, $this->hash($row)), 'POSTIMAGE_CHANGED');
                }
            }
            $incoming = $db->table('information_schema.KEY_COLUMN_USAGE')
                ->where('REFERENCED_TABLE_SCHEMA', $session->db)->whereIn('REFERENCED_TABLE_NAME', $order)
                ->get(['TABLE_SCHEMA', 'TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']);
            foreach ($incoming as $fk) {
                $ids = array_keys($owned[$fk->REFERENCED_TABLE_NAME]);
                if (! $ids) {
                    continue;
                }
                $this->require($fk->TABLE_SCHEMA === $session->db && $fk->REFERENCED_COLUMN_NAME === 'id', 'FK_SCOPE');
                foreach ($db->table($fk->TABLE_NAME)->whereIn($fk->COLUMN_NAME, $ids)->lockForUpdate()->get() as $row) {
                    $this->require(isset($owned[$fk->TABLE_NAME][$this->key($fk->TABLE_NAME, $row)]), 'FOREIGN_OWNERSHIP');
                }
            }
            foreach ($order as $table) {
                foreach (array_keys($owned[$table]) as $id) {
                    $this->require($this->query($db, $table, $id)->delete() === 1, 'DELETE_COUNT');
                }
            }

            if ($delete) {
                $db->commit();
            }

            return array_map('count', $owned);
        } finally {
            if (! $delete || $db->transactionLevel() > $level) {
                $db->rollBack($level);
            }
        }
    }

    private function query(Connection $db, string $table, int|string $id)
    {
        $query = $db->table($table);
        if ($table === 'equipment_return_photo_items') {
            [$photo, $item] = explode(':', $id, 2);

            return $query->where('photo_id', $photo)->where('loan_item_id', $item);
        }

        return $query->where($table === 'inventory_unit_members' ? 'unit_id' : 'id', $id);
    }

    private function key(string $table, stdClass $row): int|string
    {
        return match ($table) {
            'equipment_return_photo_items' => $row->photo_id.':'.$row->loan_item_id,
            'inventory_unit_members' => $row->unit_id,
            default => $row->id ?? '',
        };
    }

    private function hash(stdClass $row): string
    {
        return hash('sha256', serialize($row));
    }

    private function require(bool $valid, string $code): void
    {
        if (! $valid) {
            throw new RuntimeException($code);
        }
    }
}
