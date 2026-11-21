<?php

namespace App\Services;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityAttachment;
use App\Models\MonthlyActivityItem;
use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Support\MonthlyActivityRules as Rules;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

class MonthlyActivityLifecycle
{
    /** @var list<int>|null Reports created by this instance in the caller's transaction. */
    private ?array $newReportIds = null;

    public function forNewReportsInTransaction(): self
    {
        if (DB::transactionLevel() < 1) {
            throw new MonthlyActivityException('MONTHLY_FAILURE');
        }
        $service = clone $this;
        $service->newReportIds = [];

        return $service;
    }

    public function createDraft(User $actor, string $division, string $month): MonthlyActivityReport
    {
        $division = Rules::division($division);
        $period = Rules::period($month)->toDateString();

        return $this->transaction(function () use ($actor, $division, $period) {
            $actor = $this->freshActor($actor);
            Gate::forUser($actor)->authorize('create', [MonthlyActivityReport::class, $division]);
            $report = new MonthlyActivityReport;
            $report->forceFill(['division' => $division, 'period_start' => $period, 'status' => 'draft', 'version' => 1, 'created_by' => $actor->id]);
            $this->requireSaved($report->saveCoordinated());
            $this->audit($report, $actor, 'created', null);
            if ($this->newReportIds !== null) {
                $this->newReportIds[] = $report->id;
            }

            return $report;
        });
    }

    public function updateDraft(User $actor, int $id, int $expectedVersion, ?string $nilReason): MonthlyActivityReport
    {
        return $this->mutate($actor, $id, $expectedVersion, 'update', function ($report, $actor) use ($nilReason) {
            if (! Rules::editable($report->status)) {
                throw new MonthlyActivityException('MONTHLY_STATE');
            }
            $report->nil_reason = Rules::reason($nilReason, 'nil_reason');
            if ($report->isDirty('nil_reason')) {
                $this->advance($report);
                $this->audit($report, $actor, 'updated', $report->status, changes: ['fields' => ['nil_reason']]);
            }
        });
    }

    public function submit(User $actor, int $id, int $expectedVersion): MonthlyActivityReport
    {
        return $this->mutate($actor, $id, $expectedVersion, 'submit', function ($report, $actor) {
            $from = $report->status;
            Rules::transition($from, 'submitted');
            $snapshot = $this->snapshot($report);
            $version = $report->versions()->make();
            $this->requireSaved($version->forceFill([
                'revision_number' => (int) $report->versions()->max('revision_number') + 1,
                'schema_version' => 1, 'snapshot' => $snapshot, 'snapshot_hash' => Rules::snapshotHash($snapshot),
                'submitted_by' => $actor->id, 'submitted_at' => $report->freshTimestamp(),
            ])->save());
            $report->status = 'submitted';
            $this->advance($report);
            $this->audit($report, $actor, 'submitted', $from, $version->id);
        });
    }

