<?php

namespace App\Livewire\Admin\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class InventoryActivity
{
    public const LABELS = [
        'category.created' => 'Menambahkan kategori',
        'category.updated' => 'Mengubah kategori',
        'model.created' => 'Menambahkan peralatan dan stok awal',
        'model.updated' => 'Mengubah peralatan',
        'model.photo_added' => 'Menambahkan foto peralatan',
        'stock.added' => 'Menambah stok',
        'unit.condition_changed' => 'Mengubah kondisi atau serial unit',
        'unit.maintenance_started' => 'Memulai perawatan',
        'unit.maintenance_finished' => 'Menyelesaikan perawatan',
        'unit.lost' => 'Menandai unit hilang',
        'unit.found' => 'Mencatat unit ditemukan',
        'unit.retired' => 'Menonaktifkan unit secara permanen',
        'unit.photo_added' => 'Menambahkan foto unit',
        'loan.checked_out' => 'Mencatat peminjaman',
        'loan.extended' => 'Memperpanjang peminjaman',
        'loan.voided' => 'Membatalkan peminjaman',
        'loan.loss_recorded' => 'Mencatat kehilangan saat peminjaman',
        'return.created' => 'Mencatat pengembalian',
    ];

    public static function query(string $search, string $activity, string $from, string $until): Builder
    {
        $labels = array_keys(array_filter(self::LABELS, fn ($label) => mb_stripos($label, trim($search, '%')) !== false));
        $query = DB::table('equipment_audit_log')->select('id', 'actor_name_snapshot', 'event_name', 'reason', 'before_data', 'after_data', 'occurred_at')
            ->where(fn ($q) => $q->where('actor_name_snapshot', 'like', $search)->orWhere('reason', 'like', $search)
                ->orWhereIn('event_name', $labels)
                ->orWhere('after_data->record->name', 'like', $search)->orWhere('after_data->record->asset_code', 'like', $search)
                ->orWhere('after_data->loan->loan_number', 'like', $search));
        if (isset(self::LABELS[$activity])) {
            $query->where('event_name', $activity);
        }
        $dates = ['from' => $from ?: null, 'until' => $until ?: null];
        if (Validator::make($dates, [
            'from' => 'nullable|date_format:Y-m-d',
            'until' => 'nullable|date_format:Y-m-d'.($from ? '|after_or_equal:from' : ''),
        ])->fails()) {
            return $query->whereRaw('1 = 0')->orderByDesc('id');
        }
        foreach ($dates as $key => $date) {
            if ($date) {
                $day = CarbonImmutable::parse($date, 'Asia/Jakarta')->startOfDay();
                $query->where('occurred_at', $key === 'from' ? '>=' : '<', ($key === 'from' ? $day : $day->addDay())->utc());
            }
        }
        return $query->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public static function subject(object $event): string
    {
        $after = json_decode($event->after_data ?? '', true);
        $before = json_decode($event->before_data ?? '', true);
        $record = $after['record'] ?? $before['record'] ?? $before ?? [];
        return $record['asset_code'] ?? $record['name'] ?? $after['loan']['loan_number']
            ?? $after['unit']['asset_code'] ?? $before['asset_code'] ?? 'Snapshot nama tidak tersedia';
    }

    public static function reason(string $reason): string
    {
        return preg_replace('/^(?:category\.create|model\.create|model\.photo_add|unit\.photo_add|stock\.add|unit\.condition|loan\.checkout|loan\.extend|loan\.void|loan\.loss|return\.create):\s*/', '', $reason);
    }
}
