<?php

namespace App\Support;

use App\Exceptions\MonthlyActivityException;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class MonthlyActivityRules
{
    public const DIVISIONS = ['production', 'news'];

    public const DIVISION_LABELS = ['production' => 'Production', 'news' => 'News'];

    public const STATUS_LABELS = ['draft' => 'Draft', 'submitted' => 'Dikirim', 'revision' => 'Revisi', 'approved' => 'Disetujui'];

    public const STATUS_BADGES = ['draft' => 'secondary', 'submitted' => 'primary', 'revision' => 'warning text-dark', 'approved' => 'success'];

    public const ITEM_LIMIT = 500;

    public const ATTACHMENT_LIMIT = 100;

    public const ITEM_FIELDS = [
        'title' => 'Judul', 'description' => 'Deskripsi', 'outcome' => 'Hasil',
        'obstacles' => 'Kendala', 'follow_up' => 'Tindak lanjut', 'contribution' => 'Kontribusi divisi',
    ];

    public static function item(array $input, string $month): array
    {
        $rules = ['activity_date' => ['required', 'date_format:Y-m-d']];
        foreach (self::ITEM_FIELDS as $field => $label) {
            $rules[$field] = ['required', 'string', 'max:'.self::itemLength($field)];
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }
        $item = validator($input, $rules, [], [...self::ITEM_FIELDS, 'activity_date' => 'tanggal pelaksanaan'])->validate();
        if (substr($item['activity_date'], 0, 7) !== $month) {
            throw ValidationException::withMessages(['activity_date' => 'Tanggal pelaksanaan harus berada pada periode laporan.']);
        }

        return $item;
    }

    public static function itemLength(string $field): int
    {
        return $field === 'title' ? 200 : 5000;
    }

    public static function division(string $division): string
    {
        if (! in_array($division, self::DIVISIONS, true)) {
            throw ValidationException::withMessages(['division' => 'Divisi laporan tidak valid.']);
        }

        return $division;
    }

    public static function period(string $month): CarbonImmutable
    {
        if (! preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $month)) {
            throw ValidationException::withMessages(['period' => 'Gunakan periode YYYY-MM yang valid.']);
        }

        return CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
    }

    public static function version(int $current, int $expected): void
    {
        if ($expected < 1 || $expected > 4294967295) {
            throw ValidationException::withMessages(['version' => 'Versi laporan tidak valid.']);
        }
        if ($expected !== $current) {
            throw new MonthlyActivityException('MONTHLY_STALE');
        }
    }

    public static function editable(string $status): bool
    {
        return in_array($status, ['draft', 'revision'], true);
    }

    public static function transition(string $from, string $to): void
    {
        if ((self::editable($from) && $to === 'submitted') || ($from === 'submitted' && in_array($to, ['revision', 'approved'], true))) {
            return;
        }

        throw new MonthlyActivityException('MONTHLY_STATE');
    }

    public static function reason(?string $value, string $field, bool $required = false): ?string
    {
        $value = trim($value ?? '');
        if (($required && $value === '') || mb_strlen($value) > 1000) {
            throw ValidationException::withMessages([$field => 'Isi alasan yang valid dengan panjang maksimal 1000 karakter.']);
        }

        return $value === '' ? null : $value;
    }

    public static function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode(self::canonical($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn ($entry) => is_array($entry) ? self::canonical($entry) : $entry, $value);
    }
}
