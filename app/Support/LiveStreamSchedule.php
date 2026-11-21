<?php

namespace App\Support;

use App\Models\WpitvAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Agenda date/time values are WIB wall time; all returned instants are UTC. */
final class LiveStreamSchedule
{
    public static function startsAt(WpitvAgenda $agenda): ?CarbonImmutable
    {
        return $agenda->start_time ? self::instant($agenda, $agenda->start_time) : null;
    }

    public static function endsAt(WpitvAgenda $agenda): ?CarbonImmutable
    {
        if (! $agenda->end_time) {
            return null;
        }
        $start = self::startsAt($agenda);
        if ($start === null) {
            throw ValidationException::withMessages(['start_time' => 'Jam mulai wajib diisi jika jam selesai tersedia.']);
        }
        $end = self::instant($agenda, $agenda->end_time);
        if ($end->equalTo($start)) {
            throw ValidationException::withMessages(['end_time' => 'Jam selesai harus berbeda dari jam mulai.']);
        }

        // Explicit MVP convention: earlier end clock means next calendar day WIB.
        return $end->lt($start) ? $end->addDay() : $end;
    }

    private static function instant(WpitvAgenda $agenda, string $time): CarbonImmutable
    {
        if (! preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $time) || $agenda->agenda_date === null) {
            throw ValidationException::withMessages(['start_time' => 'Tanggal dan jam agenda tidak valid.']);
        }
        $value = $agenda->agenda_date->format('Y-m-d').' '.$time.(strlen($time) === 5 ? ':00' : '');

        return CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, 'Asia/Jakarta')->utc();
    }

    /** @return array{CarbonImmutable, ?CarbonImmutable} */
    public static function manual(string $date, string $startTime, ?string $endTime): array
    {
        $agenda = new WpitvAgenda;
        $agenda->agenda_date = $date;
        $agenda->start_time = $startTime;
        $agenda->end_time = $endTime;

        return [self::startsAt($agenda), self::endsAt($agenda)];
    }
}
