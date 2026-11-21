<?php

namespace App\Support;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\WpitvAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Pure mapping of Agenda form intent to the existing manual Live lifecycle. */
final class LiveStreamAgendaChanges
{
    public const FIELDS = ['title', 'type', 'program_id', 'coverage_id', 'group_id', 'agenda_date', 'start_time', 'end_time', 'location', 'description', 'status', 'visibility'];

    public static function apply(LiveStream $stream, WpitvAgenda $agenda, array $input, CarbonImmutable $now, ?string $reason): void
    {
        LiveStreamState::assertConsistent($stream, $agenda);
        if (array_diff(array_keys($input), self::FIELDS)) {
            throw ValidationException::withMessages(['operation' => 'Form memuat kolom yang tidak diizinkan.']);
        }
        if (array_key_exists('type', $input) && $input['type'] !== 'live') {
            throw ValidationException::withMessages(['type' => 'Jenis Agenda yang terhubung dengan Live Streaming harus tetap Live.']);
        }
        $desiredStatus = $input['status'] ?? $agenda->status;
        $metadata = array_diff_key($input, ['type' => true, 'status' => true]);
        $agenda->fill($metadata);
        if ($stream->status === 'ended' && $agenda->isDirty(['agenda_date', 'start_time', 'end_time'])) {
            throw ValidationException::withMessages(['agenda_date' => 'Jadwal siaran yang sudah selesai tidak dapat diubah.']);
        }
        if ($desiredStatus === $agenda->status) {
            return;
        }
        if ($stream->status === 'scheduled' && in_array($desiredStatus, ['draft', 'scheduled'], true)) {
            $agenda->status = $desiredStatus;

            return;
        }
        $target = match ($desiredStatus) {
            'completed' => 'ended', 'cancelled' => 'cancelled', 'scheduled' => 'scheduled', default => null,
        };
        if ($target === null) {
            throw new LiveStreamOperationException('LIVE_STATE');
        }
        if ($stream->status === 'cancelled' && $target === 'scheduled') {
            foreach (['agenda_date', 'start_time', 'end_time', 'visibility'] as $field) {
                if (! array_key_exists($field, $input)) {
                    throw ValidationException::withMessages([$field => 'Nyatakan jadwal ulang dan visibility secara lengkap; jam selesai boleh kosong.']);
                }
            }
        }
        LiveStreamState::applyTransition($stream, $agenda, $target, $now, $reason);
    }
}
