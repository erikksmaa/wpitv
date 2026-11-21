<?php

namespace Tests\Integration;

use App\Exceptions\MonthlyActivityException;
use App\Livewire\Admin\MonthlyReports\Show;
use App\Models\MonthlyActivityEvent;
use App\Models\MonthlyActivityReport;
use App\Models\User;
use App\Services\MonthlyActivityFiles;
use App\Services\MonthlyActivityLifecycle;
use App\Support\MonthlyActivityRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\ReleaseScenarioTestCase;

class MonthlyActivityAttachmentTest extends ReleaseScenarioTestCase
{
    protected function cleanUpReleaseFixtures(): void
    {
        $ids = MonthlyActivityReport::whereIn('created_by', $this->owned['users'])->pluck('id');
        $this->primary->transaction(function () use ($ids) {
            foreach (['events', 'versions', 'attachments', 'items'] as $table) {
                $this->primary->table('monthly_activity_'.$table)->whereIn('report_id', $ids)->delete();
            }
            $this->primary->table('monthly_activity_reports')->whereIn('id', $ids)->delete();
        });
        foreach ($ids as $id) {
            Storage::disk('local')->deleteDirectory('monthly-activity/'.$id);
        }
        parent::cleanUpReleaseFixtures();
    }

    private function draft(string $division = 'production'): MonthlyActivityReport
    {
        return app(MonthlyActivityLifecycle::class)->createDraft($this->actor($division === 'production' ? User::ROLE_PRODUCTION : User::ROLE_NEWS), $division, '2026-11');
    }

    private function pdf(string $name = 'Bukti.pdf', ?int $size = null): UploadedFile
    {
        $head = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $tail = "\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, $head.str_repeat(' ', $size ? $size - strlen($head.$tail) : 0).$tail);
    }

    private function upload(MonthlyActivityReport $report, UploadedFile $file, array $options = [])
    {
        return $this->from(route('admin.monthly-reports.show', $report->id))
            ->post(route('admin.monthly-reports.attachments.store', $report->id), [...['attachment' => $file, 'version' => $report->version], ...$options]);
    }

    private function item(MonthlyActivityReport $report): int
    {
        app(MonthlyActivityLifecycle::class)->saveItem($this->actor(), $report->id, $report->version, null, [
            'title' => 'Kegiatan QA', 'activity_date' => '2026-11-10', 'description' => 'Deskripsi sintetis',
            'outcome' => 'Selesai', 'obstacles' => 'Nihil', 'follow_up' => 'Nihil', 'contribution' => 'Dokumentasi',
        ]);

        return $report->items()->sole()->id;
    }

