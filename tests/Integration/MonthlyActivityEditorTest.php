<?php

namespace Tests\Integration;

use App\Exceptions\MonthlyActivityException;
use App\Livewire\Admin\MonthlyReports\Index;
use App\Livewire\Admin\MonthlyReports\Show;
use App\Models\MonthlyActivityEvent;
use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Services\MonthlyActivityCandidates;
use App\Services\MonthlyActivityDashboard;
use App\Services\MonthlyActivityLifecycle;
use App\Support\MonthlyActivityRules;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\EquipmentScenarioTestCase;

class MonthlyActivityEditorTest extends EquipmentScenarioTestCase
{
    private array $sources = [];

    protected function cleanUpEquipmentFixtures(): void
    {
        $ids = MonthlyActivityReport::whereIn('created_by', $this->owned['users'])->pluck('id');
        $this->primary->transaction(function () use ($ids) {
            foreach (['events', 'versions', 'attachments', 'items'] as $table) {
                $this->primary->table('monthly_activity_'.$table)->whereIn('report_id', $ids)->delete();
            }
            $this->primary->table('monthly_activity_reports')->whereIn('id', $ids)->delete();
            foreach (['live_streams', 'wpitv_agendas', 'tr_perliputan', 'ms_news', 'ms_video', 'ms_program'] as $table) {
                $this->primary->table($table)->whereIn('id', $this->sources[$table] ?? [])->delete();
            }
        });
        parent::cleanUpEquipmentFixtures();
    }

    private function source(string $table, array $data): int
    {
        $id = (int) $this->primary->table($table)->insertGetId($data);
        $this->sources[$table][] = $id;

        return $id;
    }

    private function draft(string $division = 'production'): MonthlyActivityReport
    {
        return app(MonthlyActivityLifecycle::class)->createDraft($this->actor($division === 'production' ? User::ROLE_PRODUCTION : User::ROLE_NEWS), $division, '2026-11');
    }

