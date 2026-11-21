<?php

namespace App\Livewire\Admin\TVClub\Social;

use App\Exceptions\TVClubSocialException;
use App\Models\Program;
use App\Models\TVClubMember;
use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialPost;
use App\Services\TVClubSocialService;
use App\Support\TVClubSocialRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class PostForm extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public ?int $postId = null;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public ?int $originalAccountId = null;

    public string $accountId = '';

    public string $publishedAt = '';

    public string $title = '';

    public string $description = '';

    public string $url = '';

    public string $type = '';

    public string $responsible = '';

    public string $memberId = '';

    public string $programId = '';

    public string $memberSearch = '';

    public string $programSearch = '';

    public function mount(?TVClubSocialPost $post = null): void
    {
        if (! $post?->exists) {
            $this->authorize('manageContent', TVClubSocialPost::class);

            return;
        }
        $this->authorize('view', $post);
        $this->postId = $post->id;
        $this->version = $post->lock_version;
        $this->originalAccountId = $post->account_id;
        $this->accountId = (string) $post->account_id;
        $this->publishedAt = $post->published_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s');
        $this->title = $post->title;
        $this->description = $post->description ?? '';
        $this->url = $post->url;
        $this->type = $post->content_type;
        $this->responsible = $post->responsible_label;
        $this->memberId = (string) $post->member_id;
        $this->programId = (string) $post->program_id;
    }

    public function updatedAccountId(): void
    {
        $this->type = '';
    }

    public function save(TVClubSocialService $service): void
    {
        $this->authorize('manageContent', TVClubSocialPost::class);
        $data = $this->validate([
            'accountId' => ['required', 'integer', 'min:1'],
            'publishedAt' => ['required', 'date_format:Y-m-d\TH:i:s,Y-m-d\TH:i'],
        ], ['publishedAt.date_format' => 'Isi tanggal dan waktu publikasi WIB yang valid.']);
        $post = $this->postId === null ? null : TVClubSocialPost::findOrFail($this->postId);
        $wib = $post?->published_at?->timezone('Asia/Jakarta');
        $publishedAt = $wib && $data['publishedAt'] === $wib->format('Y-m-d\TH:i:s')
            ? $wib
            : CarbonImmutable::parse($data['publishedAt'], 'Asia/Jakarta');
        try {
            $service->savePost(auth()->user(), $this->originalAccountId ?? (int) $data['accountId'], $this->postId, $this->version, [
                'title' => $this->title, 'description' => $this->description, 'url' => $this->url,
                'content_type' => $this->type, 'responsible_label' => $this->responsible,
                'member_id' => $this->memberId, 'program_id' => $this->programId,
            ], $publishedAt);
        } catch (TVClubSocialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Unggahan disimpan.');
        $this->redirectRoute('admin.tvclub.social-posts.index');
    }

    public function archive(TVClubSocialService $service): void
    {
        $this->authorize('manageContent', TVClubSocialPost::class);
        abort_if($this->postId === null || $this->originalAccountId === null, 404);
        try {
            $service->archivePost(auth()->user(), $this->originalAccountId, $this->postId, $this->version);
        } catch (TVClubSocialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Unggahan diarsipkan.');
        $this->redirectRoute('admin.tvclub.social-posts.index');
    }

    public function render()
    {
        $this->authorize('viewAny', TVClubSocialPost::class);
        $post = $this->postId === null ? null : TVClubSocialPost::with(['account:id,platform,handle,archived_at', 'member:id,name', 'program:id,name'])->findOrFail($this->postId);
        if ($post) {
            $this->authorize('view', $post);
        } else {
            $this->authorize('manageContent', TVClubSocialPost::class);
        }
        $canManage = auth()->user()->can('manageContent', TVClubSocialPost::class);
        $editable = $canManage && ! $post?->archived_at && ! $post?->account->archived_at;
        $accounts = $editable && ! $post ? TVClubSocialAccount::whereNull('archived_at')->orderBy('platform')->orderBy('handle')->get(['id', 'platform', 'handle']) : collect();
        $platform = $post?->account->platform ?? $accounts->firstWhere('id', $this->accountId)?->platform;
        $types = TVClubSocialRules::TYPES[$platform] ?? [];
        $members = $editable && auth()->user()->can('viewAny', TVClubMember::class)
            ? $this->options(TVClubMember::class, $this->memberSearch, $this->memberId) : collect();
        $programs = $editable && auth()->user()->can('viewAny', Program::class)
            ? $this->options(Program::class, $this->programSearch, $this->programId) : collect();

        return view('livewire.admin.tvclub.social.post-form', compact('post', 'canManage', 'editable', 'accounts', 'types', 'members', 'programs'))
            ->title('Unggahan Media Sosial');
    }

    private function options(string $model, string $search, string $selected)
    {
        $term = mb_substr(trim($search), 0, 100);
        $id = filter_var($selected, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
        $options = $model::query()->where('name', 'like', '%'.$term.'%')->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name']);
        if ($id && ! $options->contains('id', $id)) {
            $record = $model::find($id, ['id', 'name']);
            if ($record && auth()->user()->can('view', $record)) {
                $options->push($record);
            }
        }

        return $options;
    }
}
