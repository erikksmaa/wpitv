<?php

namespace App\Services;

use App\Models\Coverage;
use App\Models\MonthlyActivityReport;
use App\Models\News;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MonthlyActivityCandidates
{
    public const LABELS = ['agenda' => 'Agenda', 'coverage' => 'Peliputan', 'news' => 'Berita', 'video' => 'Tayangan'];

    private const MODELS = ['agenda' => WpitvAgenda::class, 'coverage' => Coverage::class, 'news' => News::class, 'video' => Video::class];

    public function types(User $actor, MonthlyActivityReport $report): array
    {
        Gate::forUser($actor)->authorize('update', $report);
        $types = $report->division === 'production' ? ['agenda', 'coverage', 'video'] : ['news', 'video'];

        return array_values(array_filter($types, fn ($type) => Gate::forUser($actor)->allows('viewAny', self::MODELS[$type])));
    }

    public function page(User $actor, MonthlyActivityReport $report, string $type, string $search): Paginator
    {
        if (! in_array($type, $this->types($actor, $report), true)) {
            return new Paginator([], 10, 1, ['pageName' => 'candidatePage']);
        }

        $search = mb_substr(trim($search), 0, 100);
        if ($report->division === 'news' && $type === 'video' && $search === '') {
            return new Paginator([], 10, 1, ['pageName' => 'candidatePage']);
        }

        $page = $this->query($report, $type)
            ->when($search !== '', fn (Builder $query) => $query->where($type === 'coverage' ? 'event_name' : 'title', 'like', '%'.$search.'%'))
            ->orderByDesc('id')->simplePaginate(10, pageName: 'candidatePage');
        $page->setCollection($page->getCollection()
            ->filter(fn (Model $row) => Gate::forUser($actor)->allows('view', $row))
            ->map(fn (Model $row) => $this->context($row, $type)));

        return $page;
    }

    public function resolve(User $actor, MonthlyActivityReport $report, string $type, int $id): array
    {
        $row = in_array($type, $this->types($actor, $report), true)
            ? $this->query($report, $type)->find($id)
            : null;
        if (! $row || Gate::forUser($actor)->denies('view', $row)) {
            throw ValidationException::withMessages(['activity' => 'Sumber tidak tersedia atau tidak memenuhi syarat kandidat.']);
        }

        return $this->context($row, $type);
    }

    public function current(User $actor, MonthlyActivityReport $report, Collection $items): array
    {
        $current = [];
        foreach ($this->types($actor, $report) as $type) {
            $ids = $items->where('source_type', $type)->pluck('source_id');
            if ($ids->isEmpty()) {
                continue;
            }

            foreach ($this->query($report, $type)->whereKey($ids->all())->get() as $row) {
                if (Gate::forUser($actor)->allows('view', $row)) {
                    $current[$type][$row->id] = $this->context($row, $type);
                }
            }
        }

        return $current;
    }

    private function query(MonthlyActivityReport $report, string $type): Builder
    {
        $start = CarbonImmutable::parse($report->period_start->toDateString(), 'Asia/Jakarta');
        $end = $start->addMonth();
        $dateRange = fn (Builder $query, string $column) => $query->where($column, '>=', $start->toDateString())->where($column, '<', $end->toDateString());
        $agenda = fn (Builder $query) => $dateRange($query, 'agenda_date')->where('status', 'completed');

        return match ($type) {
            'agenda' => $agenda(WpitvAgenda::select('id', 'title', 'agenda_date', 'status'))->whereNull('coverage_id'),
            'coverage' => Coverage::select('id', 'event_name', 'event_date', 'status')
                ->withMin(['agendas as linked_agenda_date' => $agenda], 'agenda_date')
                ->addSelect(['linked_agenda_id' => $agenda(WpitvAgenda::select('id')->whereColumn('coverage_id', 'tr_perliputan.id'))
                    ->orderBy('agenda_date')->orderBy('id')->limit(1)])
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $dateRange($query, 'event_date')->where('status', Coverage::STATUS_COMPLETED))
                    ->orWhereHas('agendas', $agenda)),
            'news' => News::select('id', 'title')
                ->selectRaw('UNIX_TIMESTAMP(created_at) as recorded_epoch')
                ->whereRaw('created_at >= FROM_UNIXTIME(?) and created_at < FROM_UNIXTIME(?)', [$start->timestamp, $end->timestamp]),
            'video' => $dateRange(Video::select('id', 'title', 'air_date'), 'air_date')
                ->when($report->division === 'production', fn (Builder $query) => $query->whereNotNull('program_id')
                    ->whereDoesntHave('archivedLiveStream', fn (Builder $stream) => $stream
                        ->where('status', 'ended')->whereRaw('BINARY live_streams.youtube_id = BINARY ms_video.youtube_id')
                        ->whereHas('agenda', $agenda))),
        };
    }

    private function context(Model $row, string $type): array
    {
        [$date, $basis] = match ($type) {
            'agenda' => [$row->agenda_date->toDateString(), 'Tanggal Agenda'],
            'coverage' => $row->linked_agenda_date
                ? [(string) $row->linked_agenda_date, 'Tanggal Agenda terkait Peliputan']
                : [$row->event_date->toDateString(), 'Tanggal acara Peliputan'],
            'news' => [CarbonImmutable::createFromTimestamp((int) $row->recorded_epoch, 'Asia/Jakarta')->toDateString(), 'Tanggal pencatatan Berita (WIB)'],
            'video' => [$row->air_date->toDateString(), 'Tanggal tayang'],
        };

        $context = ['type' => $type, 'id' => (int) $row->id, 'title' => $row->title ?? $row->event_name, 'date' => $date, 'date_basis' => $basis, 'status' => $row->status];
        if ($type === 'coverage' && $row->linked_agenda_id) {
            $context['agenda_id'] = (int) $row->linked_agenda_id;
        }

        return $context;
    }
}
