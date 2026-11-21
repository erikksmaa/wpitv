<?php

namespace App\Livewire\Admin\MonthlyReports;

use App\Exceptions\MonthlyActivityException;
use App\Models\MonthlyActivityReport;
use App\Services\MonthlyActivityCandidates;
use App\Services\MonthlyActivityFiles;
use App\Services\MonthlyActivityLifecycle;
use App\Support\MonthlyActivityRules as Rules;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Show extends Component
{
    use AuthorizesRequests, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public int $reportId;

    #[Locked]
    public int $expectedVersion;

    #[Locked]
    public ?int $itemId = null;

    #[Locked]
    public ?array $source = null;

    #[Locked]
    public bool $editing = false;

    #[Locked]
    public ?int $revisionNumber = null;

    public string $reviewReason = '';

    public array $activity = [];

    public bool $confirmDuplicate = false;

    public string $nilReason = '';

    public string $candidateType = '';

    public string $search = '';

    public function mount(int $reportId): void
    {
        $this->reportId = $reportId;
        $report = $this->report();
        $this->expectedVersion = $report->version;
        $this->nilReason = $report->nil_reason ?? '';
        $this->candidateType = $report->division === 'production' ? 'agenda' : 'news';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['candidateType', 'search'], true)) {
            $this->resetPage('candidatePage');
        }
    }

    public function newItem(): void
    {
        $this->editableReport();
        $this->openItem();
    }

    public function editItem(int $itemId): void
    {
        $item = $this->editableReport()->items()->findOrFail($itemId);
        $this->openItem([
            ...$item->only(array_keys(Rules::ITEM_FIELDS)),
            'activity_date' => $item->activity_date->toDateString(),
        ], $item->id);
    }

    public function chooseCandidate(string $type, int $id, MonthlyActivityCandidates $candidates): void
    {
        $report = $this->editableReport();
        $this->resetValidation();
        try {
            $source = $candidates->resolve(auth()->user(), $report, $type, $id);
            $this->openItem(['title' => $source['title'], 'activity_date' => $source['date']], source: $source);
        } catch (ValidationException $exception) {
            $this->addError('report', $exception->validator->errors()->first());
        }
    }

    public function cancelEdit(): void
    {
        $this->report();
        $this->closeItem();
    }

    public function saveItem(MonthlyActivityLifecycle $service): void
    {
        $this->editableReport();
        abort_unless($this->editing, 403);

        $this->mutate(fn () => $service->saveItem(
            auth()->user(),
            $this->reportId,
            $this->expectedVersion,
            $this->itemId,
            $this->activity,
            $this->source['type'] ?? null,
            $this->source['id'] ?? null,
            $this->source === null ? null : Rules::snapshotHash($this->source),
            $this->confirmDuplicate,
        ), 'Kegiatan disimpan.');
    }

    public function removeItem(int $id, MonthlyActivityLifecycle $service): void
    {
        $this->editableReport();
        abort_if($this->editing, 403);
        $this->mutate(fn () => $service->deleteItem(auth()->user(), $this->reportId, $this->expectedVersion, $id), 'Kegiatan dihapus.');
    }

    public function moveItem(int $id, int $offset, MonthlyActivityLifecycle $service): void
    {
        $this->editableReport();
        abort_if($this->editing, 403);
        $this->mutate(fn () => $service->moveItem(auth()->user(), $this->reportId, $this->expectedVersion, $id, $offset), 'Urutan disimpan.');
    }

    public function saveNilReason(MonthlyActivityLifecycle $service): void
    {
        $this->editableReport();
        abort_if($this->editing, 403);
        $this->mutate(fn () => $service->updateDraft(auth()->user(), $this->reportId, $this->expectedVersion, $this->nilReason), 'Alasan nihil disimpan.');
    }

    public function submitReport(MonthlyActivityLifecycle $service): void
    {
        $this->authorize('submit', $this->report());
        abort_if($this->editing, 403);
        $this->mutate(fn () => $service->submit(auth()->user(), $this->reportId, $this->expectedVersion), 'Laporan dikirim untuk ditinjau.');
    }

    public function detachAttachment(int $id, MonthlyActivityLifecycle $service): void
    {
        $this->editableReport();
        abort_if($this->editing, 403);
        $this->mutate(fn () => $service->detachAttachment(auth()->user(), $this->reportId, $this->expectedVersion, $id), 'Lampiran dilepas dari laporan.');
    }

    public function requestRevision(MonthlyActivityLifecycle $service): void
    {
        $this->authorizeReview();
        $this->mutate(fn () => $service->requestRevision(auth()->user(), $this->reportId, $this->expectedVersion, $this->reviewReason), 'Permintaan revisi dikirim.');
    }

    public function approveReport(MonthlyActivityLifecycle $service): void
    {
        $this->authorizeReview();
        $this->mutate(fn () => $service->approve(auth()->user(), $this->reportId, $this->expectedVersion), 'Laporan disetujui.');
    }

    public function openRevision(int $number): void
    {
        $this->report()->versions()->where('revision_number', $number)->firstOrFail(['id']);
        $this->revisionNumber = $number;
        $this->reset('reviewReason');
        $this->resetPage('snapshotPage');
    }

    public function openLatestRevision(): void
    {
        $this->report();
        $this->reset('revisionNumber', 'reviewReason');
        $this->resetPage('snapshotPage');
    }

    public function render(MonthlyActivityCandidates $service, MonthlyActivityFiles $files)
    {
        $report = $this->report();
        $items = Rules::editable($report->status) ? $report->items()->orderBy('position')->orderBy('id')->paginate(15) : null;
        $editable = $this->canEdit($report);
        $stale = $this->expectedVersion !== $report->version;
        $types = $editable ? $service->types(auth()->user(), $report) : [];
        $candidates = $editable && ! $stale && ! $this->editing ? $service->page(auth()->user(), $report, $this->candidateType, $this->search) : null;
        $current = $editable ? $service->current(auth()->user(), $report, $items->getCollection()) : [];
        $selected = $candidates?->count() ? $report->items()->where('source_type', $this->candidateType)
            ->whereIn('source_id', $candidates->getCollection()->pluck('id'))->pluck('source_id')->all() : [];

        $versions = $report->versions()->orderByDesc('revision_number')
            ->simplePaginate(10, ['id', 'revision_number', 'submitted_at'], 'versionPage');
        $snapshot = $report->versions()->with('events:id,report_version_id,action,actor_name')
            ->when($this->revisionNumber !== null, fn ($query) => $query->where('revision_number', $this->revisionNumber))
            ->orderByDesc('revision_number')->first();
        $snapshotPage = $this->getPage('snapshotPage');
        $snapshotItems = new Paginator(
            array_slice($snapshot?->snapshot['items'] ?? [], ($snapshotPage - 1) * 15, 16),
            15,
            $snapshotPage,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'snapshotPage'],
        );
        $timeline = $report->events()->with('reportVersion:id,revision_number')
            ->select('id', 'report_version_id', 'action', 'to_status', 'actor_name', 'reason', 'created_at')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(changes, '$.operation')) AS operation")
            ->orderByDesc('id')->simplePaginate(12, pageName: 'timelinePage');
        $revisionNote = $report->status === 'revision'
            ? $report->events()->with('reportVersion:id,revision_number')->where('action', 'revision_requested')
                ->latest('id')->first(['id', 'reason', 'actor_name', 'report_version_id', 'created_at'])
            : null;
        $canReview = Gate::allows('review', $report);
        $canDecide = $canReview && $report->status === 'submitted' && $this->revisionNumber === null && $snapshot !== null;
        $attachments = $items !== null
            ? $report->attachments()->attached()->with('item:id,title')->orderBy('id')->limit(Rules::ATTACHMENT_LIMIT)->get()
            : collect();
        $snapshotAttachments = collect($snapshot?->snapshot['attachments'] ?? []);
        $snapshotFiles = $snapshotAttachments->isEmpty() ? collect()
            : $report->attachments()->whereIn('id', $snapshotAttachments->pluck('id'))->get()->keyBy('id');

        return view('livewire.admin.monthly-reports.show', compact(
            'report', 'items', 'editable', 'stale', 'types', 'candidates', 'current', 'selected',
            'versions', 'snapshot', 'snapshotItems', 'timeline', 'revisionNote', 'canReview', 'canDecide',
            'attachments', 'snapshotAttachments', 'snapshotFiles', 'files',
        ))
            ->title('Detail Laporan Bulanan');
    }

    private function authorizeReview(): void
    {
        $this->authorize('review', $this->report());
        abort_if($this->revisionNumber !== null, 403);
    }

    private function report(): MonthlyActivityReport
    {
        $this->authorize('viewAny', MonthlyActivityReport::class);
        $report = MonthlyActivityReport::findOrFail($this->reportId);
        $this->authorize('view', $report);

        return $report;
    }

    private function editableReport(): MonthlyActivityReport
    {
        $report = $this->report();
        abort_unless($this->canEdit($report), 403);

        return $report;
    }

    private function canEdit(MonthlyActivityReport $report): bool
    {
        return Rules::editable($report->status) && Gate::allows('update', $report);
    }

    private function openItem(array $activity = [], ?int $itemId = null, ?array $source = null): void
    {
        $this->resetValidation();
        $this->activity = $activity;
        $this->itemId = $itemId;
        $this->source = $source;
        $this->confirmDuplicate = false;
        $this->editing = true;
    }

    private function closeItem(): void
    {
        $this->resetValidation();
        $this->reset('activity', 'itemId', 'source', 'confirmDuplicate', 'editing');
    }

    private function mutate(Closure $action, string $message): void
    {
        $this->resetValidation();
        try {
            $this->expectedVersion = $action()->version;
            $this->closeItem();
            $this->reset('reviewReason');
            session()->flash('success', $message);
        } catch (MonthlyActivityException $exception) {
            $this->addError('report', $exception->getMessage());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $errors) {
                $key = in_array($field, ['activity_date', ...array_keys(Rules::ITEM_FIELDS)], true) ? 'activity.'.$field : $field;
                $this->addError($key, $errors[0]);
            }
        }
    }
}
