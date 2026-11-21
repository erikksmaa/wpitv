<?php

namespace App\Support;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\WpitvAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Pure state rules: no queries, platform requests, or persistence. */
final class LiveStreamState
{
    public static function assertVersion(LiveStream $stream, int $expectedVersion): void
    {
        if ($stream->version !== $expectedVersion) {
            throw new LiveStreamOperationException('LIVE_STALE');
        }
    }

    public static function applyYouTubeId(LiveStream $stream, ?string $id): void
    {
        if ($id !== null && ! YouTubeVideoId::isValid($id)) {
            throw ValidationException::withMessages(['youtube_input' => 'ID YouTube tidak valid.']);
        }
        if ($id === $stream->youtube_id) {
            return;
        }
        if (! in_array($stream->status, ['scheduled', 'cancelled'], true) || $stream->archive_video_id !== null) {
            throw new LiveStreamOperationException('LIVE_STATE');
        }
        $stream->youtube_id = $id;
    }

    public static function assertConsistent(LiveStream $stream, ?WpitvAgenda $agenda): void
    {
        $agendaStates = match ($stream->status) {
            'scheduled' => ['draft', 'scheduled'], 'live' => ['scheduled'],
            'ended' => ['completed'], 'cancelled' => ['cancelled'], default => [],
        };
        $markersValid = match ($stream->status) {
            'scheduled' => $stream->live_marked_at === null && $stream->ended_marked_at === null && $stream->cancelled_marked_at === null,
            'live' => $stream->live_marked_at !== null && $stream->ended_marked_at === null && $stream->cancelled_marked_at === null,
            'ended' => $stream->ended_marked_at !== null && $stream->cancelled_marked_at === null
                && ($stream->live_marked_at === null || $stream->ended_marked_at->gte($stream->live_marked_at)),
            'cancelled' => $stream->cancelled_marked_at !== null && $stream->live_marked_at === null && $stream->ended_marked_at === null,
            default => false,
        };
        $scheduleValid = $agenda === null
            ? $stream->isManual() && trim($stream->manual_title ?? '') !== '' && $stream->manual_starts_at !== null
                && ($stream->manual_ends_at === null || $stream->manual_ends_at->gt($stream->manual_starts_at))
            : $agenda->type === 'live' && in_array($agenda->status, $agendaStates, true);
        if (! $scheduleValid || ! $markersValid
            || ! in_array($agenda === null ? $stream->manual_visibility : $agenda->visibility, ['public', 'internal'], true) || $stream->version < 1
            || ($stream->youtube_id !== null && ! YouTubeVideoId::isValid($stream->youtube_id))
            || (in_array($stream->status, ['live', 'ended'], true) && $stream->youtube_id === null)
            || ($stream->archive_video_id !== null && $stream->status !== 'ended')) {
            throw new LiveStreamOperationException('LIVE_STATE');
        }
    }

    public static function applyTransition(LiveStream $stream, ?WpitvAgenda $agenda, string $target, CarbonImmutable $now, ?string $reason): void
    {
        self::assertConsistent($stream, $agenda);
        if (! in_array($target, LiveStream::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Status siaran tidak valid.']);
        }
        if ($stream->status === $target) {
            return;
        }
        $allowed = match ($stream->status) {
            'scheduled' => ['live', 'ended', 'cancelled'], 'live' => ['ended'],
            'cancelled' => ['scheduled'], default => [],
        };
        if (! in_array($target, $allowed, true)) {
            throw new LiveStreamOperationException('LIVE_STATE');
        }
        if ($target === 'cancelled' || $stream->status === 'cancelled' || ($stream->status === 'scheduled' && $target === 'ended')) {
            if ($reason === null || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Alasan perubahan wajib diisi.']);
            }
        }
        if (in_array($target, ['live', 'ended'], true) && ! YouTubeVideoId::isValid($stream->youtube_id)) {
            throw ValidationException::withMessages(['youtube_input' => 'ID YouTube wajib tersedia sebelum siaran dimulai atau ditutup.']);
        }
        if ($target === 'live' && $agenda !== null && ($agenda->status !== 'scheduled' || LiveStreamSchedule::startsAt($agenda) === null)) {
            throw ValidationException::withMessages(['start_time' => 'Agenda harus terjadwal dan mempunyai jam mulai.']);
        }
        if ($stream->status === 'cancelled') {
            $start = $agenda ? LiveStreamSchedule::startsAt($agenda) : $stream->manual_starts_at;
            if ($start === null || ! $start->gt($now)) {
                throw ValidationException::withMessages(['agenda_date' => 'Jadwal ulang harus berada di masa depan.']);
            }
        }

        $stream->status = $target;
        $stream->status_changed_at = $now;
        if ($target === 'live') {
            $stream->live_marked_at = $now;
        } elseif ($target === 'ended') {
            $stream->ended_marked_at = $now;
        } elseif ($target === 'cancelled') {
            $stream->cancelled_marked_at = $now;
        } else {
            $stream->cancelled_marked_at = null;
        }
        if ($agenda !== null) {
            $agenda->status = match ($target) {
                'ended' => 'completed', 'cancelled' => 'cancelled', default => 'scheduled',
            };
        }
        self::assertConsistent($stream, $agenda);
    }
}
