<?php

namespace App\Livewire\Admin\TVClub\Social;

use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialPost;
use App\Support\TVClubSocialRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public bool $posts;

    #[Url]
    public string $platform = '';

    #[Url]
    public string $account = '';

    #[Url]
    public string $period = '';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        $this->posts = request()->routeIs('admin.tvclub.social-posts.index');
        $this->authorize('viewAny', $this->posts ? TVClubSocialPost::class : TVClubSocialAccount::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['platform', 'account', 'period', 'type'], true)) {
            if ($property === 'platform') {
                $this->reset('account', 'type');
            }
            $this->resetPage();
        }
    }

    public function render()
    {
        $this->authorize('viewAny', $this->posts ? TVClubSocialPost::class : TVClubSocialAccount::class);
        $platforms = array_keys(TVClubSocialRules::TYPES);
        $platform = in_array($this->platform, $platforms, true) ? $this->platform : null;
        $accounts = TVClubSocialAccount::query()->when($platform, fn ($query) => $query->where('platform', $platform));
        $types = $platform ? TVClubSocialRules::TYPES[$platform] : array_unique(array_merge(...array_values(TVClubSocialRules::TYPES)));
        $options = collect();
        if ($this->posts) {
            $period = preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $this->period) && $this->period !== '9999-12'
                ? TVClubSocialRules::period($this->period) : null;
            $account = filter_var($this->account, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $rows = TVClubSocialPost::query()->with('account:id,platform,handle,archived_at')
                ->when($platform, fn ($query) => $query->whereHas('account', fn ($query) => $query->where('platform', $platform)))
                ->when($account, fn ($query) => $query->where('account_id', $account))
                ->when(in_array($this->type, $types, true), fn ($query) => $query->where('content_type', $this->type))
                ->when($period, fn ($query) => $query->where('published_at', '>=', $period->utc()->format('Y-m-d H:i:s.u'))
                    ->where('published_at', '<', $period->addMonth()->utc()->format('Y-m-d H:i:s.u')))
                ->latest('published_at')->latest('id')->paginate(15);
            $options = $accounts->orderBy('platform')->orderBy('handle')->get(['id', 'platform', 'handle']);
        } else {
            $rows = $accounts->orderBy('platform')->orderBy('handle')->paginate(15);
        }

        return view('livewire.admin.tvclub.social.index', compact('rows', 'options', 'platforms', 'types'))
            ->title($this->posts ? 'Konten Media Sosial' : 'Akun Media Sosial');
    }
}
