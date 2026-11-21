<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LiveStreamEvent extends Model
{
    use HasUtcTimestamps;

    public const UPDATED_AT = null;

    public const TYPES = ['created', 'agenda_updated', 'manual_updated', 'youtube_updated', 'status_changed', 'archive_linked', 'archive_unlinked'];

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function save(array $options = [])
    {
        if ($this->exists && $this->isDirty()) {
            throw new LogicException('Live streaming audit events are append-only.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new LogicException('Live streaming audit events cannot be deleted.');
    }

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'created_at' => UtcDateTime::class];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Live streaming audit events are append-only.');
        });
        static::deleting(function () {
            throw new LogicException('Live streaming audit events cannot be deleted.');
        });
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
