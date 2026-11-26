<?php

namespace App\Livewire\Admin\Social;

use App\Services\SocialReport as Social;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Report extends Component
{
    public string $month = '', $note = '';
    #[Locked] public string $period = '';
    #[Locked] public int $lock = 0;
    #[Locked] public int $history = 0;
    #[Locked] public bool $schemaReady = true;
    public array $metrics = [], $analysis = [], $refs = [];

    public function mount(): void
    {
        $this->month = now('Asia/Jakarta')->format('Y-m');
        $this->loadPeriod();
    }

    private function records() { return DB::table('tvclub_social_reports')->where('period_start',$this->period.'-01'); }
    private function versions() { return DB::table('tvclub_social_report_versions')->where('period_start',$this->period.'-01'); }

    public function loadPeriod(): void
    {
        Gate::authorize('social.view');
        $this->validate(['month'=>'required|date_format:Y-m'], ['required'=>'Pilih periode laporan.', 'date_format'=>'Gunakan bulan dan tahun yang valid.']);
        $this->period = $this->month;
        $this->history = 0;
        $this->note = '';
        try {
            $row = $this->records()->first();
            $this->versions()->limit(1)->exists();
            $this->schemaReady = true;
        } catch (QueryException $e) {
            report($e);
            $this->schemaReady = false;
            $this->lock = 0;
            $this->metrics = array_fill_keys(array_keys(Social::PLATFORMS), array_fill_keys(array_keys(Social::METRICS), null));
            $this->analysis = [];
            $this->refs = [];
            return;
        }
        $draft = json_decode($row->draft ?? '{}',true);
        $this->lock = $row->lock_version ?? 0;
        $this->metrics = $draft['metrics'] ?? array_fill_keys(array_keys(Social::PLATFORMS), array_fill_keys(array_keys(Social::METRICS),null));
        $this->analysis = $draft['analysis'] ?? [];
        $this->refs = $draft['refs'] ?? [];
        $this->resetValidation();
    }

    public function version(int $id): void
    {
        Gate::authorize('social.view');
        abort_if($id && !$this->versions()->where('id',$id)->exists(),404);
        $this->history = $id;
    }

    public function fillStartFromPreviousMonth(): void
    {
        Gate::authorize('social.manage');
        if (!$this->schemaReady || $this->history) {
            return;
        }
        $prevPeriod = CarbonImmutable::createFromFormat('!Y-m', $this->period, 'Asia/Jakarta')->subMonth()->format('Y-m-d');
        $prev = DB::table('tvclub_social_reports')->where('period_start', $prevPeriod)->first();
        $prevDraft = $prev ? json_decode($prev->draft ?? '{}', true) : [];
        $filled = 0;
        foreach (array_keys(Social::PLATFORMS) as $p) {
            $val = $prevDraft['metrics'][$p]['end'] ?? null;
            if (is_numeric($val) && ($this->metrics[$p]['start'] ?? null) === null) {
                $this->metrics[$p]['start'] = (int) $val;
                $filled++;
            }
        }
        session()->flash('social_notice', $filled > 0
            ? "Followers awal untuk {$filled} platform diisi dari followers akhir bulan lalu."
            : 'Tidak ada data followers akhir bulan lalu yang tersedia untuk disalin.');
    }

    public function fillEndFromObservations(): void
    {
        Gate::authorize('social.manage');
        if (!$this->schemaReady || $this->history) {
            return;
        }
        $start = CarbonImmutable::createFromFormat('!Y-m', $this->period, 'Asia/Jakarta');
        $readings = DB::table('tvclub_social_account_observations as o')
            ->join('tvclub_social_accounts as a', 'a.id', '=', 'o.account_id')
            ->where('o.observed_at', '>=', $start->utc())->where('o.observed_at', '<', $start->addMonth()->utc())
            ->whereNotNull('o.followers')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tvclub_social_account_observations as correction')->whereColumn('correction.corrects_id', 'o.id'))
            ->orderByDesc('o.observed_at')->orderByDesc('o.id')
            ->get(['a.platform', 'o.account_id', 'o.followers'])->unique('account_id');
        $byPlatform = $readings->groupBy('platform')->map(fn ($items) => $items->sum('followers'));
        $filled = 0;
        foreach (array_keys(Social::PLATFORMS) as $p) {
            if ($byPlatform->has($p) && ($this->metrics[$p]['end'] ?? null) === null) {
                $this->metrics[$p]['end'] = (int) $byPlatform[$p];
                $filled++;
            }
        }
        session()->flash('social_notice', $filled > 0
            ? "Followers akhir untuk {$filled} platform diisi dari observasi periode ini."
            : 'Tidak ada observasi followers yang tersedia untuk diisi.');
    }

    public function updatedRefs(mixed $value, string $key): void
    {
        if (!in_array($key, ['best', 'lowest'], true) || !ctype_digit((string) $value) || trim((string) ($this->analysis[$key] ?? '')) !== '') {
            return;
        }
        $post = Social::publications($this->period)->firstWhere('id', (int) $value);
        if (!$post) {
            return;
        }
        $obs = DB::table('tvclub_social_post_observations')->where('post_id', $post->id)->orderByDesc('observed_at')->orderByDesc('id')->first();
        $summaryParts = [];
        if ($obs) {
            foreach (['views' => 'tayangan', 'likes' => 'suka', 'comments' => 'komentar', 'shares' => 'bagikan'] as $col => $label) {
                if ($obs->$col !== null) {
                    $summaryParts[] = number_format($obs->$col, 0, ',', '.').' '.$label;
                }
            }
        }
        $platformLabel = Social::PLATFORMS[$post->platform] ?? ucfirst($post->platform);
        $this->analysis[$key] = $post->title.' ('.$platformLabel.')'.($summaryParts ? ' — '.implode(', ', $summaryParts) : '');
    }

    public function change(string $action): void
    {
        abort_unless(in_array($action,['save','submit','revise','approve'],true),404);
        $review = in_array($action,['revise','approve'],true);
        Gate::authorize($review?'social.review':'social.manage');
        abort_if($this->history || $this->month !== $this->period,409);
        $user = auth()->user();
        DB::transaction(function () use ($action,$review,$user) {
            $row = $this->records()->lockForUpdate()->first();
            $state = $row->status ?? 'draft';
            if (($row->lock_version ?? 0) != $this->lock || ($review ? $state!=='submitted' : !in_array($state,['draft','revision']))) {
                throw ValidationException::withMessages(['workflow'=>'Laporan telah berubah. Muat kembali periode.']);
            }
            $person = ['id'=>$user->id,'name'=>$user->name];
            $draft = json_decode($row->draft ?? '{}',true);
            if ($review) {
                $snapshot = json_decode($this->versions()->where('id',$row->version_id)->firstOrFail()->snapshot,true);
                abort_if($snapshot['submitted_by']['id']==$user->id,403,'Pengajuan harus diperiksa oleh pengguna lain.');
                if ($action==='revise') { $this->validate(['note'=>'required|string|max:1000'],['required'=>'Catatan revisi harus diisi.','max'=>'Catatan revisi maksimal 1.000 karakter.']); }
            } else {
                $required = $action==='submit'?'required':'nullable';
                $rules=[];
                foreach (Social::PLATFORMS as $p=>$label) {
                    foreach (Social::METRICS as $k=>$metric) {
                        $rules["metrics.$p.$k"] = $k==='er'?'nullable|numeric|min:0|max:999999999999':'nullable|integer|min:0|max:999999999999';
                    }
                }
                foreach (Social::ANALYSIS as $k=>$label) { $rules["analysis.$k"]=$required.'|string|max:5000'; }
                foreach (['best','lowest'] as $k) { $rules["refs.$k"]='nullable|integer'; }
                $data = $this->validate($rules, [
                    'min'=>'Nilai tidak boleh negatif.', 'metrics.*.start.min'=>'Jumlah followers tidak boleh negatif.',
                    'metrics.*.end.min'=>'Jumlah followers tidak boleh negatif.', 'integer'=>'Masukkan bilangan bulat.',
                    'numeric'=>'Masukkan angka.', 'required'=>'Bagian ini harus diisi.', 'string'=>'Masukkan teks.',
                    'max'=>'Nilai melebihi batas :max.',
                ]);
                $posts = Social::publications($this->period);
                $references = [];
                foreach (array_filter($data['refs'] ?? []) as $key=>$id) {
                    $post = $posts->firstWhere('id',$id);
                    if (!$post) { throw ValidationException::withMessages(["refs.$key"=>'Pilih publikasi dalam periode laporan.']); }
                    $references[$key] = ['id'=>$post->id,'title'=>$post->title,'platform'=>$post->platform];
                }
                $normalizedMetrics = [];
                foreach (Social::PLATFORMS as $p => $label) {
                    foreach (Social::METRICS as $k => $metric) {
                        $val = $data['metrics'][$p][$k] ?? null;
                        $normalizedMetrics[$p][$k] = is_numeric($val) ? ($k === 'er' ? (float) $val : (int) $val) : null;
                    }
                }
                $draft = ['metrics'=>$normalizedMetrics,'analysis'=>$data['analysis'] ?? [],'refs'=>$data['refs'] ?? [],'author'=>$draft['author'] ?? $person];
                $draft += Social::summary($draft['metrics'],$posts) + ['period'=>$this->period,'references'=>$references,'identity'=>config('social-media.document')];
                $snapshot = $draft + ['submitted_by'=>$person,'approved_by'=>null];
            }
            $status = match ($action) { 'submit'=>'submitted','revise'=>'revision','approve'=>'approved',default=>$state };
            $versionId = $row->version_id ?? null;
            if (in_array($action,['submit','approve'])) {
                $number = 1+(int)$this->versions()->max('revision_number');
                if ($review) { $snapshot['approved_by']=$person; }
                $snapshot['status']=$status;
                $encoded=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                $versionId=$this->versions()->insertGetId(['period_start'=>$this->period.'-01','revision_number'=>$number,
                    'schema_version'=>2,'snapshot'=>$encoded,'snapshot_hash'=>hash('sha256',$encoded),'published_by'=>$user->id,
                    'published_at'=>now('UTC'),'reason'=>$number===1?null:Social::STATES[$status]]);
            }
            $values=['draft'=>json_encode($draft,JSON_THROW_ON_ERROR),'status'=>$status,'version_id'=>$versionId,'lock_version'=>$this->lock+1];
            if ($row) { $this->records()->update($values); $id=$row->id; }
            else { $id=$this->records()->insertGetId($values+['period_start'=>$this->period.'-01','author_id'=>$user->id]); }
            Social::event('report',$id,$row?'updated':'created',['period'=>$this->period,'operation'=>$action,'status'=>$status,'version'=>$versionId,'note'=>$action==='revise'?$this->note:null]);
        },3);
        $this->loadPeriod();
        session()->flash('social_notice',match ($action) {
            'submit'=>'Laporan berhasil diajukan.', 'approve'=>'Laporan berhasil disetujui.',
            'revise'=>'Laporan dikembalikan untuk revisi.', default=>'Laporan berhasil disimpan.',
        });
    }

    public function render()
    {
        Gate::authorize('social.view');
        if (!$this->schemaReady) {
            return view('livewire.admin.social.report', [
                'status' => 'draft', 'snapshot' => null, 'summary' => Social::summary($this->metrics, []),
                'posts' => collect(), 'editable' => false, 'versions' => collect(), 'events' => [],
                'accountReadings' => collect(), 'previewUrl' => null, 'officialVersionId' => null,
            ]);
        }
        $row=$this->records()->first();
        $status=$row->status ?? 'draft';
        $id=$this->history ?: (in_array($status,['submitted','approved'])?$row->version_id:null);
        $snapshot=$id ? json_decode($this->versions()->where('id',$id)->firstOrFail()->snapshot,true) : null;
        $posts=$snapshot===null ? Social::publications($this->period) : collect();
        $summary=$snapshot ?? Social::summary($this->metrics,$posts);
        $editable=!$id && auth()->user()->can('social.manage');
        $accountReadings = collect();
        if ($editable) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $this->period, 'Asia/Jakarta');
            $accountReadings = DB::table('tvclub_social_account_observations as o')
                ->join('tvclub_social_accounts as a', 'a.id', '=', 'o.account_id')
                ->where('o.observed_at', '>=', $start->utc())->where('o.observed_at', '<', $start->addMonth()->utc())
                ->whereNotNull('o.followers')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tvclub_social_account_observations as correction')->whereColumn('correction.corrects_id','o.id'))
                ->orderByDesc('o.observed_at')->orderByDesc('o.id')
                ->get(['a.platform','a.handle','o.account_id','o.followers','o.observed_at'])->unique('account_id');
        }
        $versions=$this->versions()->orderByDesc('revision_number')->get(['id','revision_number','schema_version','published_at']);
        $events=$row ? DB::table('tvclub_social_events')->where('target_type','report')->where('target_id',$row->id)->where('changes->period',$this->period)->orderByDesc('id')->get() : [];
        $previewUrl = $id && isset($snapshot['counts']) ? route('admin.social.preview',$id)
            : (!$id && $row ? route('admin.social.draft',$this->period) : null);
        $officialVersionId = $id && ($snapshot['status'] ?? '') === 'approved' && !empty($snapshot['approved_by']['id']) ? $id : null;
        $reviewerConfigured = count(config('social-media.reviewers', [])) > 0;
        return view('livewire.admin.social.report',compact('status','snapshot','summary','posts','editable','versions','events','accountReadings','previewUrl','officialVersionId','reviewerConfigured'));
    }
}
