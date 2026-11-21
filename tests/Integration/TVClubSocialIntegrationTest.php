<?php

namespace Tests\Integration;

use App\Exceptions\TVClubSocialException;
use App\Livewire\Admin\TVClub\Social\AccountForm;
use App\Livewire\Admin\TVClub\Social\Observations;
use App\Livewire\Admin\TVClub\Social\PostForm;
use App\Livewire\Admin\TVClub\Social\Reports;
use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialEvent;
use App\Models\TVClubSocialReportVersion;
use App\Models\User;
use App\Services\TVClubSocialDashboard;
use App\Services\TVClubSocialService;
use App\Support\MonthlyActivityRules;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\Support\EquipmentScenarioTestCase;

class TVClubSocialIntegrationTest extends EquipmentScenarioTestCase
{
    protected function cleanUpEquipmentFixtures(): void
    {
        $this->primary->transaction(function (): void {
            $accounts = $this->primary->table('tvclub_social_accounts')->where('account_key', $this->prefix)->pluck('id');
            $posts = $this->primary->table('tvclub_social_posts')->whereIn('account_id', $accounts)->pluck('id');
            $this->primary->table('tvclub_social_events')->whereIn('actor_id', $this->owned['users'])->delete();
            $this->primary->table('tvclub_social_report_versions')->whereIn('published_by', $this->owned['users'])->delete();
            foreach (['post' => $posts, 'account' => $accounts] as $type => $ids) {
                $table = $this->primary->table('tvclub_social_'.$type.'_observations')->whereIn($type.'_id', $ids);
                foreach ((clone $table)->orderByDesc('id')->pluck('id') as $id) {
                    (clone $table)->where('id', $id)->delete();
                }
            }
            $this->primary->table('tvclub_social_posts')->whereIn('id', $posts)->delete();
            $this->primary->table('tvclub_social_accounts')->whereIn('id', $accounts)->delete();
        });
        parent::cleanUpEquipmentFixtures();
    }

    private function service(): TVClubSocialService
    {
        return app(TVClubSocialService::class);
    }

    private function club(): User
    {
        return $this->actor(User::ROLE_TVCLUB);
    }

    private function account(string $platform = 'tiktok', ?User $actor = null): TVClubSocialAccount
    {
        return $this->service()->createAccount($actor ?? $this->club(), $platform, $this->prefix, strtolower($this->prefix), 'Synthetic account');
    }

