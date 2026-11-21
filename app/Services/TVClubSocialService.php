<?php

namespace App\Services;

use App\Exceptions\TVClubSocialException;
use App\Models\Concerns\TVClubSocialRecord;
use App\Models\Program;
use App\Models\TVClubMember;
use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialAccountObservation;
use App\Models\TVClubSocialEvent;
use App\Models\TVClubSocialPost;
use App\Models\TVClubSocialPostObservation;
use App\Models\TVClubSocialReportVersion;
use App\Models\User;
use App\Support\MonthlyActivityRules;
use App\Support\TVClubSocialRules as Rules;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

class TVClubSocialService
{
    public function createAccount(User $actor, string $platform, string $key, string $handle, string $name, ?string $url = null): TVClubSocialAccount
    {
        return $this->transaction($actor, 'manageAccounts', function ($actor) use ($platform, $key, $handle, $name, $url) {
            $account = new TVClubSocialAccount;
            $this->save($account, [
                'platform' => Rules::platform($platform),
                'account_key' => Rules::label($key, 'account_key', 100),
                ...$this->accountFields($platform, $handle, $name, $url),
                'lock_version' => 1,
            ]);
            $this->audit($actor, 'account', $account->id, 'created');

            return $account;
        });
    }

    public function updateAccount(User $actor, int $id, int $expectedVersion, string $handle, string $name, ?string $url = null): TVClubSocialAccount
    {
        return $this->transaction($actor, 'manageAccounts', function ($actor) use ($id, $expectedVersion, $handle, $name, $url) {
            $account = $this->lockAccount($id);
            $this->change($actor, $account, 'account', $expectedVersion, $this->accountFields($account->platform, $handle, $name, $url));

            return $account;
        });
    }

    public function archiveAccount(User $actor, int $id, int $expectedVersion): TVClubSocialAccount
    {
        return $this->transaction($actor, 'manageAccounts', function ($actor) use ($id, $expectedVersion) {
            $account = $this->lockAccount($id);
            $this->active($account);
            $this->change($actor, $account, 'account', $expectedVersion, ['archived_at' => $account->freshTimestamp()], 'archived');

            return $account;
        });
    }

    public function savePost(User $actor, int $accountId, ?int $postId, int $expectedVersion, array $input, DateTimeInterface $publishedAt): TVClubSocialPost
    {
        return $this->transaction($actor, 'manageContent', function ($actor) use ($accountId, $postId, $expectedVersion, $input, $publishedAt) {
            $account = $this->lockAccount($accountId);
            $this->active($account);
            $post = $postId === null ? $account->posts()->make() : $account->posts()->lockForUpdate()->findOrFail($postId);
            $this->active($post);
            $published = CarbonImmutable::instance($publishedAt)->utc();
            if ($published->year < 1000 || $published->isFuture()) {
                Rules::invalid('published_at', 'Waktu publikasi berada di luar rentang yang didukung.');
            }
            $link = Rules::postUrl($account->platform, $input['url'] ?? '');
            $fields = [
                'url' => $link['url'],
                'platform_content_key' => $link['key'],
                'url_hash' => hash('sha256', $link['url']),
                'published_at' => $published,
                'title' => Rules::label($input['title'] ?? null, 'title', 200),
                'description' => Rules::label($input['description'] ?? null, 'description', 5000, false),
                'content_type' => Rules::contentType($account->platform, $input['content_type'] ?? ''),
                'responsible_label' => Rules::label($input['responsible_label'] ?? null, 'responsible_label', 160),
                'member_id' => $this->reference($actor, TVClubMember::class, $input['member_id'] ?? null, 'member_id'),
                'program_id' => $this->reference($actor, Program::class, $input['program_id'] ?? null, 'program_id'),
            ];
            if ($post->exists) {
                Rules::version($post->lock_version, $expectedVersion);
                if (($post->platform_content_key !== $link['key'] && $post->observations()->exists())
                    || $post->observations()->where('observed_at', '<', $published->format('Y-m-d H:i:s.u'))->exists()) {
                    throw new TVClubSocialException('SOCIAL_STATE');
                }
                $this->change($actor, $post, 'post', $expectedVersion, $fields);
            } else {
                Rules::version(0, $expectedVersion);
                $this->save($post, [...$fields, 'lock_version' => 1]);
                $this->audit($actor, 'post', $post->id, 'created');
            }

            return $post;
        });
    }

    public function archivePost(User $actor, int $accountId, int $postId, int $expectedVersion): TVClubSocialPost
    {
        return $this->transaction($actor, 'manageContent', function ($actor) use ($accountId, $postId, $expectedVersion) {
            $account = $this->lockAccount($accountId);
            $post = $account->posts()->lockForUpdate()->findOrFail($postId);
            $this->active($post);
            $this->change($actor, $post, 'post', $expectedVersion, ['archived_at' => $post->freshTimestamp()], 'archived');

            return $post;
        });
    }

