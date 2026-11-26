<?php

namespace App\Services;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\LiveStreamEvent;
use App\Models\Program;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Support\AgendaLiveGuard;
use App\Support\LiveStreamAgendaChanges;
use App\Support\LiveStreamErrors;
use App\Support\LiveStreamSchedule;
use App\Support\LiveStreamState;
use App\Support\YouTubeVideoId;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The coordinated write boundary for Live and its linked Agenda. No platform API. */
class LiveStreamLifecycle
{
    private const AGENDA_FIELDS = ['title', 'program_id', 'coverage_id', 'group_id', 'agenda_date', 'start_time', 'end_time', 'location', 'description', 'status', 'visibility'];

    private const SCHEDULE_FIELDS = ['agenda_date', 'start_time', 'end_time'];

    public function create(User $actor, ?int $agendaId, ?string $youtubeInput = null, string $mode = 'agenda', array $manual = []): LiveStream
    {
        return LiveStreamErrors::run(function () use ($actor, $agendaId, $youtubeInput, $mode, $manual) {
            $this->authorize($actor, 'create', LiveStream::class);
            Validator::make(['mode' => $mode, 'agendaId' => $agendaId], [
                'mode' => ['required', Rule::in(['manual', 'agenda'])],
                'agendaId' => $mode === 'agenda' ? ['required', 'integer', 'min:1'] : ['prohibited'],
            ])->validate();
            $id = $this->youtubeId($youtubeInput);

            return DB::transaction(function () use ($actor, $agendaId, $id, $manual) {
                $agenda = $agendaId === null ? null : WpitvAgenda::lockForUpdate()->findOrFail($agendaId);
                $fresh = $this->authorize($actor, 'create', LiveStream::class);
                if ($agenda !== null) {
                    Gate::forUser($fresh)->authorize('update', $agenda);
                    if (LiveStream::where('agenda_id', $agendaId)->lockForUpdate()->exists()) {
                        throw new LiveStreamOperationException('LIVE_DUPLICATE');
                    }
                    $this->validateAgenda($agenda);
                }
                $now = CarbonImmutable::now('UTC');
                $stream = new LiveStream;
                $stream->agenda_id = $agenda?->id;
                if ($agenda === null) {
                    $this->applyManual($stream, $fresh, $manual);
                }
                $stream->youtube_id = $id;
                $stream->status = 'scheduled';
                $stream->version = 1;
                $stream->status_changed_at = $now;
                $stream->status_changed_by = $fresh->id;
                $stream->created_by = $fresh->id;
                $stream->updated_by = $fresh->id;
                LiveStreamState::assertConsistent($stream, $agenda);
                $stream->saveCoordinated();
                $this->audit($stream, $agenda, $fresh, 'created', null, null, $now);

                return $stream->setRelation('agenda', $agenda);
            }, 3);
        });
    }

    public function updateConfiguration(User $actor, int $streamId, int $expectedVersion, ?string $youtubeInput, ?array $manual = null): LiveStream
    {
        return $this->mutate($actor, $streamId, $expectedVersion, 'update', $manual === null ? 'youtube_updated' : 'manual_updated', null,
            function (LiveStream $stream, ?WpitvAgenda $agenda, User $fresh) use ($youtubeInput, $manual) {
                if ($manual !== null) {
                    if ($agenda !== null || $stream->status !== 'scheduled') {
                        throw new LiveStreamOperationException('LIVE_STATE');
                    }
                    $this->applyManual($stream, $fresh, $manual);
                }
                LiveStreamState::applyYouTubeId($stream, $this->youtubeId($youtubeInput));
            });
    }

    public function updateVisibility(User $actor, int $streamId, int $expectedVersion, string $visibility): LiveStream
    {
        return $this->mutate($actor, $streamId, $expectedVersion, 'update', 'agenda_updated', null,
            function (LiveStream $stream, ?WpitvAgenda $agenda, User $fresh, CarbonImmutable $now) use ($visibility) {
                if ($agenda !== null) {
                    $this->agendaChange(['visibility' => $visibility], null)($stream, $agenda, $fresh, $now);

                    return;
                }
                $stream->manual_visibility = Validator::make(['visibility' => $visibility], [
                    'visibility' => ['required', Rule::in(['public', 'internal'])],
                ])->validate()['visibility'];
            });
    }

