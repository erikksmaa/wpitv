<?php

namespace App\Livewire\Admin\TVClub\Social;

use App\Exceptions\TVClubSocialException;
use App\Models\TVClubSocialReportVersion;
use App\Services\TVClubSocialService;
use App\Support\TVClubSocialRecap;
use App\Support\TVClubSocialRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Reports extends Component
{
    use AuthorizesRequests, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $period = '';

    #[Url]
    public string $platform = '';

    #[Url]
    public string $account = '';

    #[Locked]
    public ?int $versionId = null;

    #[Locked]
    public ?string $preparedPeriod = null;

    #[Locked]
    public int $expectedRevision = 0;

    public string $reason = '';

    public function mount(?TVClubSocialReportVersion $version = null): void
    {
        $this->authorize('viewAny', TVClubSocialReportVersion::class);
        $this->versionId = $version?->getKey();
        if ($this->period === '') {
            $this->period = now('Asia/Jakarta')->format('Y-m');
        }
        if ($this->versionId === null) {
            $this->preparePeriod();
        }
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['period', 'platform', 'account'], true)) {
            return;
        }
        $this->authorize('viewAny', TVClubSocialReportVersion::class);
        $this->resetPage('postsPage');
        $this->resetPage('accountsPage');
        if ($property === 'platform') {
            $this->reset('account');
        }
        if ($property === 'period' && $this->versionId === null) {
            $this->resetPage('versionsPage');
            $this->preparePeriod();
        }
    }

    public function publish(TVClubSocialService $service): void
    {
        $this->authorize('publish', TVClubSocialReportVersion::class);
        abort_if($this->versionId !== null, 403);
        $this->resetValidation();
        if ($this->preparedPeriod === null || $this->period !== $this->preparedPeriod) {
            $this->addError('operation', 'Pilih ulang periode sebelum menerbitkan.');

            return;
        }
        try {
            $version = $service->publish(auth()->user(), $this->preparedPeriod, $this->expectedRevision, $this->reason);
        } catch (TVClubSocialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }
        $this->redirectRoute('admin.tvclub.social-reports.show', $version);
    }

    public function render(TVClubSocialService $service)
    {
        $this->authorize('viewAny', TVClubSocialReportVersion::class);
        $version = $this->versionId === null ? null : TVClubSocialReportVersion::findOrFail($this->versionId);
        if ($version) {
            $this->authorize('view', $version);
        }
        $month = $version?->period_start->format('Y-m') ?? $this->period;
        $period = $this->validPeriod($month);
        $snapshot = $version?->snapshot;
        $previous = $version && $version->revision_number > 1
            ? TVClubSocialReportVersion::where('period_start', $period->toDateString())
                ->where('revision_number', $version->revision_number - 1)->first()
            : null;
        $postDifference = $previous ? count($snapshot['posts']) - count($previous->snapshot['posts']) : null;
        $unavailable = false;
        if (! $version && $period) {
            try {
                $snapshot = $service->recap(auth()->user(), $month);
            } catch (TVClubSocialException|ValidationException) {
                $unavailable = true;
            }
        }
        $accounts = collect($snapshot['accounts'] ?? [])->keyBy('id');
        $options = $accounts->filter(fn ($row) => ! isset(TVClubSocialRules::TYPES[$this->platform]) || $row['platform'] === $this->platform);
        $filteredAccounts = $options->filter(fn ($row) => $this->account === '' || (string) $row['id'] === $this->account);
        $posts = array_values(array_filter($snapshot['posts'] ?? [], fn ($row) => $filteredAccounts->has($row['account_id'])));
        $summary = TVClubSocialRecap::summarize($posts);
        $versions = $period ? TVClubSocialReportVersion::where('period_start', $period->toDateString())
            ->orderByDesc('revision_number')->simplePaginate(10, ['id', 'revision_number', 'published_at', 'reason'], 'versionsPage') : null;
        $canPublish = ! $version && ! $unavailable && $period && ! $period->addMonth()->isFuture()
            && auth()->user()->can('publish', TVClubSocialReportVersion::class);
        $postCount = count($posts);
        $posts = $this->page($posts, 'postsPage');
        $reportPeriod = $period;
        $followers = $this->page($filteredAccounts->values()->all(), 'accountsPage');

        return view('livewire.admin.tvclub.social.reports', compact(
            'version', 'month', 'reportPeriod', 'unavailable', 'accounts', 'options', 'summary', 'versions', 'canPublish', 'postCount', 'posts', 'followers', 'previous', 'postDifference',
        ))->title('Laporan Media Sosial');
    }

    private function preparePeriod(): void
    {
        $period = $this->validPeriod($this->period);
        $this->resetValidation();
        $this->reset('reason');
        $this->preparedPeriod = $period?->format('Y-m');
        $this->expectedRevision = $period ? (int) TVClubSocialReportVersion::where('period_start', $period->toDateString())->max('revision_number') : 0;
    }

    private function validPeriod(string $month): ?CarbonImmutable
    {
        return preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $month) && $month !== '9999-12'
            ? TVClubSocialRules::period($month) : null;
    }

    private function page(array $rows, string $name): LengthAwarePaginator
    {
        $page = $this->getPage($name);

        return new LengthAwarePaginator(array_slice($rows, ($page - 1) * 15, 15), count($rows), 15, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => $name,
        ]);
    }
}