    public function observePost(User $actor, int $accountId, int $postId, DateTimeInterface $observedAt, array $metrics, ?string $note = null, ?int $correctsId = null, ?string $reason = null): TVClubSocialPostObservation
    {
        return $this->transaction($actor, 'manageContent', function ($actor) use ($accountId, $postId, $observedAt, $metrics, $note, $correctsId, $reason) {
            $account = $this->lockAccount($accountId);
            $this->active($account);
            $post = $account->posts()->lockForUpdate()->findOrFail($postId);
            $this->active($post);

            return $this->observe($actor, $post, 'post_observation', $observedAt, Rules::metrics($metrics, Rules::POST_METRICS), $note, $correctsId, $reason);
        });
    }

    public function observeAccount(User $actor, int $accountId, DateTimeInterface $observedAt, array $metrics, ?string $note = null, ?int $correctsId = null, ?string $reason = null): TVClubSocialAccountObservation
    {
        return $this->transaction($actor, 'manageContent', function ($actor) use ($accountId, $observedAt, $metrics, $note, $correctsId, $reason) {
            $account = $this->lockAccount($accountId);
            $this->active($account);

            return $this->observe($actor, $account, 'account_observation', $observedAt, Rules::metrics($metrics, Rules::ACCOUNT_METRICS), $note, $correctsId, $reason);
        });
    }

    public function publish(User $actor, string $month, int $expectedRevision, ?string $reason = null): TVClubSocialReportVersion
    {
        return $this->transaction($actor, 'publish', function ($actor) use ($month, $expectedRevision, $reason) {
            $start = Rules::period($month);
            $end = $start->addMonth();
            if ($end->isFuture()) {
                throw new TVClubSocialException('SOCIAL_STATE');
            }
            $current = (int) TVClubSocialReportVersion::where('period_start', $start->toDateString())->max('revision_number');
            [$revision, $reason] = Rules::revision($current, $expectedRevision, $reason);
            $snapshot = $this->snapshot($start, $end);
            $version = new TVClubSocialReportVersion;
            $this->save($version, [
                'period_start' => $start->toDateString(), 'revision_number' => $revision, 'schema_version' => 1,
                'snapshot' => $snapshot, 'snapshot_hash' => MonthlyActivityRules::snapshotHash($snapshot),
                'published_by' => $actor->id, 'published_at' => $version->freshTimestamp(), 'reason' => $reason,
            ]);
            $this->audit($actor, 'report', $version->id, 'published', ['revision_number' => $revision]);

            return $version;
        });
    }

    public function recap(User $actor, string $month): array
    {
        return $this->transaction($actor, 'viewAny', function () use ($month) {
            $start = Rules::period($month);

            return $this->snapshot($start, $start->addMonth());
        });
    }

    private function snapshot(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $cutoff = $end->utc()->format('Y-m-d H:i:s.u');
        $posts = TVClubSocialPost::query()->where('published_at', '>=', $start->utc()->format('Y-m-d H:i:s.u'))
            ->where('published_at', '<', $cutoff)->orderBy('id')->limit(5001);
        $accounts = TVClubSocialAccount::query()->orderBy('id')->limit(501);
        $posts = $this->withObservation($posts, TVClubSocialPostObservation::class, 'post_id', $cutoff);
        $accounts = $this->withObservation($accounts, TVClubSocialAccountObservation::class, 'account_id', $cutoff);
        if ($posts->count() > 5000 || $accounts->count() > 500) {
            Rules::invalid('period', 'Periode melampaui batas snapshot yang didukung.');
        }

        return [
            'period' => $start->format('Y-m'),
            'posts' => $posts->map(fn ($post) => [
                ...$post->only(['id', 'account_id', 'title', 'description', 'url', 'content_type', 'responsible_label']),
                'published_at' => $post->published_at->toISOString(),
                'archived_at' => $post->archived_at?->toISOString(),
                'observation' => $this->projectObservation($post->getRelation('closingObservation'), Rules::POST_METRICS),
            ])->all(),
            'accounts' => $accounts->map(fn ($account) => [
                ...$account->only(['id', 'platform', 'display_name', 'handle']),
                'archived_at' => $account->archived_at?->toISOString(),
                'observation' => $this->projectObservation($account->getRelation('closingObservation'), Rules::ACCOUNT_METRICS),
            ])->all(),
        ];
    }

    private function withObservation(Builder $query, string $model, string $key, string $cutoff): Collection
    {
        $table = $query->getModel()->getTable();
        $rows = $query->select($table.'.*')->addSelect([
            'observation_id' => $model::select('id')->whereColumn($key, $table.'.id')
                ->where('observed_at', '<', $cutoff)->orderByDesc('observed_at')->orderByDesc('id')->limit(1),
        ])->get();
        $ids = $rows->pluck('observation_id')->filter()->all();
        $observations = $ids === [] ? collect() : $model::whereKey($ids)->get()->keyBy('id');

        return $rows->each(fn ($row) => $row->setRelation('closingObservation', $observations->get($row->observation_id)));
    }

