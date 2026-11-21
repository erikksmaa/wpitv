<?php

namespace App\Livewire\Admin\TVClub\Social;

use App\Exceptions\TVClubSocialException;
use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialPost;
use App\Services\TVClubSocialService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Observations extends Component
{
    use AuthorizesRequests, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public string $scope;

    #[Locked]
    public int $ownerId;

    #[Locked]
    public ?int $correctsId = null;

    public string $observedAt = '';

    public array $metrics = [];

    public string $sourceNote = '';

    public string $reason = '';

    public ?string $notice = null;

    public function mount(string $scope, int $ownerId): void
    {
        $this->scope = $scope;
        $this->ownerId = $ownerId;
        $this->owner();
    }

    public function correct(int $observationId): void
    {
        $owner = $this->owner();
        $this->authorizeMutation($owner);
        $observation = $owner->observations()->find($observationId);
        if ($observation === null) {
            $this->addError('operation', 'Observasi tidak tersedia pada histori ini.');

            return;
        }
        if ($owner->observations()->where('corrects_id', $observationId)->exists()) {
            $this->addError('operation', 'Observasi ini sudah dikoreksi. Pilih catatan koreksi terbaru.');

            return;
        }
        $this->resetValidation();
        $this->reset('notice', 'reason');
        $this->correctsId = $observation->id;
        $this->observedAt = $observation->observed_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s');
        foreach ($this->fields($owner) as $key => $label) {
            $this->metrics[$key] = (string) $observation->$key;
        }
        $this->sourceNote = $observation->source_note ?? '';
    }

    public function cancelCorrection(): void
    {
        $this->authorizeMutation($this->owner());
        $this->clearForm();
    }

    public function save(TVClubSocialService $service): void
    {
        $owner = $this->owner();
        $this->authorizeMutation($owner);
        $this->resetValidation();
        $this->notice = null;
        if ($this->correctsId === null) {
            $this->validate(['observedAt' => ['required', 'date_format:Y-m-d\TH:i:s,Y-m-d\TH:i']], [
                'observedAt.required' => 'Isi waktu observasi WIB.',
                'observedAt.date_format' => 'Isi tanggal dan waktu observasi WIB yang valid.',
            ]);
            $time = CarbonImmutable::parse($this->observedAt, 'Asia/Jakarta');
        } else {
            $time = $owner->observations()->findOrFail($this->correctsId)->observed_at;
        }
        $metrics = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $this->metrics);
        try {
            if ($owner instanceof TVClubSocialPost) {
                $service->observePost(auth()->user(), $owner->account_id, $owner->id, $time, $metrics, $this->sourceNote, $this->correctsId, $this->reason);
            } else {
                $service->observeAccount(auth()->user(), $owner->id, $time, $metrics, $this->sourceNote, $this->correctsId, $this->reason);
            }
        } catch (TVClubSocialException $exception) {
            $this->addError('operation', $exception->errorCode === 'SOCIAL_STATE' && $this->correctsId !== null
                ? 'Koreksi tidak dapat disimpan. Periksa histori terbaru dan status arsip.'
                : $exception->getMessage());

            return;
        } catch (ModelNotFoundException) {
            $this->addError('operation', 'Data observasi sudah tidak tersedia.');

            return;
        }

        $this->notice = $this->correctsId === null ? 'Observasi disimpan.' : 'Koreksi disimpan. Catatan sebelumnya tetap tersedia.';
        $this->clearForm();
        $this->resetPage('observationsPage');
    }

    public function render()
    {
        $owner = $this->owner();
        $query = $owner->observations()->orderByDesc('observed_at')->orderByDesc('id');
        $latest = (clone $query)->first();
        $table = $query->getRelated()->getTable();
        $history = $query->select($table.'.*')->addSelect([
            'correction_id' => $query->getRelated()->newQuery()->from($table.' as correction')->select('correction.id')
                ->whereColumn('correction.corrects_id', $table.'.id')->limit(1),
        ])->with('actor:id,name')->paginate(10, pageName: 'observationsPage');
        $fields = $this->fields($owner);
        $editable = $this->canMutate($owner);

        return view('livewire.admin.tvclub.social.observations', compact('fields', 'latest', 'history', 'editable'));
    }

    private function owner(): TVClubSocialAccount|TVClubSocialPost
    {
        $class = match ($this->scope) {
            'account' => TVClubSocialAccount::class,
            'post' => TVClubSocialPost::class,
            default => abort(404),
        };
        $this->authorize('viewAny', $class);
        $owner = $this->scope === 'post'
            ? $class::with('account:id,platform,archived_at')->findOrFail($this->ownerId)
            : $class::findOrFail($this->ownerId);
        $this->authorize('view', $owner);

        return $owner;
    }

    private function canMutate(TVClubSocialAccount|TVClubSocialPost $owner): bool
    {
        return auth()->user()->can('manageContent', $owner)
            && $owner->archived_at === null
            && (! ($owner instanceof TVClubSocialPost) || $owner->account->archived_at === null);
    }

    private function authorizeMutation(TVClubSocialAccount|TVClubSocialPost $owner): void
    {
        $this->authorize('manageContent', $owner);
        abort_unless($this->canMutate($owner), 403);
    }

    private function fields(TVClubSocialAccount|TVClubSocialPost $owner): array
    {
        if ($owner instanceof TVClubSocialAccount) {
            return ['followers' => 'Pengikut'];
        }

        return ['views' => 'Tayangan', 'likes' => $owner->account->platform === 'facebook' ? 'Reaksi' : 'Suka', 'comments' => 'Komentar', 'shares' => 'Bagikan'];
    }

    private function clearForm(): void
    {
        $this->reset('correctsId', 'observedAt', 'metrics', 'sourceNote', 'reason');
        $this->resetValidation();
    }
}
