<?php

namespace App\Livewire\Admin\MonthlyReports;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Services\MonthlyActivityLifecycle;
use App\Support\MonthlyActivityRules as Rules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $period = '';

    #[Url]
    public string $division = '';

    #[Url]
    public string $status = '';

    public string $draftPeriod = '';

    public function mount(): void
    {
        $this->authorize('viewAny', MonthlyActivityReport::class);
        $this->draftPeriod = now('Asia/Jakarta')->format('Y-m');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'division', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function createDraft(MonthlyActivityLifecycle $service): void
    {
        $this->authorize('viewAny', MonthlyActivityReport::class);
        $division = $this->ownDivision(auth()->user());
        $this->authorize('create', [MonthlyActivityReport::class, $division]);

        try {
            $report = $service->createDraft(auth()->user(), $division, $this->draftPeriod);
            $this->redirectRoute('admin.monthly-reports.show', $report->id);
        } catch (MonthlyActivityException $exception) {
            $this->addError('draftPeriod', $exception->getMessage());
        } catch (ValidationException $exception) {
            $this->addError('draftPeriod', $exception->validator->errors()->first());
        }
    }

    public function render()
    {
        $this->authorize('viewAny', MonthlyActivityReport::class);
        $user = auth()->user();
        $ownDivision = $this->ownDivision($user);
        $division = $user->isSuperAdmin() ? $this->division : $ownDivision;
        $reports = MonthlyActivityReport::query()
            ->when(in_array($division, Rules::DIVISIONS, true), fn ($query) => $query->where('division', $division))
            ->when(preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $this->period), fn ($query) => $query->where('period_start', $this->period.'-01'))
            ->when(isset(Rules::STATUS_LABELS[$this->status]), fn ($query) => $query->where('status', $this->status))
            ->withCount('items')->orderByDesc('period_start')->orderBy('id')->paginate(15);

        return view('livewire.admin.monthly-reports.index', compact('reports', 'ownDivision'))->title('Laporan Bulanan');
    }

    private function ownDivision(User $user): string
    {
        return match (true) {
            $user->isAdminProduction() => 'production',
            $user->isAdminNews() => 'news',
            default => '',
        };
    }
}