    public function saveItem(User $actor, int $id, int $expectedVersion, ?int $itemId, array $input, ?string $sourceType = null, ?int $sourceId = null, ?string $sourceHash = null, bool $confirmDuplicate = false): MonthlyActivityReport
    {
        return $this->editItems($actor, $id, $expectedVersion, function ($report, $actor) use ($itemId, $input, $sourceType, $sourceId, $sourceHash, $confirmDuplicate) {
            $fields = Rules::item($input, $report->period_start->format('Y-m'));
            $item = $itemId === null ? $report->items()->make() : $report->items()->findOrFail($itemId);

            if (! $item->exists) {
                if ($report->items()->count() >= Rules::ITEM_LIMIT) {
                    throw ValidationException::withMessages(['activity' => 'Maksimal 500 kegiatan per laporan.']);
                }
                if ($sourceType !== null) {
                    $context = app(MonthlyActivityCandidates::class)->resolve($actor, $report, $sourceType, $sourceId ?? 0);
                    if ($sourceHash !== Rules::snapshotHash($context)) {
                        throw ValidationException::withMessages(['activity' => 'Kandidat telah berubah. Pilih ulang kandidat sebelum menyimpan.']);
                    }
                    if ($report->items()->where('source_type', $sourceType)->where('source_id', $sourceId)->exists()) {
                        throw ValidationException::withMessages(['activity' => 'Sumber ini sudah dipilih dalam laporan.']);
                    }
                    $item->forceFill(['source_type' => $sourceType, 'source_id' => $sourceId, 'source_context' => $context]);
                }
                $item->position = (int) $report->items()->max('position') + 1;
            }

            $item->forceFill($fields);
            if ($item->source_type === null && $item->isDirty(['activity_date', 'title']) && ! $confirmDuplicate && $report->items()
                ->where('activity_date', $fields['activity_date'])->where('title', $fields['title'])
                ->when($item->exists, fn ($query) => $query->whereKeyNot($item->id))->exists()) {
                throw ValidationException::withMessages(['confirmDuplicate' => 'Judul dan tanggal sama dengan kegiatan lain. Konfirmasi jika kegiatan ini berbeda.']);
            }

            if (! $item->isDirty()) {
                return null;
            }
            $changes = ['operation' => $item->exists ? 'item_updated' : 'item_added', 'fields' => array_keys($item->getDirty())];
            $this->requireSaved($item->save());

            return [...$changes, 'item_id' => $item->id];
        });
    }

    public function deleteItem(User $actor, int $id, int $expectedVersion, int $itemId): MonthlyActivityReport
    {
        return $this->editItems($actor, $id, $expectedVersion, function ($report) use ($itemId) {
            $item = $report->items()->findOrFail($itemId);
            if ($item->attachments()->exists()) {
                throw ValidationException::withMessages(['activity' => 'Kegiatan dengan dokumen pendukung tidak dapat dihapus.']);
            }
            $this->requireSaved($item->deleteDraft());

            return ['operation' => 'item_deleted', 'item_id' => $itemId];
        });
    }

    public function uploadAttachment(User $actor, MonthlyActivityReport $report, int $expectedVersion, ?int $itemId, UploadedFile $upload): MonthlyActivityReport
    {
        if ($this->newReportIds !== null) {
            throw new MonthlyActivityException('MONTHLY_FAILURE');
        }
        Gate::forUser($actor)->authorize('update', $report);
        Rules::version($report->version, $expectedVersion);
        if (! Rules::editable($report->status)) {
            throw new MonthlyActivityException('MONTHLY_STATE');
        }

        $files = app(MonthlyActivityFiles::class);
        $metadata = $files->stage($upload, $report->id);
        try {
            return $this->editItems($actor, $report->id, $expectedVersion, function ($report, $actor) use ($itemId, $metadata) {
                if ($report->attachments()->attached()->count() >= Rules::ATTACHMENT_LIMIT) {
                    throw ValidationException::withMessages(['attachment' => 'Maksimal 100 lampiran aktif per laporan.']);
                }
                $item = $itemId === null ? null : $report->items()->findOrFail($itemId);
                $file = $report->attachments()->make();
                $this->requireSaved($file->forceFill([...$metadata, 'item_id' => $item?->id, 'uploaded_by' => $actor->id])->save());

                return ['operation' => 'attachment_added', 'attachment_id' => $file->id, 'item_id' => $item?->id];
            });
        } catch (Throwable $exception) {
            // Keep the file if a lost connection made the commit outcome unknown.
            try {
                if (! MonthlyActivityAttachment::where('disk', 'local')->where('storage_key', $metadata['storage_key'])->exists()) {
                    $files->discard($metadata['storage_key']);
                }
            } catch (Throwable) {
                logger()->warning('MONTHLY_FILE_CLEANUP_UNCERTAIN');
            }
            throw $exception;
        }
    }