    private function time(string $date = '2026-10-01 00:00:00.123456'): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Jakarta');
    }

    private function postInput(string $url = 'https://tiktok.com/@wpitv/video/123?tracking=1'): array
    {
        return ['url' => $url, 'title' => 'Synthetic title', 'content_type' => 'short_video', 'responsible_label' => 'Synthetic coordinator'];
    }

    private function rejects(string $class, callable $operation, ?string $code = null): void
    {
        try {
            $operation();
            $this->fail('Unexpected success.');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($class, $exception);
            if ($code !== null) {
                $this->assertSame($code, $exception->errorCode);
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function test_policy_discovery_and_service_rbac(): void
    {
        $admin = $this->actor(User::ROLE_SUPER_ADMIN);
        $account = $this->account('tiktok', $admin);
        $this->assertTrue(Gate::forUser($admin)->allows('view', $account));
        $this->assertTrue(Gate::forUser($this->club())->allows('export', TVClubSocialReportVersion::class));
        $this->assertTrue(Gate::forUser($this->club())->allows('manageContent', $account));
        $this->rejects(AuthorizationException::class, fn () => $this->service()->savePost($admin, $account->id, null, 0, $this->postInput(), $this->time()));
        $this->rejects(AuthorizationException::class, fn () => $this->service()->publish($admin, '2026-10', 0));
        foreach ([User::ROLE_PRODUCTION, User::ROLE_NEWS] as $role) {
            $actor = $this->actor($role);
            $this->assertFalse(Gate::forUser($actor)->allows('view', $account));
            $this->rejects(AuthorizationException::class, fn () => $this->account('instagram', $actor));
        }
        $this->primary->table('users')->where('id', $this->club()->id)->update(['status' => false]);
        $this->rejects(AuthorizationException::class, fn () => $this->account('facebook'));
    }

    public function test_platform_accounts_urls_duplicate_and_owner_scope(): void
    {
        $service = $this->service();
        $club = $this->club();
        $account = $this->account();
        $instagram = $this->account('instagram');
        $facebook = $this->account('facebook');
        $this->assertSame(3, TVClubSocialAccount::where('account_key', $this->prefix)->count());
        $this->assertSame('facebook', $facebook->platform);
        $post = $service->savePost($club, $account->id, null, 0, $this->postInput(), $this->time());
        $this->assertSame($account->id, $post->account_id);
        $this->assertSame('https://www.tiktok.com/@wpitv/video/123/', $post->url);
        $this->assertSame('2026-09-30 17:00:00.123456', $post->fresh()->getRawOriginal('published_at'));
        $this->rejects(TVClubSocialException::class, fn () => $service->savePost($club, $account->id, null, 0, $this->postInput('https://www.tiktok.com/@wpitv/video/123/'), $this->time()), 'SOCIAL_DUPLICATE');
        $this->rejects(ValidationException::class, fn () => $service->savePost($club, $account->id, null, 0, $this->postInput('https://tiktok.com.invalid/@wpitv/video/222'), $this->time()));
        $this->rejects(ModelNotFoundException::class, fn () => $service->archivePost($club, $instagram->id, $post->id, 1));
        $service->updateAccount($club, $account->id, 1, 'new-'.$this->prefix, 'New label');
        $this->rejects(TVClubSocialException::class, fn () => $service->archiveAccount($club, $account->id, 1), 'SOCIAL_STALE');
    }

    public function test_admin_account_form_preserves_identity_versions_and_audit(): void
    {
        $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN));
        $this->get(route('admin.tvclub.social-accounts.create'))->assertOk()->assertSee('Daftarkan Akun');
        $form = Livewire::test(AccountForm::class)->set('platform', 'instagram')
            ->set('accountKey', $this->prefix)->set('handle', $this->prefix)->set('displayName', 'Akun QA')
            ->set('profileUrl', 'https://instagram.com.example.test/wp')->call('save')->assertHasErrors('url');
        $form->set('profileUrl', 'https://instagram.com/wp/?tracking=1')->call('save')->assertHasNoErrors();
        $account = TVClubSocialAccount::where('account_key', $this->prefix)->sole();
        $this->assertSame('https://www.instagram.com/wp/', $account->profile_url);
        $this->get(route('admin.tvclub.social-accounts.index'))->assertOk()->assertSee('Akun QA');
        $old = Livewire::test(AccountForm::class, ['account' => $account]);
        Livewire::test(AccountForm::class, ['account' => $account])->set('displayName', 'Nama QA baru')->call('save')->assertHasNoErrors();
        $old->call('archive')->assertHasErrors('operation');
        Livewire::test(AccountForm::class, ['account' => $account->fresh()])->call('archive')->assertHasNoErrors();
        $this->assertNotNull($account->fresh()->archived_at);
        $this->assertSame(3, $account->fresh()->lock_version);
        $this->assertSame(['created', 'updated', 'archived'], TVClubSocialEvent::where('target_type', 'account')->where('target_id', $account->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_post_routes_filters_optional_sources_and_read_only_roles(): void
    {
        $account = $this->account();
        $other = $this->account('instagram');
        $service = $this->service();
        $service->savePost($this->club(), $other->id, null, 0, [
            ...$this->postInput('https://instagram.com/reel/ClubFixture/'), 'title' => 'Crosspost Instagram', 'content_type' => 'reel',
        ], $this->time());
        $this->actingAs($this->club());
        $this->get(route('admin.tvclub.social-posts.create'))->assertOk()->assertSee('Waktu publikasi');
        $ids = [];
        try {
            $ids['ms_program'] = $this->primary->table('ms_program')->insertGetId(['name' => 'Program QA', 'slug' => strtolower($this->prefix)]);
            $ids['tvclub_generations'] = $this->primary->table('tvclub_generations')->insertGetId(['name' => $this->prefix, 'year' => 2090]);
            $ids['tvclub_members'] = $this->primary->table('tvclub_members')->insertGetId([
                'generation_id' => $ids['tvclub_generations'], 'student_number' => $this->prefix, 'name' => 'Anggota QA',
            ]);
            Livewire::test(PostForm::class)->set('accountId', (string) $account->id)
                ->set('publishedAt', '2026-10-01T00:00')->set('type', 'short_video')->set('title', 'Unggahan QA')
                ->set('responsible', 'Koordinator QA')->set('url', 'https://tiktok.com/@wpitv/video/999?tracking=1')
                ->set('memberId', (string) $ids['tvclub_members'])->set('programId', (string) $ids['ms_program'])
                ->call('save')->assertHasNoErrors();
            $post = $account->posts()->sole();
            $this->assertSame($ids['tvclub_members'], $post->member_id);
            $this->assertSame($ids['ms_program'], $post->program_id);
            $this->assertSame('2026-09-30 17:00:00.000000', $post->getRawOriginal('published_at'));
            $url = route('admin.tvclub.social-posts.show', $post);
            $this->get(route('admin.tvclub.social-posts.index', ['platform' => 'tiktok', 'account' => $account->id, 'period' => '2026-10', 'type' => 'short_video']))
                ->assertOk()->assertSee('Unggahan QA')->assertDontSee('Crosspost Instagram');
            $this->get(route('admin.tvclub.social-posts.index', ['period' => '2026-09']))->assertOk()->assertDontSee('Unggahan QA');

            $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN))->get($url)->assertOk()->assertSee('Detail Unggahan')->assertDontSee('Simpan Unggahan');
            $this->get(route('admin.tvclub.social-posts.create'))->assertForbidden();
            Livewire::test(PostForm::class, ['post' => $post])->call('archive')->assertForbidden();
            foreach ([User::ROLE_PRODUCTION, User::ROLE_NEWS] as $role) {
                $this->actingAs($this->actor($role))->get($url)->assertForbidden();
                $this->get(route('admin.tvclub.social-accounts.index'))->assertForbidden();
                Livewire::test(PostForm::class, ['post' => $post])->assertForbidden();
            }
        } finally {
            foreach (array_reverse($ids, true) as $table => $id) {
                $this->primary->table($table)->where('id', $id)->delete();
            }
        }
        $post = $account->posts()->sole();
        $this->assertNull($post->member_id);
        $this->assertNull($post->program_id);
        $this->assertSame('Koordinator QA', $post->responsible_label);
        $this->actingAs($this->club());
        $old = Livewire::test(PostForm::class, ['post' => $post]);
        Livewire::test(PostForm::class, ['post' => $post])->set('title', 'Judul diperbarui')->call('save')->assertHasNoErrors();
        $old->call('archive')->assertHasErrors('operation');
        Livewire::test(PostForm::class, ['post' => $post->fresh()])->call('archive')->assertHasNoErrors();
        $this->assertNotNull($post->fresh()->archived_at);
    }

    public function test_post_form_rolls_back_when_audit_is_rejected(): void
    {
        $account = $this->account();
        $this->actingAs($this->club());
        TVClubSocialEvent::creating(fn () => false);
        Livewire::test(PostForm::class)->set('accountId', (string) $account->id)
            ->set('publishedAt', '2026-10-01T00:00:00')->set('type', 'short_video')
            ->set('title', 'Rollback QA')->set('responsible', 'Koordinator QA')->set('url', 'https://tiktok.com/@wpitv/video/123')
            ->call('save')->assertHasErrors('operation');
        $this->assertSame(0, $account->posts()->count());
    }

    public function test_observations_and_month_snapshot_choose_time_and_correction_id(): void
    {
        $service = $this->service();
        $club = $this->club();
        $account = $this->account();
        $post = $service->savePost($club, $account->id, null, 0, $this->postInput(), $this->time());
        $outside = $service->savePost($club, $account->id, null, 0, $this->postInput('https://tiktok.com/@wpitv/video/456'), $this->time('2026-11-01 00:00:00'));
        $early = $service->observePost($club, $account->id, $post->id, $this->time('2026-10-02'), ['views' => 0]);
        $lastTime = $this->time('2026-10-31 23:59:59.123456');
        $last = $service->observePost($club, $account->id, $post->id, $lastTime, ['views' => 7, 'shares' => PHP_INT_MAX]);
        $corrected = $service->observePost($club, $account->id, $post->id, $lastTime, ['views' => 8, 'likes' => 0], correctsId: $last->id, reason: 'Correction');
        $service->observePost($club, $account->id, $post->id, $this->time('2026-11-01'), ['views' => 99]);
        $followers = $service->observeAccount($club, $account->id, $lastTime, ['followers' => 0]);
        $this->assertSame(0, (int) $early->fresh()->views);
        $this->assertNull($early->fresh()->likes);
        $this->assertSame((string) PHP_INT_MAX, (string) $last->fresh()->shares);
        $this->assertSame('2026-10-31 16:59:59.123456', $corrected->fresh()->getRawOriginal('observed_at'));
        $this->rejects(ValidationException::class, fn () => $service->observeAccount($club, $account->id, $lastTime, ['followers' => -1]));
        $this->rejects(ValidationException::class, fn () => $service->observePost($club, $account->id, $post->id, $lastTime, ['followers' => 1]));
        $this->rejects(ValidationException::class, fn () => $service->observePost($club, $account->id, $post->id, $lastTime, [], correctsId: $corrected->id));
        $this->rejects(ModelNotFoundException::class, fn () => $service->observePost($club, $account->id, $outside->id, $lastTime, [], correctsId: $last->id, reason: 'Wrong owner'));
        $this->rejects(TVClubSocialException::class, fn () => $service->observePost($club, $account->id, $post->id, $lastTime, [], correctsId: $last->id, reason: 'Already corrected'), 'SOCIAL_STATE');
        $version = $service->publish($club, '2026-10', 0)->fresh();
        $this->assertSame(1, $version->revision_number);
        $this->assertSame([$post->id], array_column($version->snapshot['posts'], 'id'));
        $this->assertSame($corrected->id, $version->snapshot['posts'][0]['observation']['id']);
        $this->assertEquals(['views' => 8, 'likes' => 0, 'comments' => null, 'shares' => null], $version->snapshot['posts'][0]['observation']['metrics']);
        $this->assertSame($followers->id, $version->snapshot['accounts'][0]['observation']['id']);
        $this->assertSame(['followers' => 0], $version->snapshot['accounts'][0]['observation']['metrics']);
        $this->assertArrayNotHasKey('member_id', $version->snapshot['posts'][0]);
        $this->assertArrayNotHasKey('actor_id', $version->snapshot['posts'][0]['observation']);
        $this->assertSame($version->snapshot_hash, MonthlyActivityRules::snapshotHash($version->snapshot));
        $this->assertSame(2, $service->publish($club, '2026-10', 1, 'Revision')->revision_number);
        $this->assertSame($version->snapshot_hash, $version->fresh()->snapshot_hash);
        foreach ([$version, $corrected, $followers, TVClubSocialEvent::where('actor_id', $club->id)->firstOrFail()] as $history) {
            $this->rejects(LogicException::class, fn () => $history->saveQuietly());
            $this->rejects(LogicException::class, fn () => $history->deleteQuietly());
        }
    }

    public function test_audit_veto_rolls_back_snapshot(): void
    {
        TVClubSocialEvent::creating(fn () => false);
        $club = $this->club();
        $this->rejects(TVClubSocialException::class, fn () => $this->service()->publish($club, '2026-10', 0), 'SOCIAL_FAILURE');
        $this->assertSame(0, TVClubSocialReportVersion::where('published_by', $club->id)->count());
        $this->assertSame(0, TVClubSocialEvent::where('actor_id', $club->id)->count());
    }

    public function test_snapshot_veto_leaves_no_audit_and_real_check_is_enforced(): void
    {
        TVClubSocialReportVersion::creating(fn () => false);
        $club = $this->club();
        $this->rejects(TVClubSocialException::class, fn () => $this->service()->publish($club, '2026-10', 0), 'SOCIAL_FAILURE');
        $this->assertSame(0, TVClubSocialEvent::where('actor_id', $club->id)->count());
        $account = $this->account();
        try {
            $this->primary->table('tvclub_social_accounts')->where('id', $account->id)->update(['lock_version' => 0]);
            $this->fail('Expected CHECK rejection.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, (int) $exception->errorInfo[1]);
        }
        $this->assertSame(1, $account->fresh()->lock_version);
    }

    public function test_observation_panel_validates_time_metrics_and_immutable_correction(): void
    {
        $account = $this->account();
        $other = $this->account('facebook');
        $actor = $this->club();
        $post = $this->service()->savePost($actor, $account->id, null, 0, $this->postInput(), $this->time());
        $this->actingAs($actor)->get(route('admin.tvclub.social-posts.show', $post))->assertOk()->assertSee('Metrik Unggahan');
        $panel = Livewire::test(Observations::class, ['scope' => 'post', 'ownerId' => $post->id]);
        $panel->set('observedAt', '2026-09-30T23:59:59')->call('save')->assertHasErrors('observed_at');
        $panel->set('observedAt', '2026-10-01T00:00:01')->set('metrics.views', '-1')->call('save')->assertHasErrors('views');
        $panel->set('metrics.views', (string) PHP_INT_MAX)->set('metrics.likes', '0')->call('save')->assertHasNoErrors();
        $old = $post->observations()->sole();
        $this->assertSame((string) PHP_INT_MAX, (string) $old->views);
        $this->assertSame(0, (int) $old->likes);
        $this->assertNull($old->shares);
        $this->assertSame('2026-09-30 17:00:01.000000', $old->getRawOriginal('observed_at'));
        $this->assertNotNull($old->created_at);
        $panel->set('observedAt', '2026-10-01T00:00:01')->call('save')->assertHasErrors('operation');
        $snapshot = $this->service()->publish($actor, '2026-10', 0);
        $previousHash = $snapshot->snapshot_hash;
        $panel->call('correct', $old->id)->call('save')->assertHasErrors('reason');
        $panel->set('reason', 'Angka dikonfirmasi')->set('metrics.views', '17')->call('save')
            ->assertHasNoErrors()->assertSee('Angka dikonfirmasi');
        $replacement = $post->observations()->where('corrects_id', $old->id)->sole();
        $this->assertSame($old->observed_at->toISOString(), $replacement->observed_at->toISOString());
        $this->assertSame((string) PHP_INT_MAX, (string) $old->fresh()->views);
        $this->assertSame($previousHash, MonthlyActivityRules::snapshotHash($snapshot->fresh()->snapshot));
        $panel->call('correct', $old->id)->assertHasErrors('operation');
        $otherPost = $this->service()->savePost($actor, $other->id, null, 0, [
            ...$this->postInput('https://facebook.com/reel/456'), 'content_type' => 'reel',
        ], $this->time());
        Livewire::test(Observations::class, ['scope' => 'post', 'ownerId' => $otherPost->id])
            ->assertSee('Reaksi')->call('correct', $old->id)->assertHasErrors('operation');
    }

    public function test_account_observations_enforce_roles_and_rollback_audit(): void
    {
        $account = $this->account();
        $params = ['scope' => 'account', 'ownerId' => $account->id];
        $url = route('admin.tvclub.social-accounts.edit', $account);
        $this->actingAs($this->club())->get($url)->assertOk()->assertSee('Statistik Pengikut Akun');
        $form = Livewire::test(Observations::class, $params);
        $form->set('observedAt', '2026-10-31T23:59:59')->set('metrics.views', '2')->call('save')->assertHasErrors('metrics');
        $form->set('metrics', ['followers' => '0'])->call('save')->assertHasNoErrors();
        $old = $account->observations()->sole();
        $this->assertSame(0, (int) $old->followers);
        $this->assertSame('2026-10-31 16:59:59.000000', $old->getRawOriginal('observed_at'));

        $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN))->get($url)->assertOk()->assertSee('Histori Observasi')->assertDontSee('Simpan Observasi');
        Livewire::test(Observations::class, $params)->call('correct', $old->id)->assertForbidden();
        Livewire::test(Observations::class, $params)->call('save')->assertForbidden();
        foreach ([User::ROLE_PRODUCTION, User::ROLE_NEWS] as $role) {
            $this->actingAs($this->actor($role))->get($url)->assertForbidden();
            Livewire::test(Observations::class, $params)->assertForbidden();
        }
        $this->actingAs($this->club());
        TVClubSocialEvent::creating(fn () => false);
        Livewire::test(Observations::class, $params)->call('correct', $old->id)
            ->set('reason', 'Synthetic rollback')->set('metrics.followers', '1')->call('save')->assertHasErrors('operation');
        $this->assertSame(1, $account->observations()->count());
        $this->assertSame(0, TVClubSocialEvent::where('actor_id', $this->club()->id)->where('action', 'corrected')->count());
    }

    public function test_recap_filters_and_publish_preserve_the_original_snapshot(): void
    {
        $actor = $this->club();
        $service = $this->service();
        $account = $this->account();
        $other = $this->account('instagram');
        $post = $service->savePost($actor, $account->id, null, 0, [...$this->postInput(), 'title' => '=1+1'], $this->time('2025-12-01 00:00:00'));
        $service->savePost($actor, $account->id, null, 0, $this->postInput('https://tiktok.com/@wpitv/video/456'), $this->time('2026-01-01 00:00:00'));
        $time = $this->time('2025-12-31 23:59:59.123456');
        $old = $service->observePost($actor, $account->id, $post->id, $time, ['views' => 0, 'likes' => PHP_INT_MAX]);
        $service->observePost($actor, $account->id, $post->id, $this->time('2026-01-01 00:00:00'), ['views' => 999]);
        $service->observeAccount($actor, $account->id, $time, ['followers' => 50]);
        $index = route('admin.tvclub.social-reports.index', ['period' => '2025-12']);
        $this->actingAs($actor)->get($index)->assertOk()->assertSee('Terbitkan Revisi 1')->assertSee('1 dari 1 unggahan');
        $first = Livewire::withQueryParams(['period' => '2025-12'])->test(Reports::class)->assertSet('preparedPeriod', '2025-12');
        $stale = Livewire::withQueryParams(['period' => '2025-12'])->test(Reports::class);
        $first->set('account', (string) $other->id)->assertSee('Belum ada unggahan')->call('publish')->assertHasNoErrors();
        $stale->call('publish')->assertHasErrors('operation');
        $version = TVClubSocialReportVersion::where('period_start', '2025-12-01')->sole();
        $this->assertCount(1, $version->snapshot['posts']);
        $this->assertSame(0, $version->snapshot['posts'][0]['observation']['metrics']['views']);
        $this->assertSame($version->snapshot_hash, MonthlyActivityRules::snapshotHash($version->snapshot));
        $csv = fn ($report, string $type) => route('admin.tvclub.social-reports.csv', ['version' => $report, 'type' => $type]);
        $original = $this->post($csv($version, 'posts'))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString("'=1+1,0,".PHP_INT_MAX.',,,', $original);
        $this->assertStringNotContainsString('Synthetic coordinator', $original);
        $this->assertStringContainsString(',50,', $this->post($csv($version, 'followers'))->assertOk()->streamedContent());
        $service->observePost($actor, $account->id, $post->id, $time, ['views' => 7], correctsId: $old->id, reason: 'Koreksi sumber');
        $service->archiveAccount($actor, $account->id, 1);
        $running = $service->recap($actor, '2025-12');
        $this->assertSame(7, $running['posts'][0]['observation']['metrics']['views']);
        $this->assertSame(50, $running['accounts'][0]['observation']['metrics']['followers']);
        $this->assertNotNull($running['accounts'][0]['archived_at']);
        $this->assertSame($version->snapshot, $version->fresh()->snapshot);
        Livewire::withQueryParams(['period' => '2025-12'])->test(Reports::class)
            ->call('publish')->assertHasErrors('reason')->set('reason', 'Koreksi periode')->call('publish')->assertHasNoErrors();
        $this->assertSame([1, 2], TVClubSocialReportVersion::where('period_start', '2025-12-01')->orderBy('revision_number')->pluck('revision_number')->all());
        $show = route('admin.tvclub.social-reports.show', $version);
        $this->actingAs($this->actor(User::ROLE_SUPER_ADMIN))->get($show)->assertOk()->assertSee('Revisi 1')
            ->assertSee('CSV Metrik Unggahan')->assertSee('CSV Pengikut Akun')->assertDontSee('Terbitkan Revisi');
        $this->assertSame($original, $this->post($csv($version, 'posts'))->assertOk()->streamedContent());
        $latest = TVClubSocialReportVersion::where('period_start', '2025-12-01')->where('revision_number', 2)->sole();
        $this->assertStringContainsString("'=1+1,7,", $this->post($csv($latest, 'posts'))->assertOk()->streamedContent());
        $this->post($csv(999999999, 'posts'))->assertNotFound();
        $this->assertSame(['type' => 'posts', 'row_count' => 1], TVClubSocialEvent::where('action', 'exported')->where('target_id', $version->id)->latest('id')->firstOrFail()->changes);
        TVClubSocialEvent::creating(fn ($event) => $event->action === 'exported' ? false : null);
        $this->post($csv($version, 'posts'))->assertStatus(500);
        $this->assertSame(3, TVClubSocialEvent::where('action', 'exported')->where('target_id', $version->id)->count());
        Livewire::test(Reports::class)->call('publish')->assertForbidden();
        foreach ([User::ROLE_NEWS, User::ROLE_PRODUCTION] as $role) {
            $this->actingAs($this->actor($role))->get($index)->assertForbidden();
            $this->get($show)->assertForbidden();
            $this->post($csv($version, 'posts'))->assertForbidden();
            Livewire::test(Reports::class)->assertForbidden();
        }
    }

    public function test_social_dashboard_projects_period_metrics_with_role_query_limits(): void
    {
        $club = $this->club();
        $account = $this->account();
        $post = $this->service()->savePost($club, $account->id, null, 0, $this->postInput(), $this->time('2025-12-01'));
        $this->service()->observePost($club, $account->id, $post->id, $this->time('2025-12-31 23:59:59'), ['views' => 0]);
        $this->service()->observePost($club, $account->id, $post->id, $this->time('2026-01-01'), ['views' => 500]);
        $this->service()->observeAccount($club, $account->id, $this->time('2025-12-31'), ['followers' => 0]);
        $dashboard = app(TVClubSocialDashboard::class);
        DB::enableQueryLog();
        try {
            foreach ([User::ROLE_TVCLUB => 5, User::ROLE_SUPER_ADMIN => 2, User::ROLE_PRODUCTION => 0, User::ROLE_NEWS => 0] as $role => $limit) {
                $actor = $this->actor($role);
                DB::flushQueryLog();
                $data = $dashboard->get($actor, '2025-12');
                $this->assertCount($limit, collect(DB::getQueryLog())->filter(fn ($entry) => str_contains($entry['query'], 'tvclub_social_')));
                $this->actingAs($actor)->get(route('admin.dashboard'))->assertOk()
                    ->{($limit ? 'assertSee' : 'assertDontSee')}('Performa Media Sosial TVClub');
                if ($limit === 0) {
                    $this->assertNull($data);
                } else {
                    $this->assertSame(1, (int) $data['platforms']['tiktok']->posts);
                    if ($role === User::ROLE_TVCLUB) {
                        $metrics = $data['platforms']['tiktok'];
                        $this->assertSame('0', (string) $metrics->views);
                        $this->assertSame(1, (int) $metrics->views_coverage);
                        $this->assertNull($metrics->likes);
                        $this->assertSame(0, (int) $data['followers']->sole()['observation']->followers);
                    }
                }
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_oversized_snapshot_is_rejected_before_export_audit(): void
    {
        $actor = $this->club();
        $snapshot = ['period' => '2025-12', 'accounts' => [], 'posts' => array_fill(0, 5001, ['title' => 'Synthetic'])];
        $version = new TVClubSocialReportVersion;
        $version->forceFill([
            'period_start' => '2025-12-01', 'revision_number' => 1, 'schema_version' => 1,
            'snapshot' => $snapshot, 'snapshot_hash' => MonthlyActivityRules::snapshotHash($snapshot),
            'published_by' => $actor->id, 'published_at' => $version->freshTimestamp(),
        ])->save();

        $this->actingAs($actor)->post(route('admin.tvclub.social-reports.csv', [$version, 'posts']))->assertConflict();
        $this->assertFalse(TVClubSocialEvent::where('action', 'exported')->where('target_id', $version->id)->exists());
    }

    public function test_maximum_row_snapshot_streams_both_csv_files(): void
    {
        $actor = $this->club();
        $observed = '2025-12-31T16:00:00.123456Z';
        $accounts = [];
        for ($id = 1; $id <= 500; $id++) {
            $accounts[] = ['id' => $id, 'platform' => 'tiktok', 'handle' => '@synthetic'.$id,
                'observation' => ['observed_at' => $observed, 'metrics' => ['followers' => PHP_INT_MAX]]];
        }
        $posts = [];
        for ($id = 1; $id <= 5000; $id++) {
            $posts[] = ['id' => $id, 'account_id' => 1 + ($id % 500), 'title' => '=Synthetic '.$id,
                'description' => str_repeat('x', 2000), 'responsible_label' => 'PRIVATE_TEST_MARKER',
                'observation' => ['observed_at' => $observed, 'metrics' => ['views' => PHP_INT_MAX, 'likes' => 0, 'comments' => null]]];
        }
        $snapshot = ['period' => '2025-12', 'accounts' => $accounts, 'posts' => $posts];
        $version = new TVClubSocialReportVersion;
        $version->forceFill([
            'period_start' => '2025-12-01', 'revision_number' => 1, 'schema_version' => 1,
            'snapshot' => $snapshot, 'snapshot_hash' => MonthlyActivityRules::snapshotHash($snapshot),
            'published_by' => $actor->id, 'published_at' => $version->freshTimestamp(),
        ])->save();
        unset($snapshot, $posts, $accounts);
        $id = $version->id;
        unset($version);

        foreach (['posts' => 5000, 'followers' => 500] as $type => $expected) {
            $stream = tmpfile();
            try {
                gc_collect_cycles();
                memory_reset_peak_usage();
                $baseline = memory_get_usage();
                $start = hrtime(true);
                $response = $this->actingAs($actor)->post(route('admin.tvclub.social-reports.csv', [$id, $type]));
                $response->assertOk()->assertDownload("media-sosial-2025-12-r1-$type.csv");
                $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
                ob_start(static function (string $chunk) use ($stream): string {
                    fwrite($stream, $chunk);

                    return '';
                }, 8192);
                try {
                    $response->baseResponse->sendContent();
                } finally {
                    ob_end_flush();
                }
                $duration = (hrtime(true) - $start) / 1e6;
                $peak = memory_get_peak_usage();
                unset($response);
                rewind($stream);
                $this->assertSame("\xEF\xBB\xBF", fread($stream, 3));
                $header = fgetcsv($stream, escape: '');
                $this->assertSame($type === 'posts' ? 'judul' : 'followers', $header[4]);
                $count = 0;
                while (($cells = fgetcsv($stream, escape: '')) !== false) {
                    $count++;
                    $this->assertSame("'@synthetic".($type === 'posts' ? 1 + ($count % 500) : $count), $cells[3]);
                    $this->assertSame((string) PHP_INT_MAX, $cells[$type === 'posts' ? 5 : 4]);
                    if ($type === 'posts') {
                        $this->assertSame(["'=Synthetic ".$count, (string) PHP_INT_MAX, '0', '', ''], array_slice($cells, 4, 5));
                    }
                    $this->assertNotContains('PRIVATE_TEST_MARKER', $cells);
                }
                $this->assertSame($expected, $count);
                fwrite(STDOUT, sprintf("\nCSV %s: %d rows, %.1f ms, %.1f MiB peak (+%.1f MiB)\n", $type, $count, $duration, $peak / 1048576, ($peak - $baseline) / 1048576));
            } finally {
                fclose($stream);
            }
        }
    }

    public function test_two_processes_publish_the_same_revision_once(): void
    {
        $this->raceWorkers([], [$this->club()->id, $this->club()->id], 'SOCIAL_DUPLICATE');
        $version = TVClubSocialReportVersion::where('published_by', $this->club()->id)->sole();
        $event = TVClubSocialEvent::where('actor_id', $this->club()->id)->where('action', 'published')->sole();
        $this->assertSame(1, $version->revision_number);
        $this->assertSame($version->id, $event->target_id);
    }

    public function test_two_operators_cannot_correct_the_same_observation_twice(): void
    {
        $account = $this->account();
        $old = $this->service()->observeAccount($this->club(), $account->id, $this->time(), ['followers' => 0]);
        $peer = $this->club()->replicate();
        $peer->username = $this->prefix.'_9';
        $peer->save();
        $this->own('users', $peer->id);
        $this->raceWorkers(['account_id' => $account->id, 'observation_id' => $old->id], [$this->club()->id, $peer->id], 'SOCIAL_STATE');
        $replacement = $account->observations()->where('corrects_id', $old->id)->sole();
        $this->assertSame(2, $account->observations()->count());
        $this->assertSame(0, (int) $old->fresh()->followers);
        $this->assertSame(42, (int) $replacement->followers);
        $event = TVClubSocialEvent::whereIn('actor_id', [$this->club()->id, $peer->id])->where('action', 'corrected')->sole();
        $this->assertSame($replacement->id, $event->target_id);
    }

    private function raceWorkers(array $job, array $actors, string $conflict): void
    {
        $dir = storage_path('framework/testing/'.$this->prefix.'-social');
        mkdir($dir, 0700, true);
        $paths = [$dir.'/a', $dir.'/b'];
        try {
            foreach ($paths as $index => $path) {
                file_put_contents($path.'.json', json_encode([...$job, 'actor_id' => $actors[$index], 'go_file' => $path.'.go'], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, base_path('tests/Concurrency/Social/worker.php'), $path.'.json'], [0 => ['file', 'NUL', 'r'], 1 => ['file', $path.'.out', 'w'], 2 => ['file', $path.'.err', 'w']], $pipes, base_path(), null, ['bypass_shell' => true, 'create_new_console' => false]);
                $this->assertIsResource($process);
                $this->processes[] = $process;
            }
            [$left, $right] = array_map(fn ($path) => $this->waitForWorker($path, 'ready'), $paths);
            $this->assertNotSame($left['connection_id'], $right['connection_id']);
            $this->assertSame($left['uuid'], $right['uuid']);
            $this->assertSame($this->primary->selectOne('SELECT @@server_uuid AS uuid')->uuid, $left['uuid']);
            foreach ($paths as $path) {
                file_put_contents($path.'.go', 'go');
            }
            $this->assertEqualsCanonicalizing(['OK', $conflict], array_map(fn ($path) => $this->waitForWorker($path, 'result')['code'], $paths));
        } finally {
            $this->stopEquipmentWorkers();
        }
    }

    private function waitForWorker(string $path, string $phase): array
    {
        $deadline = microtime(true) + 25;
        do {
            clearstatcache();
            $lines = is_file($path.'.out') ? file($path.'.out', FILE_IGNORE_NEW_LINES) : [];
            foreach ($lines ?: [] as $line) {
                $message = json_decode($line, true);
                if (($message['phase'] ?? null) === 'fatal') {
                    throw new RuntimeException('SOCIAL_WORKER_FAILURE');
                }
                if (($message['phase'] ?? null) === $phase) {
                    return $message;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('SOCIAL_WORKER_TIMEOUT_'.$phase);
    }
}