    private function projectObservation(?TVClubSocialRecord $record, array $fields): ?array
    {
        return $record === null ? null : [
            ...$record->only(['id', 'source_note']),
            'observed_at' => $record->observed_at->toISOString(),
            'metrics' => Rules::metrics($record->only($fields), $fields),
        ];
    }

    private function observe(User $actor, TVClubSocialAccount|TVClubSocialPost $owner, string $target, DateTimeInterface $observedAt, array $metrics, ?string $note, ?int $correctsId, ?string $reason): TVClubSocialRecord
    {
        $reason = Rules::correction($correctsId, $reason);
        $time = CarbonImmutable::instance($observedAt)->utc();
        if ($correctsId !== null) {
            $previous = $owner->observations()->findOrFail($correctsId);
            if (! $previous->observed_at->equalTo($time) || $owner->observations()->where('corrects_id', $correctsId)->exists()) {
                throw new TVClubSocialException('SOCIAL_STATE');
            }
        } elseif ($owner->observations()->where('observed_at', $time->format('Y-m-d H:i:s.u'))->exists()) {
            throw new TVClubSocialException('SOCIAL_DUPLICATE');
        }
        if ($time->year < 1000 || $time->isFuture() || ($owner instanceof TVClubSocialPost && $time->lt($owner->published_at))) {
            Rules::invalid('observed_at', 'Waktu observasi tidak valid.');
        }
        $record = $owner->observations()->make();
        $this->save($record, [
            ...$metrics, 'observed_at' => $time, 'created_at' => $record->freshTimestamp(),
            'actor_id' => $actor->id, 'source_note' => Rules::label($note, 'source_note', 500, false),
            'corrects_id' => $correctsId, 'reason' => $reason,
        ]);
        $this->audit($actor, $target, $record->id, $correctsId === null ? 'observed' : 'corrected', ['corrects_id' => $correctsId]);

        return $record;
    }

    private function accountFields(string $platform, string $handle, string $name, ?string $url): array
    {
        return [
            'handle' => Rules::label($handle, 'handle', 100),
            'display_name' => Rules::label($name, 'display_name', 160),
            'profile_url' => $url === null || trim($url) === '' ? null : Rules::profileUrl($platform, $url),
        ];
    }

    private function lockAccount(int $id): TVClubSocialAccount
    {
        return TVClubSocialAccount::query()->lockForUpdate()->findOrFail($id);
    }

    private function active(TVClubSocialRecord $model): void
    {
        if ($model->archived_at !== null) {
            throw new TVClubSocialException('SOCIAL_STATE');
        }
    }

    private function reference(User $actor, string $model, mixed $id, string $field): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        if ((! is_int($id) && ! is_string($id)) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]) === false) {
            Rules::invalid($field, 'Referensi tidak valid.');
        }
        $record = $model::findOrFail((int) $id);
        Gate::forUser($actor)->authorize('view', $record);

        return (int) $id;
    }

    private function change(User $actor, TVClubSocialRecord $model, string $type, int $expected, array $values, string $action = 'updated'): void
    {
        Rules::version($model->lock_version, $expected);
        $model->forceFill($values);
        $fields = array_keys($model->getDirty());
        if ($fields !== []) {
            if ($expected >= 4294967295) {
                throw new TVClubSocialException('SOCIAL_STATE');
            }
            $this->save($model, ['lock_version' => $expected + 1]);
            $this->audit($actor, $type, $model->id, $action, ['fields' => $fields, 'version' => $model->lock_version]);
        }
    }

    private function audit(User $actor, string $type, int $id, string $action, ?array $changes = null): void
    {
        $event = new TVClubSocialEvent;
        $this->save($event, [
            'action' => $action, 'target_type' => $type, 'target_id' => $id, 'actor_id' => $actor->id,
            'changes' => $changes, 'created_at' => $event->freshTimestamp(),
        ]);
    }

    private function save(TVClubSocialRecord $model, array $fields): void
    {
        if (! $model->forceFill($fields)->save()) {
            throw new TVClubSocialException('SOCIAL_FAILURE');
        }
    }

    private function transaction(User $actor, string $ability, Closure $operation): mixed
    {
        try {
            if (DB::transactionLevel() !== 0) {
                throw new TVClubSocialException('SOCIAL_FAILURE');
            }

            return DB::transaction(function () use ($actor, $ability, $operation) {
                // Locking reads keep the consistent snapshot unopened until after owner locks.
                $fresh = User::query()->sharedLock()->findOrFail($actor->getKey());
                $fresh->setRelation('role', $fresh->role()->sharedLock()->first());
                Gate::forUser($fresh)->authorize($ability, TVClubSocialAccount::class);

                return $operation($fresh);
            }, 3);
        } catch (TVClubSocialException|AuthorizationException|ModelNotFoundException|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $errno = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            throw new TVClubSocialException(match ($errno) {
                1062 => 'SOCIAL_DUPLICATE',
                1205, 1213 => 'SOCIAL_CONFLICT',
                default => 'SOCIAL_FAILURE',
            });
        }
    }
}