    public function detachAttachment(User $actor, int $id, int $expectedVersion, int $attachmentId): MonthlyActivityReport
    {
        if ($this->newReportIds !== null) {
            throw new MonthlyActivityException('MONTHLY_FAILURE');
        }
        $discard = null;
        $report = $this->editItems($actor, $id, $expectedVersion, function ($report) use ($attachmentId, &$discard) {
            $discard = null;
            $file = $report->attachments()->attached()->findOrFail($attachmentId);
            $itemId = $file->item_id;
            $retained = $report->versions()->whereJsonContains('snapshot->attachments', ['id' => $file->id])->exists();
            if ($retained) {
                // The original item association remains in the immutable snapshot.
                $this->requireSaved($file->forceFill(['item_id' => null])->save());
            } else {
                $this->requireSaved($report->attachments()->whereKey($file->id)->delete() === 1);
                if ($file->disk === 'local' && str_starts_with($file->storage_key, 'monthly-activity/'.$report->id.'/')) {
                    $discard = $file->storage_key;
                }
            }

            return ['operation' => 'attachment_detached', 'attachment_id' => $file->id, 'item_id' => $itemId, 'retained' => $retained];
        });
        if ($discard !== null) {
            app(MonthlyActivityFiles::class)->discard($discard);
        }

        return $report;
    }

    public function moveItem(User $actor, int $id, int $expectedVersion, int $itemId, int $offset): MonthlyActivityReport
    {
        return $this->editItems($actor, $id, $expectedVersion, function ($report) use ($itemId, $offset) {
            $items = $report->items()->orderBy('position')->orderBy('id')->limit(Rules::ITEM_LIMIT + 1)->get();
            $index = $items->search(fn (MonthlyActivityItem $item) => $item->id === $itemId);
            if ($index === false || ! in_array($offset, [-1, 1], true) || $items->count() > Rules::ITEM_LIMIT) {
                throw ValidationException::withMessages(['activity' => 'Urutan kegiatan tidak valid.']);
            }
            $target = $index + $offset;
            if (! $items->has($target)) {
                return null;
            }
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            foreach ($items as $position => $item) {
                $item->position = $position + 1;
                if ($item->isDirty('position')) {
                    $this->requireSaved($item->save());
                }
            }

            return ['operation' => 'item_moved', 'item_id' => $itemId, 'position' => $target + 1];
        });
    }

    private function editItems(User $actor, int $id, int $expectedVersion, Closure $change): MonthlyActivityReport
    {
        return $this->mutate($actor, $id, $expectedVersion, 'update', function ($report, $actor) use ($change) {
            if (! Rules::editable($report->status)) {
                throw new MonthlyActivityException('MONTHLY_STATE');
            }
            $changes = $change($report, $actor);
            if ($changes !== null) {
                $this->advance($report);
                $this->audit($report, $actor, 'updated', $report->status, changes: $changes);
            }
        });
    }

    public function requestRevision(User $actor, int $id, int $expectedVersion, string $reason): MonthlyActivityReport
    {
        return $this->review($actor, $id, $expectedVersion, 'revision', Rules::reason($reason, 'reason', true));
    }

    public function approve(User $actor, int $id, int $expectedVersion): MonthlyActivityReport
    {
        return $this->review($actor, $id, $expectedVersion, 'approved');
    }

    private function review(User $actor, int $id, int $expectedVersion, string $target, ?string $reason = null): MonthlyActivityReport
    {
        return $this->mutate($actor, $id, $expectedVersion, 'review', function ($report, $actor) use ($target, $reason) {
            $from = $report->status;
            Rules::transition($from, $target);
            $version = $report->versions()->orderByDesc('revision_number')->firstOrFail();
            if ((int) $version->submitted_by === (int) $actor->id) {
                throw new AuthorizationException;
            }
            $report->status = $target;
            $this->advance($report);
            $this->audit($report, $actor, $target === 'approved' ? 'approved' : 'revision_requested', $from, $version->id, $reason);
        });
    }

    private function mutate(User $actor, int $id, int $expectedVersion, string $ability, Closure $change): MonthlyActivityReport
    {
        if ($this->newReportIds !== null && ! in_array($id, $this->newReportIds, true)) {
            throw new MonthlyActivityException('MONTHLY_FAILURE');
        }

        return $this->transaction(function () use ($actor, $id, $expectedVersion, $ability, $change) {
            // Lock before the first consistent read on MySQL REPEATABLE READ.
            $report = MonthlyActivityReport::query()->lockForUpdate()->findOrFail($id);
            $actor = $this->freshActor($actor);
            Gate::forUser($actor)->authorize($ability, $report);
            Rules::version($report->version, $expectedVersion);
            $change($report, $actor);

            return $report;
        });
    }

