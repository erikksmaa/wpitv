<?php

namespace App\Livewire\Admin\Social;

use App\Services\PublicSocialAccounts;
use App\Services\SocialReport as Social;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Catalog extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'bootstrap';
    #[Locked] public string $section;
    #[Locked] public int $id = 0;
    #[Locked] public int $version = 0;
    public bool $open = false;
    public string $search = '', $platform = '', $filter = '', $visibility = '';
    public int $perPage = 15;
    public array $form = [];
    public const OBS = ['views'=>'Tayangan','likes'=>'Suka','comments'=>'Komentar','shares'=>'Bagikan'];

    public function mount(string $section): void
    {
        abort_unless(in_array($section,['accounts','posts'],true),404);
        $this->section = $section;
    }

    private function table() { return DB::table('tvclub_social_'.$this->section); }
    public function resetFilters(): void
    {
        $this->reset('search', 'platform', 'filter', 'visibility');
        $this->resetPage();
    }

    public function updated($key): void
    {
        if (in_array($key,['search','platform','filter','visibility','perPage'])) { $this->resetPage(); }
    }

    public function edit(int $id = 0): void
    {
        Gate::authorize('social.manage');
        $this->resetValidation();
        $this->id = $id;
        $this->form = ['active'=>true,'show_on_website'=>false,'display_order'=>0,'platform'=>'instagram','content_type'=>'photo','published_at'=>now('Asia/Jakarta')->format('Y-m-d\TH:i')];
        if ($id) {
            $row = $this->table()->where('id',$id)->first();
            abort_unless($row,404);
            $this->version = $row->lock_version;
            $this->form = (array)$row;
            $this->form['active'] = !$row->archived_at;
            if ($this->section === 'accounts') {
                $this->form['show_on_website'] = (bool) ($row->show_on_website ?? false);
                $this->form['display_order'] = (int) ($row->display_order ?? 0);
            }
            if ($this->section === 'posts') {
                $this->form['published_at'] = CarbonImmutable::parse($row->published_at,'UTC')->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i');
                $this->form['content_type'] = Social::category($row->content_type);
            }
        }
        $this->open = true;
    }

    public function togglePublicVisibility(int $id, int $version): void
    {
        Gate::authorize('social.manage');
        abort_unless($this->section === 'accounts', 403);

        $publicSocial = app(PublicSocialAccounts::class);
        if (! $publicSocial->visibilityColumnsReady()) {
            session()->flash('social_error', 'Kolom visibilitas website belum tersedia pada database. Terapkan skrip SQL terlebih dahulu.');

            return;
        }

        try {
            $result = DB::transaction(function () use ($id, $version): array {
                $row = DB::table('tvclub_social_accounts')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $row || (int) $row->lock_version !== $version) {
                    return [false, 'Data akun telah berubah. Muat ulang halaman lalu coba lagi.'];
                }

                $next = ! ((bool) ($row->show_on_website ?? false));
                if ($next && trim((string) ($row->profile_url ?? '')) === '') {
                    return [false, 'Tambahkan tautan profil yang valid terlebih dahulu sebelum menampilkan akun di website.'];
                }

                $now = now('UTC');
                DB::table('tvclub_social_accounts')
                    ->where('id', $id)
                    ->update([
                        'show_on_website' => $next ? 1 : 0,
                        'updated_at' => $now,
                        'lock_version' => $version + 1,
                    ]);

                Social::event('account', $id, 'updated', [
                    'field' => 'show_on_website',
                    'show_on_website' => $next,
                ]);

                return [true, 'Visibilitas akun berhasil diperbarui.'];
            });
        } catch (QueryException $error) {
            report($error);
            session()->flash('social_error', 'Visibilitas akun gagal diperbarui. Silakan coba lagi.');

            return;
        }

        if (! $result[0]) {
            session()->flash('social_error', $result[1]);

            return;
        }

        $publicSocial->forgetCache();
        session()->flash('social_notice', $result[1]);
    }

    public function save(): void
    {
        Gate::authorize('social.manage');
        $accounts = $this->section === 'accounts';
        $visibilityReady = $accounts && app(PublicSocialAccounts::class)->visibilityColumnsReady();
        $rules = $accounts ? [
            'display_name'=>'required|string|max:160','platform'=>['required',Rule::in(array_keys(Social::PLATFORMS))],
            'handle'=>['required','string','max:100',Rule::unique('tvclub_social_accounts','handle')->where('platform',$this->form['platform'] ?? '')->ignore($this->id)],
            'profile_url'=>'nullable|url:http,https|max:500','active'=>'required|boolean',
        ] : [
            'title'=>'required|string|max:200','account_id'=>'required|integer|exists:tvclub_social_accounts,id',
            'published_at'=>'required|date_format:Y-m-d\TH:i','content_type'=>['required',Rule::in(array_keys(Social::TYPES))],
            'url'=>'nullable|url:http,https|max:500','description'=>'nullable|string|max:10000','responsible_label'=>'nullable|string|max:160',
        ];
        if ($visibilityReady) {
            $rules['show_on_website'] = 'nullable|boolean';
            $rules['display_order'] = 'nullable|integer|min:0|max:9999';
        }
        if (!$accounts) { foreach (self::OBS as $key=>$label) { $rules[$key]='nullable|integer|min:0|max:999999999999'; } }
        $fields = $this->validate(Arr::prependKeysWith($rules,'form.'), [
            'form.display_name.required'=>'Nama akun harus diisi.', 'form.platform.required'=>'Pilih platform.',
            'form.account_id.required'=>'Pilih akun media sosial.', 'form.published_at.required'=>'Tanggal publikasi harus diisi.',
            'form.title.required'=>'Judul konten harus diisi.', 'form.handle.required'=>'Username harus diisi.',
            'form.content_type.required'=>'Pilih jenis konten.', 'form.handle.unique'=>'Username sudah digunakan pada platform ini.',
            'url'=>'Masukkan tautan HTTP atau HTTPS yang valid.', 'exists'=>'Pilihan tidak tersedia.',
            'in'=>'Pilih salah satu opsi yang tersedia.', 'date_format'=>'Masukkan tanggal dan waktu publikasi yang valid.',
            'integer'=>'Masukkan bilangan bulat.', 'min'=>'Jumlah tidak boleh negatif.', 'max'=>'Nilai melebihi batas :max.',
            'string'=>'Masukkan teks.', 'boolean'=>'Pilih status akun.',
        ])['form'];
        if ($visibilityReady && ! empty($fields['show_on_website']) && trim((string) ($fields['profile_url'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'form.profile_url' => 'Tautan profil wajib diisi untuk menampilkan akun di website.',
            ]);
        }
        try {
            DB::transaction(function () use ($fields,$accounts,$visibilityReady) {
                $row = $this->id ? $this->table()->where('id',$this->id)->lockForUpdate()->first() : null;
                if ($this->id && (!$row || $row->lock_version != $this->version)) {
                    throw ValidationException::withMessages(['form'=>'Data telah berubah. Buka kembali formulir.']);
                }
                $time = now('UTC');
                $values = Arr::except($fields,['active','show_on_website','display_order',...array_keys(self::OBS)]);
                if ($accounts) {
                    if ($row && $row->platform !== $values['platform'] && DB::table('tvclub_social_posts')->where('account_id',$row->id)->exists()) {
                        throw ValidationException::withMessages(['form.platform'=>'Akun dengan publikasi tidak dapat dipindah platform.']);
                    }
                    $values['archived_at'] = $fields['active'] ? null : ($row->archived_at ?? $time);
                    if ($visibilityReady) {
                        $values['show_on_website'] = ! empty($fields['show_on_website']) ? 1 : 0;
                        $values['display_order'] = is_numeric($fields['display_order'] ?? null) ? (int) $fields['display_order'] : 0;
                    }
                    if (!$row) { $values['account_key'] = (string)\Illuminate\Support\Str::uuid(); }
                } else {
                    $account = DB::table('tvclub_social_accounts')->where('id',$fields['account_id'])->lockForUpdate()->first();
                    if (!$account || ($account->archived_at && $account->id != ($row->account_id ?? null))) {
                        throw ValidationException::withMessages(['form.account_id'=>'Pilih akun aktif.']);
                    }
                    $values['published_at'] = CarbonImmutable::parse($fields['published_at'],'Asia/Jakarta')->utc();
                    if ($row && Social::category($row->content_type) === $values['content_type']) {
                        $values['content_type'] = $row->content_type;
                    }
                    $values['responsible_label'] = $fields['responsible_label'] ?? '';
                    $values['url'] = ($fields['url'] ?? '') ?: null;
                    $values['url_hash'] = $values['url'] ? hash('sha256',$values['url']) : null;
                    if ($values['url_hash'] && $this->table()->where('account_id',$account->id)->where('url_hash',$values['url_hash'])->where('id','!=',$this->id)->exists()) {
                        throw ValidationException::withMessages(['form.url'=>'Tautan sudah tercatat untuk akun ini.']);
                    }
                }
                $values['updated_at'] = $time;
                if ($row) {
                    $id = $row->id;
                    $this->table()->where('id',$id)->update($values+['lock_version'=>$this->version+1]);
                } else { $id = $this->table()->insertGetId($values+['created_at'=>$time]); }
                Social::event($accounts?'account':'post',$id,$row?'updated':'created',[]);
                $metrics = array_filter(Arr::only($fields,array_keys(self::OBS)),fn($v)=>is_numeric($v));
                if ($metrics) {
                    $obs = DB::table('tvclub_social_post_observations')->insertGetId($metrics+['post_id'=>$id,'observed_at'=>$time,'created_at'=>$time,'actor_id'=>auth()->id()]);
                    Social::event('post_observation',$obs,'observed',['post_id'=>$id]);
                }
            });
        } catch (QueryException $error) {
            report($error);
            throw ValidationException::withMessages(['form'=>'Data gagal disimpan. Periksa kembali isian atau ulangi beberapa saat lagi.']);
        }
        if ($accounts) {
            app(PublicSocialAccounts::class)->forgetCache();
        }
        $this->open = false;
        session()->flash('social_notice',$accounts
            ? ($this->id?'Akun berhasil diperbarui.':'Akun berhasil ditambahkan.')
            : ($this->id?'Publikasi berhasil diperbarui.':'Publikasi berhasil dicatat.'));
    }

    public function archive(int $id,int $version): void
    {
        Gate::authorize('social.manage');
        abort_unless($this->section==='posts',403);
        DB::transaction(function () use ($id,$version) {
            abort_unless($this->table()->where('id',$id)->where('lock_version',$version)->whereNull('archived_at')->update([
                'archived_at'=>now('UTC'),'updated_at'=>now('UTC'),'lock_version'=>$version+1]),409);
            Social::event('post',$id,'archived',[]);
        });
        session()->flash('social_notice','Publikasi diarsipkan.');
    }

    public function render()
    {
        Gate::authorize('social.view');
        $accounts = $this->section==='accounts';
        $visibilityReady = $accounts && app(PublicSocialAccounts::class)->visibilityColumnsReady();
        $query = $accounts ? $this->table() : DB::table('tvclub_social_posts as p')->join('tvclub_social_accounts as a','a.id','=','p.account_id')
            ->select('p.*','a.display_name','a.platform')->whereNull('p.archived_at');
        $query->when($this->platform,fn($q)=>$q->where($accounts?'platform':'a.platform',$this->platform));
        $query->when($this->search!=='',fn($q)=>$q->where(fn($s)=>$s->where($accounts?'display_name':'a.display_name','like','%'.$this->search.'%')->orWhere($accounts?'handle':'p.title','like','%'.$this->search.'%')));
        if ($this->filter !== '') {
            if ($accounts) { $this->filter==='active' ? $query->whereNull('archived_at') : $query->whereNotNull('archived_at'); }
            else { $types=array_filter(['photo','carousel','story','short_video','reel','video','link','text','other'],fn($t)=>Social::category($t)===$this->filter); $query->whereIn('p.content_type',$types); }
        }
        if ($accounts && $visibilityReady && $this->visibility !== '') {
            $query->where('show_on_website', $this->visibility === 'visible' ? 1 : 0);
        }
        $pageSize = in_array($this->perPage, [10, 15, 25], true) ? $this->perPage : 15;
        if ($accounts && $visibilityReady) {
            $rows = $query->orderByDesc('show_on_website')->orderBy('display_order')->orderByDesc('id')->paginate($pageSize);
        } else {
            $rows = $query->orderByDesc($accounts?'id':'p.published_at')->orderByDesc($accounts?'id':'p.id')->paginate($pageSize);
        }
        $latestMetrics = collect();
        if (!$accounts && $rows->isNotEmpty()) {
            $latestMetrics = DB::table('tvclub_social_post_observations')
                ->whereIn('post_id', $rows->pluck('id'))
                ->orderByDesc('observed_at')->orderByDesc('id')
                ->get(['post_id', 'views', 'likes', 'comments', 'shares'])
                ->unique('post_id')
                ->keyBy('post_id');
        }
        return view('livewire.admin.social.catalog',[
            'accounts'=>$accounts,'visibilityReady'=>$visibilityReady,'rows'=>$rows,'latestMetrics'=>$latestMetrics,
            'choices'=>!$accounts && $this->open ? DB::table('tvclub_social_accounts')
                ->where(fn ($q)=>$q->whereNull('archived_at')->when($this->id,fn ($q)=>$q->orWhereIn('id',
                    DB::table('tvclub_social_posts')->select('account_id')->where('id',$this->id))))
                ->orderBy('display_name')->get() : [],
            'observations'=>!$accounts && $this->open && $this->id ? DB::table('tvclub_social_post_observations')->where('post_id',$this->id)->orderByDesc('id')->limit(10)->get() : [],
        ]);
    }
}