    public function updateAgenda(User $actor, int $streamId, int $expectedVersion, array $input, ?string $reason = null): LiveStream
    {
        return $this->mutate($actor, $streamId, $expectedVersion, 'update', 'agenda_updated', $reason,
            $this->agendaChange($input, $reason));
    }

    /** Agenda UI writes discover the link only after locking the Agenda header. */
    public function saveAgenda(User $actor, int $agendaId, ?int $expectedVersion, array $input, ?string $reason = null): WpitvAgenda
    {
        return LiveStreamErrors::run(fn () => DB::transaction(function () use ($actor, $agendaId, $expectedVersion, $input, $reason) {
            $agenda = WpitvAgenda::lockForUpdate()->findOrFail($agendaId);
            $fresh = $this->authorizeAgenda($actor, 'update', $agenda);
            $guard = app(AgendaLiveGuard::class);
            $stream = $guard->stream($agenda, true);
            if ($stream !== null) {
                $this->performMutation($fresh, $stream, $agenda, $expectedVersion, 'update', 'agenda_updated', $reason, $this->agendaChange($input, $reason));
            } else {
                $this->assertUnlinkedVersion($expectedVersion);
                $this->allowFields($input, LiveStreamAgendaChanges::FIELDS);
                $agenda->fill($this->agendaInput($input, LiveStreamAgendaChanges::FIELDS));
                $this->validateAgenda($agenda, false);
                $guard->persist($agenda);
            }

            return $agenda;
        }, 3));
    }

    public function cancelAgenda(User $actor, int $agendaId, ?int $expectedVersion, ?string $reason = null): void
    {
        LiveStreamErrors::run(fn () => DB::transaction(function () use ($actor, $agendaId, $expectedVersion, $reason) {
            $agenda = WpitvAgenda::lockForUpdate()->findOrFail($agendaId);
            $fresh = $this->authorizeAgenda($actor, 'update', $agenda);
            $guard = app(AgendaLiveGuard::class);
            $stream = $guard->stream($agenda, true);
            if ($stream !== null) {
                $this->performMutation($fresh, $stream, $agenda, $expectedVersion, 'changeStatus', 'status_changed', $reason,
                    $this->agendaChange(['status' => 'cancelled'], $reason));
            } else {
                $this->assertUnlinkedVersion($expectedVersion);
                $agenda->status = 'cancelled';
                $guard->persist($agenda);
            }
        }, 3));
    }

    public function deleteAgenda(User $actor, int $agendaId): void
    {
        LiveStreamErrors::run(fn () => DB::transaction(function () use ($actor, $agendaId) {
            $agenda = WpitvAgenda::lockForUpdate()->findOrFail($agendaId);
            $fresh = $this->authorizeAgenda($actor, 'delete', $agenda);
            $guard = app(AgendaLiveGuard::class);
            if ($stream = $guard->stream($agenda, true)) {
                Gate::forUser($fresh)->authorize('view', $stream);
                throw new LiveStreamOperationException('LIVE_DEPENDENCY');
            }
            $guard->persist($agenda, true);
        }, 3));
    }

    private function agendaChange(array $input, ?string $reason): Closure
    {
        return function (LiveStream $stream, ?WpitvAgenda $agenda, User $fresh, CarbonImmutable $now) use ($input, $reason) {
            if ($agenda === null) {
                throw new LiveStreamOperationException('LIVE_STATE');
            }
            $previous = $stream->status;
            LiveStreamAgendaChanges::apply($stream, $agenda, $this->agendaInput($input, LiveStreamAgendaChanges::FIELDS), $now, $reason);
            if ($previous !== $stream->status) {
                Gate::forUser($fresh)->authorize('changeStatus', $stream);
                $stream->status_changed_by = $fresh->id;
            }
        };
    }