    private function snapshot(MonthlyActivityReport $report): array
    {
        // All future child writers must lock this report before writing children.
        $items = $report->items()->orderBy('position')->orderBy('id')->limit(Rules::ITEM_LIMIT + 1)->get();
        $files = $report->attachments()->attached()->orderBy('id')->limit(Rules::ATTACHMENT_LIMIT + 1)->get();
        if ($items->count() > Rules::ITEM_LIMIT || $files->count() > Rules::ATTACHMENT_LIMIT || $files->contains(fn ($file) => $file->status !== 'ready')) {
            throw ValidationException::withMessages(['report' => 'Periksa batas kegiatan dan kesiapan dokumen.']);
        }
        $nilReason = Rules::reason($report->nil_reason, 'nil_reason', $items->isEmpty());
        if ($items->isNotEmpty() && $nilReason !== null) {
            throw ValidationException::withMessages(['nil_reason' => 'Hapus alasan nihil sebelum mengirim laporan berisi kegiatan.']);
        }
        $period = $report->period_start->format('Y-m');
        $activities = $items->map(function ($item) use ($period) {
            if ($item->activity_date->format('Y-m') !== $period) {
                throw ValidationException::withMessages(['items' => 'Tanggal kegiatan harus berada pada periode laporan.']);
            }
            $fields = array_keys(Rules::ITEM_FIELDS);
            foreach ($fields as $field) {
                if (trim($item->$field) === '' || mb_strlen($item->$field) > Rules::itemLength($field)) {
                    throw ValidationException::withMessages(['items' => 'Lengkapi uraian kegiatan dalam batas panjang yang diizinkan.']);
                }
            }

            return [...$item->only(['id', ...$fields, 'source_type', 'source_id']), 'activity_date' => $item->activity_date->toDateString()];
        })->all();

        return [
            'division' => $report->division, 'period' => $period, 'nil_reason' => $nilReason, 'items' => $activities,
            'attachments' => $files->map(fn ($file) => $file->only(['id', 'item_id', 'display_name', 'mime_type', 'size_bytes', 'sha256']))->all(),
        ];
    }

    private function freshActor(User $actor): User
    {
        return User::query()->with('role')->findOrFail($actor->getKey());
    }

    private function advance(MonthlyActivityReport $report): void
    {
        $report->version++;
        $this->requireSaved($report->saveCoordinated());
    }

    private function audit(MonthlyActivityReport $report, User $actor, string $action, ?string $from, ?int $versionId = null, ?string $reason = null, ?array $changes = null): void
    {
        $this->requireSaved($report->events()->make()->forceFill([
            'report_version_id' => $versionId, 'action' => $action, 'from_status' => $from,
            'to_status' => $report->status, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
            'reason' => $reason, 'changes' => $changes, 'created_at' => $report->freshTimestamp(),
        ])->save());
    }

    private function requireSaved(bool $saved): void
    {
        if (! $saved) {
            throw new MonthlyActivityException('MONTHLY_FAILURE');
        }
    }

    private function transaction(Closure $operation): mixed
    {
        try {
            // Keep a caller's existing consistent-read snapshot out of this lifecycle.
            $nested = $this->newReportIds !== null;
            if ($nested ? DB::transactionLevel() < 1 : DB::transactionLevel() !== 0) {
                throw new MonthlyActivityException('MONTHLY_FAILURE');
            }

            return DB::transaction($operation, $nested ? 1 : 3);
        } catch (MonthlyActivityException|AuthorizationException|ModelNotFoundException|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $code = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            throw new MonthlyActivityException(match ($code) {
                1062 => 'MONTHLY_DUPLICATE',
                1205, 1213 => 'MONTHLY_CONCURRENCY',
                default => 'MONTHLY_FAILURE',
            });
        }
    }
}
