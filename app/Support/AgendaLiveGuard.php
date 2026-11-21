<?php

namespace App\Support;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\WpitvAgenda;
use Closure;
use Illuminate\Support\Facades\Schema;
use LogicException;
use WeakMap;

/** Scoped to one request; cache schema availability, never a Live row or version. */
class AgendaLiveGuard
{
    private array $availability = [];

    private WeakMap $coordinated;

    public function __construct()
    {
        $this->coordinated = new WeakMap;
    }

    public function available(?string $connection = null): bool
    {
        $key = $connection ?? '(default)';
        if (! array_key_exists($key, $this->availability)) {
            $this->availability[$key] = LiveStreamErrors::run(function () use ($connection) {
                $schema = $connection === null ? Schema::getFacadeRoot() : Schema::connection($connection);

                $streams = $schema->hasTable('live_streams');
                $events = $schema->hasTable('live_stream_events');
                if ($streams !== $events) {
                    throw new LiveStreamOperationException('LIVE_SCHEMA');
                }

                return $streams && $events;
            });
        }

        return $this->availability[$key];
    }

    public function stream(WpitvAgenda $agenda, bool $lock = false): ?LiveStream
    {
        if (! $this->available($agenda->getConnectionName())) {
            return null;
        }

        return LiveStreamErrors::run(function () use ($agenda, $lock) {
            $query = LiveStream::on($agenda->getConnectionName())->useWritePdo()->where('agenda_id', $agenda->getKey());

            return ($lock ? $query->lockForUpdate() : $query)->first();
        });
    }

    /** Covers save/update/delete and their quiet variants, including link-create races. */
    public function write(WpitvAgenda $agenda, string $operation, Closure $write): mixed
    {
        if (! $agenda->exists || isset($this->coordinated[$agenda]) || ! $this->available($agenda->getConnectionName())) {
            return $write();
        }

        return LiveStreamErrors::run(fn () => $agenda->getConnection()->transaction(function () use ($agenda, $operation, $write) {
            $locked = $agenda->newQuery()->useWritePdo()->lockForUpdate()->findOrFail($agenda->getRawOriginal($agenda->getKeyName()) ?? $agenda->getKey());
            if ($this->stream($locked, true) !== null) {
                throw new LiveStreamOperationException($operation === 'delete' ? 'LIVE_DEPENDENCY' : 'LIVE_COORDINATION');
            }

            return $write();
        }, 3));
    }

    /** Only the lifecycle service may persist an Agenda it has already locked/validated. */
    public function persist(WpitvAgenda $agenda, bool $delete = false): mixed
    {
        if ($agenda->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Coordinated Agenda writes require an active transaction.');
        }
        $this->coordinated[$agenda] = true;
        try {
            return $delete ? $agenda->delete() : $agenda->save();
        } finally {
            unset($this->coordinated[$agenda]);
        }
    }
}