    public function test_upload_and_download_three_types_and_enforce_role_and_report_scope(): void
    {
        $report = $this->draft();
        $news = $this->draft('news');
        $item = $this->item($report);
        $this->actingAs($this->actor());
        foreach ([$this->pdf('Bukti laporan.pdf'), UploadedFile::fake()->image('Foto.png'), UploadedFile::fake()->image('Foto.jpg')] as $index => $upload) {
            $hash = hash_file('sha256', $upload->getRealPath());
            $this->upload($report->fresh(), $upload, $index === 0 ? [] : ['item_id' => $item])->assertSessionHasNoErrors();
            $file = $report->attachments()->latest('id')->firstOrFail();
            $this->assertSame($hash, $file->sha256);
            $this->assertSame($index === 0 ? null : $item, $file->item_id);
            $url = route('admin.monthly-reports.attachments.download', [$report->id, $file->id]);
            $response = $this->get($url)->assertOk()->assertDownload($file->display_name)->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($hash, hash('sha256', $response->streamedContent()));
            $this->assertStringNotContainsString('monthly-activity/', $response->headers->get('Content-Disposition'));
        }
        $this->get(route('admin.monthly-reports.attachments.download', [$report->id, 0]))->assertNotFound();
        $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN))->get($url)->assertOk();
        $this->upload($report->fresh(), $this->pdf())->assertForbidden();
        $this->actingAs($this->actor(User::ROLE_NEWS))->get($url)->assertForbidden();
        $this->get(route('admin.monthly-reports.attachments.download', [$news->id, $file->id]))->assertNotFound();
        $this->upload($news, $this->pdf(), ['item_id' => $item])->assertNotFound();
        $this->assertEmpty(Storage::disk('local')->allFiles('monthly-activity/'.$news->id));
        $this->actingAs($this->actor(User::ROLE_TVCLUB))->get($url)->assertForbidden();

        $this->primary->table('monthly_activity_attachments')->where('id', $file->id)->update(['storage_key' => 'monthly-activity/'.$report->id.'/../../private.pdf']);
        $this->actingAs($this->actor())->get($url)->assertGone();
        Livewire::test(Show::class, ['reportId' => $report->id])->assertSee('File tidak tersedia')->assertDontSee('private.pdf');
    }

    public function test_validation_exact_size_and_stale_cleanup(): void
    {
        $report = $this->draft();
        $this->actingAs($this->actor());
        foreach ([
            $this->pdf('wrong.png'), $this->pdf('proof.html'), $this->pdf('proof.svg'), $this->pdf('proof.exe'),
            UploadedFile::fake()->createWithContent('bad.pdf', "%PDF-1.4\ninvalid"),
            UploadedFile::fake()->createWithContent('bad.png', "\x89PNG\x0D\x0A\x1A\x0Ainvalid"),
            $this->pdf(size: MonthlyActivityFiles::MAX_BYTES + 1),
        ] as $invalid) {
            $this->upload($report, $invalid)->assertSessionHasErrors('attachment');
        }
        $this->assertEmpty(Storage::disk('local')->allFiles('monthly-activity/'.$report->id));
        $this->upload($report, $this->pdf(size: MonthlyActivityFiles::MAX_BYTES))->assertSessionHasNoErrors();
        $this->assertSame(MonthlyActivityFiles::MAX_BYTES, $report->attachments()->sole()->size_bytes);
        $this->upload($report, $this->pdf())->assertSessionHasErrors('attachment');
        $this->assertCount(1, Storage::disk('local')->allFiles('monthly-activity/'.$report->id));
    }

    public function test_detach_releases_draft_reference_and_preserves_snapshot_access(): void
    {
        $service = app(MonthlyActivityLifecycle::class);
        $report = $this->draft();
        $item = $this->item($report);
        $this->actingAs($this->actor());
        $this->upload($report->fresh(), $this->pdf(), ['item_id' => $item])->assertSessionHasNoErrors();
        $this->upload($report->fresh(), $this->pdf('Bukti tambahan.pdf'))->assertSessionHasNoErrors();
        $file = $report->attachments()->where('item_id', $item)->sole();
        $unused = $report->attachments()->whereNull('item_id')->sole();
        Livewire::test(Show::class, ['reportId' => $report->id])->call('detachAttachment', $unused->id)->assertHasNoErrors();
        Storage::disk('local')->assertMissing($unused->storage_key);
        $submitted = $service->submit($this->actor(), $report->id, $report->fresh()->version);
        $this->upload($submitted, $this->pdf())->assertSessionHasErrors('attachment');
        Livewire::test(Show::class, ['reportId' => $report->id])->call('detachAttachment', $file->id)->assertForbidden();
        $revision = $service->requestRevision($this->actor(User::ROLE_SUPER_ADMIN), $report->id, $submitted->version, 'Periksa bukti lama.');
        $history = route('admin.monthly-reports.attachments.historical', [$report->id, 1, $file->id]);
        $this->get($history)->assertOk();
        Livewire::test(Show::class, ['reportId' => $report->id])->call('detachAttachment', $file->id)->assertHasNoErrors();
        $this->assertCount(0, $report->attachments()->attached()->get());
        $this->assertSame($revision->version + 1, $report->fresh()->version);
        $this->assertNull($file->fresh()->item_id);
        $this->get(route('admin.monthly-reports.attachments.download', [$report->id, $file->id]))->assertNotFound();
        $this->get($history)->assertOk()->assertDownload('Bukti.pdf');
        $this->assertSame($item, $report->versions()->sole()->snapshot['attachments'][0]['item_id']);
        $service->deleteItem($this->actor(), $report->id, $report->fresh()->version, $item);
        $service->updateDraft($this->actor(), $report->id, $report->fresh()->version, 'Nihil setelah revisi.');
        $service->submit($this->actor(), $report->id, $report->fresh()->version);
        $this->assertSame([], $report->versions()->where('revision_number', 2)->sole()->snapshot['attachments']);
        $this->get(route('admin.monthly-reports.attachments.historical', [$report->id, 2, $file->id]))->assertNotFound();
        $this->actingAs($this->actor(User::ROLE_NEWS))->get($history)->assertForbidden();
        $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN))->get($history)->assertOk();
        Storage::disk('local')->delete($file->storage_key);
        $this->get($history)->assertGone();
    }

    public function test_audit_failure_removes_staged_file_and_rolls_back_metadata(): void
    {
        $report = $this->draft();
        $this->actingAs($this->actor());
        MonthlyActivityEvent::creating(fn () => false);
        $this->upload($report, $this->pdf())->assertSessionHasErrors('attachment');
        $this->assertSame(1, $report->fresh()->version);
        $this->assertSame(0, $report->attachments()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles('monthly-activity/'.$report->id));
    }

    public function test_report_rejects_the_101st_active_attachment(): void
    {
        $report = $this->draft();
        $this->actingAs($this->actor());
        $metadata = [
            'report_id' => $report->id, 'display_name' => 'Synthetic.pdf', 'disk' => 'local',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64),
            'status' => 'ready', 'created_at' => $report->freshTimestamp(), 'updated_at' => $report->freshTimestamp(),
        ];
        $this->primary->table('monthly_activity_attachments')->insert(array_map(
            fn ($index) => [...$metadata, 'storage_key' => 'synthetic-cap-'.$index],
            range(1, MonthlyActivityRules::ATTACHMENT_LIMIT),
        ));
        $this->upload($report, $this->pdf())->assertSessionHasErrors('attachment');
        $this->assertSame(MonthlyActivityRules::ATTACHMENT_LIMIT, $report->attachments()->attached()->count());
        $this->assertSame($report->version, $report->fresh()->version);
        $this->assertSame(1, $report->events()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles('monthly-activity/'.$report->id));
    }

    public function test_submit_during_staging_blocks_upload_and_discards_file(): void
    {
        $report = $this->draft();
        $service = app(MonthlyActivityLifecycle::class);
        $actor = $this->actor();
        $report = $service->updateDraft($actor, $report->id, $report->version, 'Tidak ada kegiatan.');
        $files = new MonthlyActivityFiles;
        $mock = $this->partialMock(MonthlyActivityFiles::class);
        $mock->shouldReceive('stage')->once()->andReturnUsing(function ($upload, $id) use ($files, $service, $actor, $report) {
            $staged = $files->stage($upload, $id);
            $service->submit($actor, $id, $report->version);

            return $staged;
        });
        try {
            $service->uploadAttachment($actor, $report, $report->version, null, $this->pdf());
            $this->fail('Expected stale upload.');
        } catch (MonthlyActivityException $exception) {
            $this->assertSame('MONTHLY_STALE', $exception->errorCode);
        }
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertSame([], $report->versions()->sole()->snapshot['attachments']);
        $this->assertEmpty(Storage::disk('local')->allFiles('monthly-activity/'.$report->id));
    }
}
