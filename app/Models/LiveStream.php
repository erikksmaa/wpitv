<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use App\Support\LiveStreamSchedule;
use App\Support\YouTubeVideoId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LiveStream extends Model
{
    use HasUtcTimestamps;

    public const STATUSES = ['scheduled', 'live', 'ended', 'cancelled'];

    // Queries using these expressions join wpitv_agendas as schedule.
    public const START_WIB = "COALESCE(TIMESTAMPADD(HOUR, 7, live_streams.manual_starts_at), TIMESTAMP(schedule.agenda_date, COALESCE(schedule.start_time, '00:00:00')))";

    public const TITLE_SQL = 'COALESCE(live_streams.manual_title, schedule.title)';

    public const PROGRAM_SQL = 'COALESCE(live_streams.manual_program_id, schedule.program_id)';

    public const VISIBILITY_SQL = 'COALESCE(live_streams.manual_visibility, schedule.visibility)';

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    private bool $coordinatedWrite = false;

    public function save(array $options = [])
    {
        if ($this->exists && $this->isDirty() && ! $this->coordinatedWrite) {
            throw new LogicException('Existing Live writes require the coordinated lifecycle.');
        }

        return parent::save($options);
    }

    /** Trusted persistence boundary, after lifecycle locks/version/authorization. */
    public function saveCoordinated(): bool
    {
        if ($this->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Coordinated Live writes require an active transaction.');
        }
        $this->coordinatedWrite = true;
        try {
            return $this->save();
        } finally {
            $this->coordinatedWrite = false;
        }
    }

    public function delete()
    {
        throw new LogicException('Live streaming history cannot be deleted.');
    }

    protected function casts(): array
    {
        return [
            'agenda_id' => 'integer', 'archive_video_id' => 'integer', 'version' => 'integer',
            'manual_program_id' => 'integer', 'manual_starts_at' => UtcDateTime::class, 'manual_ends_at' => UtcDateTime::class,
            'live_marked_at' => UtcDateTime::class, 'ended_marked_at' => UtcDateTime::class,
            'cancelled_marked_at' => UtcDateTime::class, 'status_changed_at' => UtcDateTime::class,
            'created_at' => UtcDateTime::class, 'updated_at' => UtcDateTime::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (LiveStream $stream) {
            if ($stream->isDirty(['agenda_id', 'created_by', 'created_at'])) {
                throw new LogicException('Live streaming Agenda and creation history are immutable.');
            }
        });
        static::deleting(function () {
            throw new LogicException('Live streaming history cannot be deleted.');
        });
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(WpitvAgenda::class);
    }

    public function archiveVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'archive_video_id');
    }

    public function manualProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'manual_program_id');
    }

    public function isManual(): bool
    {
        return $this->agenda_id === null;
    }

    public function getDisplayTitleAttribute(): ?string
    {
        return $this->isManual() ? $this->manual_title : $this->agenda?->title;
    }

    public function getDisplayProgramAttribute(): ?Program
    {
        return $this->isManual() ? $this->manualProgram : $this->agenda?->program;
    }

    public function getVisibilityAttribute(): ?string
    {
        return $this->isManual() ? $this->manual_visibility : $this->agenda?->visibility;
    }

    public function getScheduleDateAttribute(): ?string
    {
        return $this->isManual()
            ? $this->manual_starts_at?->setTimezone('Asia/Jakarta')->format('d/m/Y')
            : $this->agenda?->agenda_date?->format('d/m/Y');
    }

    public function getTimeLabelAttribute(): ?string
    {
        if (! $this->isManual()) {
            return $this->agenda?->time_label;
        }
        $start = $this->manual_starts_at?->setTimezone('Asia/Jakarta');
        $end = $this->manual_ends_at?->setTimezone('Asia/Jakarta');

        return $start ? $start->format('H:i').($end ? ' – '.$end->format('H:i').($end->isSameDay($start) ? '' : ' (+1 hari)') : '').' WIB' : null;
    }

    public function events(): HasMany
    {
        return $this->hasMany(LiveStreamEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function getStatusSourceAttribute(): string
    {
        return 'manual';
    }

    public function getYoutubeStatusAttribute(): string
    {
        return 'unknown';
    }

    public function getWatchUrlAttribute(): ?string
    {
        return YouTubeVideoId::watchUrl($this->youtube_id);
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return YouTubeVideoId::embedUrl($this->youtube_id);
    }

    public function getScheduledStartsAtAttribute(): ?CarbonImmutable
    {
        return $this->isManual() ? $this->manual_starts_at : ($this->agenda ? LiveStreamSchedule::startsAt($this->agenda) : null);
    }

    public function getScheduledEndsAtAttribute(): ?CarbonImmutable
    {
        return $this->isManual() ? $this->manual_ends_at : ($this->agenda ? LiveStreamSchedule::endsAt($this->agenda) : null);
    }

    public function hasValidArchive(): bool
    {
        return $this->status === 'ended' && $this->visibility === 'public'
            && ($this->isManual() || ($this->agenda?->type === 'live' && $this->agenda?->status === 'completed'))
            && YouTubeVideoId::isValid($this->youtube_id)
            && $this->archive_video_id !== null && $this->archiveVideo?->getKey() !== null
            && (int) $this->archiveVideo->getKey() === $this->archive_video_id
            && $this->archiveVideo?->youtube_id === $this->youtube_id;
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where(fn (Builder $sources) => $sources->where(fn (Builder $manual) => $manual
            ->whereNull('live_streams.agenda_id')->where('live_streams.manual_visibility', 'public')->whereNotNull('live_streams.manual_starts_at'))
            ->orWhere(fn (Builder $linked) => $linked->agendaPublicVisible()));
    }

    public function scopeAgendaPublicVisible(Builder $query): Builder
    {
        return $query->whereHas('agenda', function (Builder $agenda) {
            $agenda->where('type', 'live')->where('visibility', 'public')->where(function (Builder $states) {
                $states->where(function (Builder $scheduled) {
                    $scheduled->whereIn('live_streams.status', ['scheduled', 'live'])->where('wpitv_agendas.status', 'scheduled');
                })->orWhere(function (Builder $ended) {
                    $ended->where('live_streams.status', 'ended')->where('wpitv_agendas.status', 'completed');
                })->orWhere(function (Builder $cancelled) {
                    $cancelled->where('live_streams.status', 'cancelled')->where('wpitv_agendas.status', 'cancelled');
                });
            });
        });
    }
}
