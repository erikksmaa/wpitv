<?php

namespace Tests\Unit;

use App\Livewire\Admin\Social\Catalog;
use App\Livewire\Admin\Social\Report;
use App\Models\Role;
use App\Models\User;
use App\Services\SocialReport as Social;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\TestCase;

class SocialPhaseOneTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            // Keep the repository guard active. All fixture queries use ephemeral SQLite.
            $app['config']->set('database.connections.mysql.database', 'wpitv_rebuild_testing');
            $app['config']->set('database.connections.social_test', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
            $app['config']->set('database.default', 'social_test');
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    public function test_publications_period_totals_and_reviewed_snapshot(): void
    {
        if (!in_array('sqlite',\PDO::getAvailableDrivers())) {
            $this->markTestSkipped('Requires pdo_sqlite for the in-memory social fixture.');
        }
        config(['database.default'=>'social_test','database.connections.social_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],
            'social-media.managers'=>['1'],'social-media.reviewers'=>['2']]);
        $schemas = [
            'accounts'=>'platform,handle,display_name,account_key,profile_url,archived_at,lock_version integer default 1,created_at,updated_at',
            'posts'=>'account_id integer,published_at,title,content_type,responsible_label,url,url_hash,description,archived_at,lock_version integer default 1,created_at,updated_at',
            'post_observations'=>'post_id,views,likes,shares,comments,actor_id,observed_at,created_at',
            'account_observations'=>'account_id,observed_at,followers,corrects_id',
            'events'=>'target_type,target_id integer,action,actor_id,changes,created_at',
            'reports'=>'period_start unique,status,author_id,draft,version_id,lock_version integer',
            'report_versions'=>'period_start,revision_number integer,schema_version,snapshot,snapshot_hash,published_by,published_at,reason',
        ];
        foreach ($schemas as $table=>$columns) { DB::statement("CREATE TABLE tvclub_social_$table (id integer primary key,$columns)"); }
        $actor = fn($id)=>(new User)->forceFill(['id'=>$id,'name'=>"Pengguna $id",'status'=>true])->setRelation('role',(new Role)->forceFill(['name'=>User::ROLE_NEWS]));
        $this->actingAs($actor(3));
        Livewire::test(Catalog::class,['section'=>'accounts'])->assertForbidden();
        $this->actingAs($actor(1));
        Livewire::test(Catalog::class,['section'=>'accounts'])->call('edit')->set('form.display_name','Studio')->set('form.handle','studio')->set('form.platform','youtube')->call('save')->assertHasNoErrors()
            ->call('edit',1)->set('form.display_name','Studio TV')->call('save')->assertSee('Studio TV');
        $posts=Livewire::test(Catalog::class,['section'=>'posts']);
        foreach (['2026-11-01T00:00','2026-12-01T00:00'] as $date) {
            $posts->call('edit')->set('form.account_id',1)->set('form.title','Acara')->set('form.published_at',$date)->set('form.views',0)->call('save')->assertHasNoErrors();
        }
        $this->assertCount(1,Social::publications('2026-11'));
        $posts->call('edit',1)->set('form.title','Acara kampus')->call('save')->assertHasNoErrors()->assertSee('Acara kampus');
        $report=Livewire::test(Report::class)->set('month','2026-11')->call('loadPeriod');
        $report->call('change','save')->assertHasNoErrors()->call('change','submit')->assertHasErrors(['analysis.insight']);
        $metrics=array_fill_keys(array_keys(Social::PLATFORMS),array_fill_keys(array_keys(Social::METRICS),0));
        $metrics['youtube']['start']=100;
        $metrics['youtube']['end']=120;
        $metrics['youtube']['er']=4;
        $metrics['instagram']['start']=null;
        $report->set('metrics',$metrics)->set('analysis',array_fill_keys(array_keys(Social::ANALYSIS),'Evaluasi periode'))->call('change','submit')->assertHasNoErrors();
        $report->call('change','approve')->assertForbidden();
        $this->actingAs($actor(2));
        Livewire::test(Report::class)->set('month','2026-11')->call('loadPeriod')->set('note','Lengkapi insight')->call('change','revise')->assertHasNoErrors();
        $this->actingAs($actor(1));
        Livewire::test(Report::class)->set('month','2026-11')->call('loadPeriod')->set('analysis.insight','Insight terbaru')->call('change','submit')->assertHasNoErrors();
        $posts->call('archive',1,2)->assertHasNoErrors();
        $this->assertCount(0,Social::publications('2026-11'));
        $this->actingAs($actor(2));
        Livewire::test(Report::class)->set('month','2026-11')->call('loadPeriod')->call('change','approve')->assertHasNoErrors()->call('version',1)->assertSee('Evaluasi periode');
        $final=json_decode(DB::table('tvclub_social_report_versions')->orderByDesc('id')->value('snapshot'),true);
        $this->assertSame(1,$final['counts']['total']['total']);
        $this->assertEquals(20,$final['growth']['total']);
        $this->assertEquals(1,$final['totals']['er']);
        $this->assertSame(2,$final['approved_by']['id']);
        $this->assertNull(Social::summary([],[])['totals']['start']);
        $this->actingAs($actor(1));
        $catalog=Livewire::test(Catalog::class,['section'=>'accounts'])->call('edit')->call('save')
            ->assertSee('Nama akun harus diisi.')->call('$set','open',false);
        for ($n=2;$n<=18;$n++) {
            DB::table('tvclub_social_accounts')->insert(['display_name'=>"Akun $n",'platform'=>'instagram','handle'=>"akun$n",'account_key'=>"akun$n"]);
        }
        $catalog->call('setPage',2)->assertSee('Studio TV')->set('platform','instagram')->assertDontSee('Studio TV')
            ->set('search','tidak ditemukan')->assertSee('Tidak ada data yang sesuai.');
        Livewire::test(Catalog::class,['section'=>'posts'])->set('filter','carousel')->assertSee('Tidak ada data yang sesuai.');
        DB::statement('CREATE TABLE roles (id integer primary key,name)');
        DB::statement('CREATE TABLE users (id integer primary key,name,status integer,role_id integer)');
        DB::table('roles')->insert(['id'=>1,'name'=>User::ROLE_NEWS]);
        foreach ([1,2,3] as $id) {
            DB::table('users')->insert(['id'=>$id,'name'=>"Pengguna $id",'status'=>1,'role_id'=>1]);
        }
        $this->withSession(['auth_started_at'=>time(),'auth_last_activity_at'=>time()]);
        $url = fn ($id,$format='preview')=>route('admin.social.preview',[$id,$format]);
        $this->get($url(1))->assertOk()->assertSee('Draft')->assertDontSee('Unduh Excel');
        $this->get(route('admin.social.draft','2026-11'))->assertRedirect(route('admin.social.preview',3));
        foreach (['xlsx','print'] as $format) { $this->get($url(1,$format))->assertStatus(409); }
        $this->get($url(3,'print'))->assertOk()->assertSee('Insight terbaru')->assertSee('20,00 %');
        $file = tempnam(sys_get_temp_dir(),'social-test-');
        try {
            $download=$this->get($url(3,'xlsx'))->assertOk()->assertDownload('Laporan Aktivitas Media Sosial - November 2026.xlsx');
            file_put_contents($file,$download->streamedContent());
            $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($file);
            $sheet=$book->getActiveSheet();
            $this->assertSame(['November 2026'],$book->getSheetNames());
            $this->assertNull($sheet->getCell('B8')->getValue());
            $this->assertEquals(0,$sheet->getCell('B9')->getValue());
            $this->assertSame('',$sheet->getCell('D8')->getCalculatedValue());
            $this->assertEquals(0.2,$sheet->getCell('D12')->getCalculatedValue());
            $this->assertEquals(0.01,$sheet->getCell('G12')->getCalculatedValue());
            $this->assertEquals(1,$sheet->getCell('G20')->getCalculatedValue());
            $this->assertSame('Pengguna 2',$sheet->getCell('B36')->getValue());
            $this->assertSame('Pengguna 1',$sheet->getCell('D36')->getValue());
            $this->assertSame('landscape',$sheet->getPageSetup()->getOrientation());
            $this->assertContains('C23:G23',$sheet->getMergeCells());
            $this->assertSame(1,$sheet->getDrawingCollection()->count());
        } finally { unlink($file); }
        $this->actingAs($actor(3));
        foreach (['preview','print','xlsx'] as $format) { $this->get($url(3,$format))->assertForbidden(); }
    }
}
