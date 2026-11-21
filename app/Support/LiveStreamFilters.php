<?php

namespace App\Support;

use App\Models\LiveStream;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class LiveStreamFilters
{
    public const SORTS = ['schedule' => LiveStream::START_WIB, 'title' => LiveStream::TITLE_SQL, 'status' => 'live_streams.status', 'updated_at' => 'live_streams.updated_at'];

    public static function normalize(array $input): array
    {
        $from = self::date($input['from'] ?? '');
        $to = self::date($input['to'] ?? '');
        if ($from !== null && $to !== null && ($from > $to || CarbonImmutable::parse($from, 'Asia/Jakarta')->diffInDays(CarbonImmutable::parse($to, 'Asia/Jakarta')) > 366)) {
            throw ValidationException::withMessages(['dateFilter' => 'Rentang tanggal harus berurutan dan maksimal 366 hari.']);
        }

        return [
            'search' => InputBounds::search((string) ($input['search'] ?? '')),
            'status' => in_array($input['status'] ?? '', ['scheduled', 'live', 'ended', 'cancelled'], true) ? $input['status'] : null,
            'visibility' => in_array($input['visibility'] ?? '', ['public', 'internal'], true) ? $input['visibility'] : null,
            'program' => filter_var($input['program'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            'from' => $from, 'to' => $to,
            'sort' => self::SORTS[$input['sort'] ?? ''] ?? self::SORTS['schedule'],
            'direction' => ($input['direction'] ?? '') === 'desc' ? 'desc' : 'asc',
            'per_page' => InputBounds::perPage((int) ($input['per_page'] ?? 10)),
        ];
    }

    private static function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (! preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw ValidationException::withMessages(['dateFilter' => 'Tanggal filter harus valid dengan format YYYY-MM-DD.']);
        }

        return $value;
    }
}