    public function transition(User $actor, int $streamId, int $expectedVersion, string $target, ?string $reason = null, array $reschedule = []): LiveStream
    {
        return $this->mutate($actor, $streamId, $expectedVersion, 'changeStatus', 'status_changed', $reason,
            function (LiveStream $stream, ?WpitvAgenda $agenda, User $fresh, CarbonImmutable $now) use ($target, $reason, $reschedule) {
                if ($stream->status === 'cancelled' && $target === 'scheduled') {
                    foreach ([...self::SCHEDULE_FIELDS, 'visibility'] as $field) {
                        if (! array_key_exists($field, $reschedule)) {
                            throw ValidationException::withMessages([$field => 'Jadwal ulang dan visibility harus dinyatakan lengkap; jam selesai boleh kosong.']);
                        }
                    }
                    if ($agenda !== null) {
                        $agenda->fill($this->agendaInput($reschedule, [...self::SCHEDULE_FIELDS, 'visibility']));
                        $this->validateAgenda($agenda);
                    } else {
                        $this->allowFields($reschedule, [...self::SCHEDULE_FIELDS, 'visibility']);
                        $this->applyManual($stream, $fresh, [
                            ...$reschedule,
                            'title' => $stream->manual_title,
                            'description' => $stream->manual_description,
                            'program_id' => $stream->manual_program_id,
                        ]);
                    }
                } elseif ($reschedule !== []) {
                    throw ValidationException::withMessages(['operation' => 'Jadwal ulang hanya dapat diberikan untuk siaran dibatalkan.']);
                }
                $previousStatus = $stream->status;
                LiveStreamState::applyTransition($stream, $agenda, $target, $now, $reason);
                if ($stream->status !== $previousStatus) {
                    $stream->status_changed_by = $fresh->id;
                }
            });
    }

    public function linkArchive(User $actor, int $streamId, int $expectedVersion, ?int $videoId): LiveStream
    {
        return $this->mutate($actor, $streamId, $expectedVersion, 'manageArchive', $videoId === null ? 'archive_unlinked' : 'archive_linked', null,
            function (LiveStream $stream, ?WpitvAgenda $agenda) use ($videoId) {
                if ($stream->status !== 'ended') {
                    throw new LiveStreamOperationException('LIVE_STATE');
                }
                if ($videoId !== null) {
                    // Lock order is always Agenda -> Live -> Video. No Video is created.
                    $video = Video::lockForUpdate()->findOrFail($videoId);
                    if (($agenda?->visibility ?? $stream->manual_visibility) !== 'public' || $video->youtube_id !== $stream->youtube_id) {
                        throw new LiveStreamOperationException('LIVE_STATE');
                    }
                }
                $stream->archive_video_id = $videoId;
            });
    }

    private function mutate(User $actor, int $streamId, int $expectedVersion, string $ability, string $eventType, ?string $reason, Closure $change): LiveStream
    {
        return LiveStreamErrors::run(function () use ($actor, $streamId, $expectedVersion, $ability, $eventType, $reason, $change) {
            $this->authorize($actor, $ability, new LiveStream);
            // This read only discovers the immutable Agenda key; it acquires no lock.
            $agendaId = LiveStream::useWritePdo()->findOrFail($streamId)->agenda_id;

            return DB::transaction(function () use ($actor, $streamId, $agendaId, $expectedVersion, $ability, $eventType, $reason, $change) {
                $agenda = $agendaId === null ? null : WpitvAgenda::lockForUpdate()->findOrFail($agendaId);
                $stream = LiveStream::where('agenda_id', $agendaId)->lockForUpdate()->findOrFail($streamId);

                return $this->performMutation($actor, $stream, $agenda, $expectedVersion, $ability, $eventType, $reason, $change);
            }, 3);
        });
    }