    private function activity(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Manual '.$this->prefix, 'activity_date' => '2026-11-30', 'description' => 'Latihan',
            'outcome' => 'Selesai', 'obstacles' => 'Tidak ada', 'follow_up' => 'Tidak ada', 'contribution' => 'Penyuntingan',
        ], $overrides);
    }

    public function test_dashboard_widget_scopes_month_and_bounds_the_review_queue(): void
    {
        $service = app(MonthlyActivityLifecycle::class);
        $dashboard = app(MonthlyActivityDashboard::class);
        $month = now('Asia/Jakarta')->format('Y-m');
        $production = $service->createDraft($this->actor(), 'production', $month);
        $news = $this->actor(User::ROLE_NEWS);
        for ($offset = 0; $offset < 6; $offset++) {
            $period = now('Asia/Jakarta')->startOfMonth()->subMonths($offset)->format('Y-m');
            $report = $service->createDraft($news, 'news', $period);
            $report = $service->updateDraft($news, $report->id, $report->version, 'Nihil.');
            $service->submit($news, $report->id, $report->version);
        }
        $this->primary->enableQueryLog();
        foreach ([User::ROLE_PRODUCTION => [1, ['production']], User::ROLE_NEWS => [1, ['news']], User::ROLE_SUPER_ADMIN => [3, ['production', 'news']], User::ROLE_TVCLUB => [0, []]] as $role => [$limit, $divisions]) {
            $this->primary->flushQueryLog();
            $data = $dashboard->get($this->actor($role));
            $this->assertCount($limit, $this->primary->getQueryLog());
            $this->assertSame($divisions, $data === null ? [] : array_values($data['divisions']));
            $this->assertSame(count($divisions), $data === null ? 0 : $data['current']->count());
            if ($role === User::ROLE_SUPER_ADMIN) {
                $this->assertSame(6, $data['waiting']);
                $this->assertCount(5, $data['latest']);
            }
        }
        $this->primary->disableQueryLog();
        $this->actingAs($this->actor())->get(route('admin.dashboard'))->assertOk()->assertSee('Lanjutkan Laporan')->assertSee(route('admin.monthly-reports.show', $production->id));
        $this->actingAs($this->actor(User::ROLE_TVCLUB))->get(route('admin.dashboard'))->assertOk()->assertDontSee('Laporan Kegiatan Bulanan');
    }

    public function test_official_csv_and_print_use_only_the_approved_version_and_report_policy(): void
    {
        $report = $this->draft();
        $actor = $this->actor();
        $reviewer = $this->actor(User::ROLE_SUPER_ADMIN);
        $service = app(MonthlyActivityLifecycle::class);
        $csv = route('admin.monthly-reports.csv', $report);
        $print = route('admin.monthly-reports.print', $report);
        $this->actingAs($actor);
        $this->post($csv)->assertConflict();
        $this->get($print)->assertConflict();
        $report = $service->saveItem($actor, $report->id, $report->version, null, $this->activity([
            'title' => '=1+1', 'outcome' => '+2', 'obstacles' => '-3', 'follow_up' => '@SUM(4)', 'description' => "\t=5",
        ]));
        $report = $service->uploadAttachment($actor, $report, $report->version, $report->items()->sole()->id,
            UploadedFile::fake()->createWithContent('Bukti.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF\n"));
        $file = $report->attachments()->sole();
        $report = $service->submit($actor, $report->id, $report->version);
        $this->post($csv)->assertConflict();
        $this->get($print)->assertConflict();
        $report = $service->requestRevision($reviewer, $report->id, $report->version, 'Periksa dokumen.');
        $this->post($csv)->assertConflict();
        $this->get($print)->assertConflict();
        $report = $service->submit($actor, $report->id, $report->version);
        $service->approve($reviewer, $report->id, $report->version);
        $approved = $report->versions()->where('revision_number', 2)->sole();
        $this->primary->table('monthly_activity_items')->where('report_id', $report->id)->update(['title' => 'Isi di luar snapshot']);

        $body = $this->post($csv)->assertOk()->assertDownload('laporan-production-2026-11.csv')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        foreach (["'=1+1", "'+2", "'-3", "'@SUM(4)"] as $cell) {
            $this->assertStringContainsString($cell, $body);
        }
        foreach ([$actor->name, $file->storage_key, 'Bukti.pdf', 'Isi di luar snapshot'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $body);
        }
        $this->get($print)->assertOk()->assertSee('Versi kiriman 2')->assertSee('=1+1')->assertSee('Bukti.pdf')
            ->assertSee(route('admin.monthly-reports.attachments.historical', [$report->id, 2, $file->id]))
            ->assertDontSee('Isi di luar snapshot')->assertDontSee($file->storage_key);
        $this->get(route('admin.monthly-reports.attachments.historical', [$report->id, 1, $file->id]))->assertDownload('Bukti.pdf');
        $event = $report->events()->where('changes->operation', 'report_exported')->sole();
        $this->assertSame($approved->id, $event->report_version_id);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame(1, $event->changes['row_count']);
        foreach ([User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $this->actingAs($this->actor($role))->post($csv)->assertForbidden();
            $this->get($print)->assertForbidden();
        }
        $this->actingAs($reviewer)->get($print)->assertOk();
        $this->assertSame($approved->snapshot_hash, $approved->fresh()->snapshot_hash);
    }

    public function test_review_workflow_uses_immutable_revisions_and_rejects_duplicate_actions(): void
    {
        $report = $this->draft();
        $service = app(MonthlyActivityLifecycle::class);
        $reviewer = $this->actor(User::ROLE_SUPER_ADMIN);
        $this->actingAs($this->actor());
        $author = Livewire::test(Show::class, ['reportId' => $report->id]);
        $author->call('submitReport')->assertHasErrors('nil_reason');
        $author->call('newItem')->set('activity', $this->activity())->call('saveItem')->assertHasNoErrors();
        $secondTab = Livewire::test(Show::class, ['reportId' => $report->id]);
        $author->call('submitReport')->assertHasNoErrors()->assertSee('Versi Kiriman 1');
        $secondTab->call('submitReport')->assertHasErrors('report')->assertSee('Muat ulang laporan');
        $first = $report->versions()->sole();
        $this->assertSame(MonthlyActivityRules::snapshotHash($first->snapshot), $first->snapshot_hash);
        $author->call('approveReport')->assertForbidden();

        $this->actingAs($reviewer)->get(route('admin.monthly-reports.index', ['status' => 'submitted']))->assertOk()->assertSee('Antrean Review')->assertSee('Dikirim');
        $review = Livewire::test(Show::class, ['reportId' => $report->id])->assertSee('Review Versi Kiriman 1');
        $oldReview = Livewire::test(Show::class, ['reportId' => $report->id]);
        $review->call('requestRevision')->assertHasErrors('reason')
            ->set('reviewReason', 'Perjelas hasil kegiatan.')->call('requestRevision')->assertHasNoErrors();
        $event = $report->events()->where('action', 'revision_requested')->sole();
        $this->assertSame($first->id, $event->report_version_id);
        $this->assertSame($reviewer->id, $event->actor_id);
        $review->call('approveReport')->assertHasErrors('report');

        $this->actingAs($this->actor());
        $item = $report->items()->sole();
        Livewire::test(Show::class, ['reportId' => $report->id])->assertSee('Perjelas hasil kegiatan.')
            ->call('editItem', $item->id)->set('activity.outcome', 'Hasil baru')->call('saveItem')->assertHasNoErrors()
            ->call('submitReport')->assertHasNoErrors()->assertSee('Versi Kiriman 2');
        $this->assertSame('Selesai', $first->fresh()->snapshot['items'][0]['outcome']);

        $this->actingAs($reviewer);
        $oldReview->call('approveReport')->assertHasErrors('report');
        $latest = Livewire::test(Show::class, ['reportId' => $report->id]);
        $duplicate = Livewire::test(Show::class, ['reportId' => $report->id]);
        $latest->call('openRevision', 1)->assertSee('Versi Kiriman 1')->assertDontSee('Hasil baru')
            ->call('openLatestRevision')->assertSee('Hasil baru')->call('approveReport')->assertHasNoErrors()
            ->assertSee('Disetujui oleh');
        $duplicate->call('approveReport')->assertHasErrors('report');
        $this->assertSame(2, $report->versions()->count());
        $this->assertSame(1, $report->events()->where('action', 'approved')->count());
        $this->assertSame('approved', $report->fresh()->status);
        $this->assertSame($report->versions()->where('revision_number', 2)->sole()->id, $report->events()->where('action', 'approved')->sole()->report_version_id);
    }

    public function test_news_can_submit_nil_report_and_changed_role_cannot_approve(): void
    {
        $report = $this->draft('news');
        $this->actingAs($this->actor(User::ROLE_NEWS));
        Livewire::test(Show::class, ['reportId' => $report->id])->set('nilReason', 'Tidak ada kegiatan pada periode ini.')
            ->call('saveNilReason')->assertHasNoErrors()->call('submitReport')->assertHasNoErrors()->assertSee('Alasan nihil');
        $this->assertSame([], $report->versions()->sole()->snapshot['items']);
        $this->actingAs($this->actor())->get(route('admin.monthly-reports.show', $report->id))->assertForbidden();
        $reviewer = $this->actor(User::ROLE_SUPER_ADMIN);
        $this->actingAs($reviewer);
        $review = Livewire::test(Show::class, ['reportId' => $report->id]);
        $this->primary->table('users')->where('id', $reviewer->id)->update(['role_id' => $this->actor(User::ROLE_NEWS)->role_id]);
        $review->call('approveReport')->assertForbidden();
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertSame(0, $report->events()->where('action', 'approved')->count());
    }

    public function test_routes_and_livewire_enforce_division_and_read_only_review(): void
    {
        $production = $this->draft();
        $news = $this->draft('news');
        $this->actingAs($this->actor())->get(route('admin.monthly-reports.index'))->assertOk();
        $this->get(route('admin.monthly-reports.show', $news->id))->assertForbidden();
        Livewire::test(Index::class)->set('division', 'news')->assertSee('Production')->assertDontSee('>News<', false);
        Livewire::test(Show::class, ['reportId' => $news->id])->assertForbidden();
        $this->actingAs($this->actor(User::ROLE_NEWS))->get(route('admin.monthly-reports.show', $production->id))->assertForbidden();
        Livewire::test(Index::class)->set('draftPeriod', '2026-11')->call('createDraft')->assertHasErrors('draftPeriod');
        Livewire::test(Index::class)->set('draftPeriod', '2026-12')->call('createDraft')->assertHasNoErrors();
        $this->assertTrue(MonthlyActivityReport::where('division', 'news')->whereDate('period_start', '2026-12-01')->exists());

        $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN))->get(route('admin.monthly-reports.show', $news->id))->assertOk();
        Livewire::test(Index::class)->assertSee('Production')->assertSee('News')->call('createDraft')->assertForbidden();
        Livewire::test(Show::class, ['reportId' => $production->id])->assertDontSee('Tambah Manual')->call('newItem')->assertForbidden();

        $this->actingAs($this->actor(User::ROLE_TVCLUB))->get(route('admin.monthly-reports.index'))->assertForbidden();
        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_manual_editor_validation_reordering_audit_and_stale_tab(): void
    {
        $report = $this->draft();
        $this->actingAs($this->actor());
        $tab = Livewire::test(Show::class, ['reportId' => $report->id]);
        $stale = Livewire::test(Show::class, ['reportId' => $report->id])->call('newItem')->set('activity', $this->activity());
        $tab->call('newItem')->set('activity', $this->activity(['activity_date' => '2026-12-01']))->call('saveItem')
            ->assertHasErrors('activity.activity_date')->set('activity', $this->activity())->call('saveItem')
            ->assertHasNoErrors()->assertSet('expectedVersion', 2);
        $stale->call('saveItem')->assertHasErrors('report')->assertSee('Muat ulang laporan');
        $first = $report->items()->sole();
        $tab->call('newItem')->set('activity', $this->activity())->call('saveItem')->assertHasErrors('confirmDuplicate')
            ->set('confirmDuplicate', true)->call('saveItem')->assertHasNoErrors();
        $second = $report->items()->latest('id')->firstOrFail();
        $tab->call('moveItem', $second->id, -1)->assertHasNoErrors();
        $this->assertSame([$second->id, $first->id], $report->items()->orderBy('position')->pluck('id')->all());
        $tab->call('editItem', $first->id)->set('activity.outcome', 'Hasil diperbarui')->call('saveItem')->assertHasNoErrors();
        $this->assertSame('Hasil diperbarui', $first->fresh()->outcome);
        $tab->call('removeItem', $second->id)->assertHasNoErrors();
        $this->assertSame(1, $report->items()->count());
        $this->assertSame(6, $report->fresh()->version);
        $this->assertSame(['item_added', 'item_added', 'item_moved', 'item_updated', 'item_deleted'], $report->events()->where('action', 'updated')->orderBy('id')->get()->pluck('changes.operation')->all());

        $service = app(MonthlyActivityLifecycle::class);
        $submitted = $service->submit($this->actor(), $report->id, 6);
        $tab->call('newItem')->assertForbidden();
        $service->approve($this->actor(User::ROLE_SUPER_ADMIN), $report->id, $submitted->version);
        Livewire::test(Show::class, ['reportId' => $report->id])->assertDontSee('Tambah Manual')->call('removeItem', $first->id)->assertForbidden();
    }

    public function test_production_candidates_group_coverage_and_exclude_video_evidence(): void
    {
        $report = $this->draft();
        $coverage = $this->source('tr_perliputan', [
            'submission_code' => $this->prefix, 'name' => 'PRIVATE APPLICANT', 'phone' => 'PRIVATE PHONE',
            'event_name' => $this->prefix.' Peliputan', 'event_date' => '2026-11-08',
            'location' => 'Synthetic', 'description' => 'PRIVATE DESCRIPTION', 'admin_note' => 'PRIVATE NOTE', 'status' => 'completed',
        ]);
        $agenda = fn (array $attributes = []) => $this->source('wpitv_agendas', array_replace([
            'title' => $this->prefix.' Agenda', 'type' => 'live', 'status' => 'completed', 'visibility' => 'internal',
            'agenda_date' => '2026-11-09', 'created_by' => $this->actor()->id,
        ], $attributes));
        $linkedAgenda = $agenda(['coverage_id' => $coverage, 'agenda_date' => '2026-11-07']);
        $agenda(['coverage_id' => $coverage]);
        $liveAgenda = $agenda();
        $program = $this->source('ms_program', ['name' => 'Synthetic', 'slug' => $this->prefix]);
        $video = fn (string $title, ?string $date) => $this->source('ms_video', [
            'title' => $this->prefix.' '.$title, 'program_id' => $program, 'air_date' => $date, 'youtube_id' => 'AbC_dE-1234',
        ]);
        $archive = $video('Archive', '2026-11-10');
        $standalone = $video('Tayangan mandiri', '2026-11-11');
        $video('No date', null);
        $this->source('live_streams', [
            'agenda_id' => $liveAgenda, 'archive_video_id' => $archive, 'youtube_id' => 'AbC_dE-1234', 'status' => 'ended',
            'ended_marked_at' => '2026-11-09 10:00:00',
            'status_changed_at' => '2026-11-09 10:00:00', 'created_at' => '2026-11-09 10:00:00', 'updated_at' => '2026-11-09 10:00:00',
        ]);

        $service = app(MonthlyActivityCandidates::class);
        $page = fn ($type) => $service->page($this->actor(), $report, $type, $this->prefix)->getCollection();
        $this->assertSame([$liveAgenda], $page('agenda')->pluck('id')->all());
        $this->assertSame([$coverage], $page('coverage')->pluck('id')->all());
        $this->assertSame('2026-11-07', $page('coverage')->first()['date']);
        $this->assertSame($linkedAgenda, $page('coverage')->first()['agenda_id']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($page('coverage')->all()));
        $this->assertSame([$standalone], $page('video')->pluck('id')->all());
        $newsReport = $this->draft('news');
        $this->assertSame([$archive], $service->page($this->actor(User::ROLE_NEWS), $newsReport, 'video', $this->prefix.' Archive')->getCollection()->pluck('id')->all());

        $this->actingAs($this->actor());
        $tab = Livewire::test(Show::class, ['reportId' => $report->id])
            ->set('candidateType', 'coverage')->assertSee($this->prefix.' Peliputan')
            ->call('chooseCandidate', 'coverage', $coverage)->assertSet('activity.activity_date', '2026-11-07');
        $original = $page('coverage')->first();
        $this->primary->table('tr_perliputan')->where('id', $coverage)->update(['event_name' => $this->prefix.' Baru']);
        $tab->set('activity', $this->activity())->call('saveItem')->assertHasErrors('activity');
        $tab->call('chooseCandidate', 'coverage', $coverage)->set('activity', $this->activity())->call('saveItem')->assertHasNoErrors()->assertSee('Sudah dipilih');
        $item = $report->items()->sole();
        $this->assertSame($coverage, $item->source_id);
        $this->primary->table('tr_perliputan')->where('id', $coverage)->update(['event_name' => $original['title']]);
        $tab->call('$refresh')->assertSee('Sumber berubah');
        $this->primary->table('wpitv_agendas')->where('coverage_id', $coverage)->update(['coverage_id' => null]);
        $this->primary->table('tr_perliputan')->where('id', $coverage)->delete();
        $tab->call('$refresh')->assertSee('Sumber tidak tersedia');
        $this->assertSame($this->activity()['title'], $item->fresh()->title);
        $this->assertSame($this->prefix.' Baru', $item->fresh()->source_context['title']);
    }

    public function test_news_wib_boundaries_and_explicit_editorial_video(): void
    {
        $report = $this->draft('news');
        $actor = $this->actor(User::ROLE_NEWS);
        foreach (['2026-10-31 16:59:59', '2026-10-31 17:00:00', '2026-11-30 16:59:59', '2026-11-30 17:00:00', null] as $index => $utc) {
            $id = $this->source('ms_news', ['title' => $this->prefix.' '.$index, 'slug' => $this->prefix.'-'.$index, 'content' => 'PRIVATE CONTENT', 'user_id' => $actor->id]);
            $this->primary->table('ms_news')->where('id', $id)->update(['created_at' => $utc === null ? null : $this->primary->raw('FROM_UNIXTIME('.strtotime($utc.' UTC').')')]);
            $news[$index] = $id;
        }
        $video = $this->source('ms_video', ['title' => $this->prefix.' Editorial', 'air_date' => '2026-11-01', 'youtube_id' => 'AbC_dE-1234']);
        $service = app(MonthlyActivityCandidates::class);
        $page = $service->page($actor, $report, 'news', $this->prefix)->getCollection();
        $this->assertSame([$news[2], $news[1]], $page->pluck('id')->all());
        $this->assertSame(['2026-11-30', '2026-11-01'], $page->pluck('date')->all());
        $this->assertStringNotContainsString('PRIVATE', json_encode($page->all()));
        $this->assertCount(0, $service->page($actor, $report, 'video', ''));
        $this->assertSame([$video], $service->page($actor, $report, 'video', 'Editorial')->getCollection()->pluck('id')->all());
        $this->assertCount(0, $service->page($actor, $report, 'coverage', ''));
        $this->actingAs($actor);
        Livewire::test(Show::class, ['reportId' => $report->id])->call('chooseCandidate', 'news', $news[0])->assertHasErrors('report');

        $context = $service->resolve($actor, $report, 'video', $video);
        MonthlyActivityEvent::creating(fn () => false);
        try {
            app(MonthlyActivityLifecycle::class)->saveItem($actor, $report->id, 1, null, $this->activity(), 'video', $video, MonthlyActivityRules::snapshotHash($context));
            $this->fail('Expected audit failure.');
        } catch (MonthlyActivityException $exception) {
            $this->assertSame('MONTHLY_FAILURE', $exception->errorCode);
        }
        $this->assertSame(1, $report->fresh()->version);
        $this->assertSame(0, $report->items()->count());
        $this->assertSame(1, $report->events()->count());
    }
}