    private function performMutation(User $actor, LiveStream $stream, ?WpitvAgenda $agenda, ?int $expectedVersion, string $ability, string $eventType, ?string $reason, Closure $change): LiveStream
    {
        $fresh = $this->authorize($actor, $ability, $stream);
        if ($agenda !== null) {
            Gate::forUser($fresh)->authorize('update', $agenda);
        }
        if ($expectedVersion === null) {
            throw new LiveStreamOperationException('LIVE_STALE');
        }
        Validator::make(['version' => $expectedVersion, 'reason' => $reason], [
            'version' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        LiveStreamState::assertVersion($stream, $expectedVersion);
        LiveStreamState::assertConsistent($stream, $agenda);
        $before = $this->snapshot($stream, $agenda);
        $now = CarbonImmutable::now('UTC');
        $change($stream, $agenda, $fresh, $now);
        LiveStreamState::assertConsistent($stream, $agenda);
        if ($agenda !== null) {
            $this->validateAgenda($agenda);
        }
        if (! $stream->isDirty() && ! $agenda?->isDirty()) {
            return $stream->setRelation('agenda', $agenda);
        }
        if ($agenda?->isDirty()) {
            app(AgendaLiveGuard::class)->persist($agenda);
        }
        $stream->version++;
        $stream->updated_by = $fresh->id;
        $stream->saveCoordinated();
        if ($stream->status !== $before['live']['status']) {
            $eventType = 'status_changed';
        } elseif ($eventType === 'agenda_updated' && $agenda === null) {
            $eventType = 'manual_updated';
        }
        $this->audit($stream, $agenda, $fresh, $eventType, $reason === null ? null : trim($reason), $before, $now);

        return $stream->setRelation('agenda', $agenda);
    }

    private function assertUnlinkedVersion(?int $expectedVersion): void
    {
        if ($expectedVersion !== null) {
            throw new LiveStreamOperationException('LIVE_STALE');
        }
    }

    private function authorizeAgenda(User $actor, string $ability, WpitvAgenda $agenda): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->getKey());
        Gate::forUser($fresh)->authorize($ability, $agenda);

        return $fresh;
    }

    private function authorize(User $actor, string $ability, LiveStream|string $subject): User
    {
        $fresh = User::with('role')->useWritePdo()->findOrFail($actor->getKey());
        Gate::forUser($fresh)->authorize($ability, $subject);

        return $fresh;
    }

    private function youtubeId(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }
        $id = YouTubeVideoId::parse($input);
        if ($id === null) {
            throw ValidationException::withMessages(['youtube_input' => 'Tautan HTTPS atau ID YouTube tidak valid.']);
        }

        return $id;
    }

    private function allowFields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['operation' => 'Form memuat kolom yang tidak diizinkan.']);
        }
    }

    private function agendaInput(array $input, array $allowed): array
    {
        $this->allowFields($input, $allowed);
        foreach (['program_id', 'coverage_id', 'group_id', 'start_time', 'end_time', 'location', 'description'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] === '') {
                $input[$field] = null;
            }
        }
        // Validate the raw date before Eloquent can normalize an invalid day.
        Validator::make($input, [
            'agenda_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'nullable', 'regex:/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/'],
            'end_time' => ['sometimes', 'nullable', 'regex:/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/'],
        ])->validate();
        foreach (['start_time', 'end_time'] as $field) {
            if (isset($input[$field]) && strlen($input[$field]) === 5) {
                $input[$field] .= ':00';
            }
        }

        return $input;
    }

    private function validateAgenda(WpitvAgenda $agenda, bool $linked = true): void
    {
        $data = $agenda->only([...self::AGENDA_FIELDS, 'type']);
        $data['agenda_date'] = $agenda->agenda_date?->format('Y-m-d');
        Validator::make($data, [
            'title' => ['required', 'string', 'max:200'], 'type' => ['required', Rule::in($linked ? ['live'] : array_keys(WpitvAgenda::TYPES))],
            'program_id' => ['nullable', 'integer', 'exists:ms_program,id'],
            'coverage_id' => ['nullable', 'integer', 'exists:tr_perliputan,id'],
            'group_id' => ['nullable', 'integer', 'exists:tvclub_groups,id'],
            'agenda_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'string'], 'end_time' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(WpitvAgenda::STATUSES))],
            'visibility' => ['required', Rule::in(array_keys(WpitvAgenda::VISIBILITIES))],
        ])->validate();
        if ($agenda->type === 'live') {
            $start = LiveStreamSchedule::startsAt($agenda);
            LiveStreamSchedule::endsAt($agenda);
            if ($linked && $agenda->status === 'scheduled' && $agenda->visibility === 'public' && $start === null) {
                throw ValidationException::withMessages(['start_time' => 'Jam mulai wajib diisi untuk jadwal publik.']);
            }
        } elseif ($agenda->start_time && $agenda->end_time && $agenda->end_time < $agenda->start_time) {
            throw ValidationException::withMessages(['end_time' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai.']);
        }
    }

    private function applyManual(LiveStream $stream, User $actor, array $input): void
    {
        $this->allowFields($input, ['title', 'description', 'program_id', ...self::SCHEDULE_FIELDS, 'visibility']);
        if (is_string($input['title'] ?? null)) {
            $input['title'] = trim($input['title']);
        }
        if (is_string($input['description'] ?? null)) {
            $input['description'] = trim(strip_tags($input['description']));
        }
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'program_id' => ['nullable', 'integer', 'min:1', 'exists:ms_program,id'],
            'agenda_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'visibility' => ['required', Rule::in(['public', 'internal'])],
        ], [
            'title.required' => 'Judul siaran harus diisi.',
            'title.max' => 'Judul siaran maksimal 200 karakter.',
            'description.max' => 'Deskripsi terlalu panjang (maksimal 5.000 karakter).',
        ])->validate();
        $programId = $data['program_id'] ?? null;
        if ($programId !== null) {
            Gate::forUser($actor)->authorize('view', Program::findOrFail($programId));
        }
        [$start, $end] = LiveStreamSchedule::manual($data['agenda_date'], $data['start_time'], $data['end_time'] ?? null);
        $stream->manual_title = $data['title'];
        $description = ($data['description'] ?? '') !== '' ? $data['description'] : null;
        if (LiveStream::supportsManualDescription()) {
            $stream->manual_description = $description;
        } elseif ($description !== null) {
            throw ValidationException::withMessages(['description' => 'Kolom deskripsi manual belum tersedia pada database.']);
        }
        $stream->manual_program_id = $programId;
        $stream->manual_starts_at = $start;
        $stream->manual_ends_at = $end;
        $stream->manual_visibility = $data['visibility'];
    }

    private function snapshot(LiveStream $stream, ?WpitvAgenda $agenda): array
    {
        $agendaData = $agenda?->only([...self::AGENDA_FIELDS, 'type']);
        if ($agenda !== null) {
            $agendaData['agenda_date'] = $agenda->agenda_date?->format('Y-m-d');
        }
        $liveData = $stream->only(['id', 'agenda_id', 'youtube_id', 'status', 'archive_video_id', 'version']);
        $liveData['creation_mode'] = $agenda === null ? 'manual' : 'agenda';
        if ($agenda === null) {
            $liveData += $stream->only(array_filter(['manual_title', LiveStream::supportsManualDescription() ? 'manual_description' : null, 'manual_program_id', 'manual_visibility']));
            foreach (['manual_starts_at', 'manual_ends_at'] as $column) {
                $liveData[$column] = $stream->$column?->utc()->format('Y-m-d H:i:s.u');
            }
        }
        foreach (['live_marked_at', 'ended_marked_at', 'cancelled_marked_at', 'status_changed_at'] as $column) {
            $liveData[$column] = $stream->$column?->utc()->format('Y-m-d H:i:s.u');
        }

        return ['agenda' => $agendaData, 'live' => $liveData];
    }

    private function audit(LiveStream $stream, ?WpitvAgenda $agenda, User $actor, string $type, ?string $reason, ?array $before, CarbonImmutable $now): void
    {
        $event = new LiveStreamEvent;
        $event->live_stream_id = $stream->id;
        $event->event_type = $type;
        $event->actor_id = $actor->id;
        $event->actor_name = $actor->name;
        $event->reason = $reason;
        $event->before_values = $before;
        $event->after_values = $this->snapshot($stream, $agenda);
        $event->created_at = $now;
        if (! $event->save()) {
            throw new LiveStreamOperationException('LIVE_DATABASE');
        }
    }
}
