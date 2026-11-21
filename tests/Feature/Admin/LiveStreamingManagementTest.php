<?php

namespace Tests\Feature\Admin;

use App\Exceptions\LiveStreamOperationException;
use App\Livewire\Admin\Agenda\Form as AgendaForm;
use App\Livewire\Admin\LiveStreams\Form;
use App\Livewire\Admin\LiveStreams\Index;
use App\Livewire\Admin\LiveStreams\Show;
use App\Models\LiveStream;
use App\Models\LiveStreamEvent;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Isolated wpitv_testing only; inherited outer transaction rolls back every fixture. */
class LiveStreamingManagementTest extends TestCase
{
    private array $actors = [];

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('wpitv_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame('wpitv_test_runner@localhost', DB::selectOne('SELECT CURRENT_USER() AS account')->account);
        $this->assertTrue($this->app['db']->getSchemaBuilder()->hasTable('live_stream_events'));
        $this->prefix = 'LIVEIT_'.bin2hex(random_bytes(6));
        foreach (User::ADMIN_ROLES as $role) {
            $this->actors[$role] = User::factory()->create(['role_id' => Role::where('name', $role)->firstOrFail()->id, 'status' => true]);
        }
    }

    private function actor(string $role = User::ROLE_PRODUCTION): User
    {
        return $this->actors[$role];
    }

    private function agenda(array $input = []): WpitvAgenda
    {
        return WpitvAgenda::create(array_replace(['title' => $this->prefix, 'type' => 'live', 'agenda_date' => now('Asia/Jakarta')->addDays(3)->toDateString(), 'start_time' => '23:00', 'end_time' => '01:00', 'status' => 'scheduled', 'visibility' => 'internal', 'created_by' => $this->actor()->id], $input));
    }

    private function stream(?WpitvAgenda $agenda = null, string $id = 'AbC_dE-1234'): LiveStream
    {
        return app(LiveStreamLifecycle::class)->create($this->actor(), ($agenda ?? $this->agenda())->id, 'https://www.youtube.com/live/'.$id);
    }

    private function conflict(callable $action, string $code): void
    {
        try {
            $action();
            $this->fail('Expected a safe conflict.');
        } catch (LiveStreamOperationException $error) {
            $this->assertSame($code, $error->errorCode);
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_manual_creation_preserves_input_and_can_be_edited_and_published(): void
    {
        $agenda = $this->agenda();
        $agendaCount = WpitvAgenda::count();
        $date = now('Asia/Jakarta')->addDays(3)->toDateString();
        Livewire::actingAs($this->actor())->test(Form::class)
            ->assertSet('mode', 'manual')
            ->call('save')->assertHasErrors(['title', 'agenda_date', 'start_time'])
            ->set('title', $this->prefix.' manual')
            ->set('agenda_date', $date)->set('start_time', '23:30')->set('end_time', '01:15')
            ->set('mode', 'agenda')->set('agendaId', $agenda->id)
            ->set('mode', 'manual')->assertSet('title', $this->prefix.' manual')->assertSet('agendaId', $agenda->id)
            ->set('youtubeInput', 'AbC_dE-1234')->call('save')->assertHasNoErrors();
        $stream = LiveStream::where('manual_title', $this->prefix.' manual')->sole();
        $this->assertNull($stream->agenda_id);
        $this->assertNull($stream->manual_program_id);
        $this->assertSame($agendaCount, WpitvAgenda::count());
        $this->assertSame($date.' 16:30:00', $stream->manual_starts_at->toDateTimeString());
        $this->assertSame('manual', $stream->events()->sole()->after_values['live']['creation_mode']);
        $this->get(route('live.show', $stream))->assertNotFound();
        Livewire::actingAs($this->actor())->test(Form::class, ['streamId' => $stream->id])
            ->set('title', $this->prefix.' edited')->set('visibility', 'public')
            ->call('save')->assertHasNoErrors();
        $this->get(route('live.show', $stream))->assertOk()->assertSee($this->prefix.' edited');
        $this->get(route('live.index'))->assertOk()->assertSee($this->prefix.' edited');
        $this->actingAs($this->actor())->get(route('admin.live-streams.show', $stream))->assertOk()->assertSee('Sumber jadwal');
        $service = app(LiveStreamLifecycle::class);
        $this->conflict(fn () => $service->transition($this->actor(), $stream->id, 1, 'live'), 'LIVE_STALE');
        $live = $service->transition($this->actor(), $stream->id, 2, 'live');
        $ended = $service->transition($this->actor(), $stream->id, $live->version, 'ended');
        $this->assertSame('ended', $ended->status);
        $this->assertSame(4, $ended->events()->count());
    }

    public function test_manual_creation_validates_program_and_rolls_back_on_audit_failure(): void
    {
        $service = app(LiveStreamLifecycle::class);
        $input = [
            'title' => $this->prefix.' rollback', 'agenda_date' => now('Asia/Jakarta')->addDay()->toDateString(),
            'start_time' => '10:00', 'visibility' => 'internal',
        ];
        try {
            $service->create($this->actor(), null, null, 'manual', [...$input, 'program_id' => -1]);
            $this->fail('Expected invalid Program to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('program_id', $exception->errors());
        }
        $original = LiveStreamEvent::getEventDispatcher();
        LiveStreamEvent::setEventDispatcher(clone $original);
        LiveStreamEvent::creating(fn () => false);
        try {
            $this->conflict(fn () => $service->create($this->actor(), null, null, 'manual', $input), 'LIVE_DATABASE');
        } finally {
            LiveStreamEvent::setEventDispatcher($original);
        }
        $this->assertFalse(LiveStream::where('manual_title', $input['title'])->exists());
    }

    public function test_manual_stream_is_rescheduled_filtered_and_archived_with_existing_program(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor);
        $program = Program::create(['name' => $this->prefix, 'slug' => strtolower($this->prefix), 'status' => true]);
        $service = app(LiveStreamLifecycle::class);
        $schedule = ['agenda_date' => now('Asia/Jakarta')->addDays(2)->toDateString(), 'start_time' => '23:30', 'end_time' => null, 'visibility' => 'public'];
        $stream = $service->create($actor, null, 'https://youtu.be/AbC_dE-1234', 'manual', [...$schedule, 'title' => $this->prefix.' manual', 'program_id' => $program->id]);

        $this->assertSame($program->id, $stream->manual_program_id);
        $this->assertNull($stream->manual_ends_at);
        $stream = $service->transition($actor, $stream->id, $stream->version, 'cancelled', 'Synthetic cancellation');
        $stream = $service->transition($actor, $stream->id, $stream->version, 'scheduled', 'Synthetic reschedule', [...$schedule, 'end_time' => '01:00']);
        $this->assertNull($stream->agenda_id);
        $this->assertSame('scheduled', $stream->status);
        $this->get(route('program.show', $program->slug))->assertOk()->assertSee($stream->manual_title);
        $this->get(route('admin.live-streams.index', ['programId' => $program->id, 'from' => $schedule['agenda_date']]))
            ->assertOk()->assertSee($stream->manual_title);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee($stream->manual_title);
        $this->get(route('live.show', $stream))->assertOk()->assertSee('youtube-nocookie.com/embed/AbC_dE-1234', false);
        $stream = $service->transition($actor, $stream->id, $stream->version, 'ended', 'Synthetic administrative close');
        $video = Video::create(['title' => $this->prefix.' archive', 'youtube_id' => $stream->youtube_id, 'air_date' => today(), 'user_id' => $actor->id]);
        $stream = $service->linkArchive($actor, $stream->id, $stream->version, $video->id);
        $this->assertTrue($stream->fresh()->hasValidArchive());
        $this->get(route('live.index'))->assertOk()->assertSee($stream->manual_title);
        $stream = $service->updateVisibility($actor, $stream->id, $stream->version, 'internal');
        $this->assertFalse($stream->fresh()->hasValidArchive());
        $this->get(route('live.show', $stream))->assertNotFound();
        $this->assertSame(['created', 'status_changed', 'status_changed', 'status_changed', 'archive_linked', 'manual_updated'], $stream->events()->orderBy('id')->pluck('event_type')->all());
    }

    public function test_create_and_normal_lifecycle_write_correlated_audit(): void
    {
        $agenda = $this->agenda();
        $stream = $this->stream($agenda);
        $this->assertSame(1, $stream->events()->count());
        $this->assertSame($this->actor()->id, $stream->created_by);
        $this->assertSame('unknown', $stream->youtube_status);
        $this->assertTrue($stream->scheduled_ends_at->gt($stream->scheduled_starts_at));
        $service = app(LiveStreamLifecycle::class);
        $live = $service->transition($this->actor(), $stream->id, 1, 'live');
        $ended = $service->transition($this->actor(), $stream->id, $live->version, 'ended');
        $this->assertSame('completed', $agenda->fresh()->status);
        $this->assertSame('ended', $ended->status);
        $this->assertSame(3, $ended->events()->count());
        $this->assertNotNull($ended->live_marked_at);
        $this->assertNull($ended->archive_video_id);
        $this->assertSame(0, Video::where('title', $this->prefix)->count());
    }

    public function test_linked_agenda_form_updates_title_visibility_and_audit_together(): void
    {
        $stream = $this->stream();
        Livewire::actingAs($this->actor())->test(AgendaForm::class, ['agenda' => $stream->agenda])
            ->set('title', $this->prefix.' changed')->set('visibility', 'public')->call('save')->assertHasNoErrors()->assertRedirect(route('admin.agenda.index'));
        $this->assertSame('public', $stream->agenda->fresh()->visibility);
        $this->assertSame(2, $stream->fresh()->version);
        $event = $stream->events()->latest('id')->firstOrFail();
        $this->assertSame('agenda_updated', $event->event_type);
        $this->assertSame($this->prefix.' changed', $event->after_values['agenda']['title']);
    }

    public function test_agenda_completion_cancellation_and_reschedule_use_lifecycle(): void
    {
        $stream = $this->stream();
        $service = app(LiveStreamLifecycle::class);
        $service->saveAgenda($this->actor(), $stream->agenda_id, 1, ['status' => 'cancelled'], 'Synthetic cancellation');
        $this->assertSame('cancelled', $stream->fresh()->status);
        $service->saveAgenda($this->actor(), $stream->agenda_id, 2, ['status' => 'scheduled', 'agenda_date' => now('Asia/Jakarta')->addDays(5)->toDateString(), 'start_time' => '23:00', 'end_time' => '02:00', 'visibility' => 'public'], 'Synthetic reschedule');
        $this->assertSame('scheduled', $stream->fresh()->status);
        $this->assertNull($stream->fresh()->cancelled_marked_at);
        $service->saveAgenda($this->actor(), $stream->agenda_id, 3, ['status' => 'completed'], 'Synthetic missed start marker');
        $this->assertSame('ended', $stream->fresh()->status);
        $this->assertNull($stream->fresh()->live_marked_at);
        $this->assertSame(4, $stream->events()->count());
    }

    public function test_stale_service_and_livewire_versions_are_rejected(): void
    {
        $stream = $this->stream();
        $screen = Livewire::actingAs($this->actor())->test(Show::class, ['streamId' => $stream->id]);
        app(LiveStreamLifecycle::class)->updateAgenda($this->actor(), $stream->id, 1, ['visibility' => 'public']);
        $screen->call('markLive')->assertHasErrors('operation');
        $this->conflict(fn () => app(LiveStreamLifecycle::class)->transition($this->actor(), $stream->id, 1, 'live'), 'LIVE_STALE');
        $this->assertSame('scheduled', $stream->fresh()->status);
        $this->assertSame(2, $stream->events()->count());
    }

    public function test_link_created_after_opening_agenda_form_rejects_missing_version(): void
    {
        $agenda = $this->agenda();
        $screen = Livewire::actingAs($this->actor())->test(AgendaForm::class, ['agenda' => $agenda]);
        $stream = $this->stream($agenda);
        $screen->set('title', 'Should not overwrite')->call('save')->assertHasErrors('operation');
        $this->assertSame($this->prefix, $agenda->fresh()->title);
        $this->assertSame(1, $stream->events()->count());
    }

    public function test_direct_writes_delete_and_linked_type_change_are_blocked(): void
    {
        $stream = $this->stream();
        $this->conflict(fn () => $stream->agenda->update(['title' => 'Bypass']), 'LIVE_COORDINATION');
        $this->conflict(fn () => $stream->agenda->deleteQuietly(), 'LIVE_DEPENDENCY');
        $this->conflict(fn () => app(LiveStreamLifecycle::class)->deleteAgenda($this->actor(), $stream->agenda_id), 'LIVE_DEPENDENCY');
        try {
            app(LiveStreamLifecycle::class)->saveAgenda($this->actor(), $stream->agenda_id, 1, ['type' => 'meeting']);
            $this->fail('Type change must fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('type', $error->errors());
        }
        $this->assertSame('live', $stream->agenda->fresh()->type);
    }

    public function test_live_direct_mutation_cannot_skip_version_and_audit(): void
    {
        $stream = $this->stream();
        $this->expectException(\LogicException::class);
        $stream->forceFill(['youtube_id' => 'Zyx_1234567'])->saveQuietly();
    }

    public function test_ended_schedule_is_immutable_and_noop_has_no_duplicate_audit(): void
    {
        $stream = $this->stream();
        $service = app(LiveStreamLifecycle::class);
        $ended = $service->transition($this->actor(), $stream->id, 1, 'ended', 'Synthetic close');
        $service->transition($this->actor(), $stream->id, $ended->version, 'ended');
        $this->assertSame(2, $stream->events()->count());
        $this->expectException(ValidationException::class);
        $service->updateAgenda($this->actor(), $stream->id, $ended->version, ['start_time' => '10:00']);
    }

    public function test_atomic_rollback_when_audit_insert_fails(): void
    {
        $stream = $this->stream();
        LiveStreamEvent::creating(function ($event) use ($stream) {
            if ($event->live_stream_id === $stream->id) {
                throw new RuntimeException('Synthetic audit failure');
            }
        });
        $this->conflict(fn () => app(LiveStreamLifecycle::class)->saveAgenda($this->actor(), $stream->agenda_id, 1, ['title' => 'Rollback title', 'status' => 'cancelled'], 'Rollback reason'), 'LIVE_UNEXPECTED');
        $this->assertSame($this->prefix, $stream->agenda->fresh()->title);
        $this->assertSame('scheduled', $stream->agenda->fresh()->status);
        $this->assertSame('scheduled', $stream->fresh()->status);
        $this->assertSame(1, $stream->fresh()->version);
        $this->assertSame(1, $stream->events()->count());
    }

    public function test_archive_is_exact_case_and_retains_video_permissions(): void
    {
        $stream = $this->stream($this->agenda(['visibility' => 'public']));
        $ended = app(LiveStreamLifecycle::class)->transition($this->actor(), $stream->id, 1, 'ended', 'Synthetic end');
        $video = Video::create(['title' => $this->prefix.' archive', 'youtube_id' => $stream->youtube_id, 'air_date' => today(), 'user_id' => $this->actor()->id]);
        $linked = app(LiveStreamLifecycle::class)->linkArchive($this->actor(), $stream->id, $ended->version, $video->id);
        $this->assertTrue($linked->fresh()->hasValidArchive());
        $video->update(['youtube_id' => strtolower($stream->youtube_id)]);
        $this->assertFalse($linked->fresh()->hasValidArchive());
        app(LiveStreamLifecycle::class)->linkArchive($this->actor(), $stream->id, $linked->version, null);
        $this->assertNotNull($video->fresh());
        $this->assertNull($stream->fresh()->archive_video_id);
        $video->update(['youtube_id' => $stream->youtube_id]);
        $linked = app(LiveStreamLifecycle::class)->linkArchive($this->actor(), $stream->id, $stream->fresh()->version, $video->id);
        $auditCount = $linked->events()->count();
        $video->delete();
        $this->assertNull($linked->fresh()->archive_video_id);
        $this->assertFalse($linked->fresh()->hasValidArchive());
        $this->assertSame($auditCount, $linked->events()->count());
    }

    public function test_validation_failures_leave_no_configuration_or_audit(): void
    {
        $agenda = $this->agenda();
        Livewire::actingAs($this->actor())->test(Form::class)->set('mode', 'agenda')->set('agendaId', $agenda->id)->set('youtubeInput', 'https://evil.example/watch?v=AbC_dE-1234')->call('save')->assertHasErrors('youtube_input');
        $this->assertSame(0, LiveStream::count());
        $this->assertSame(0, LiveStreamEvent::count());
    }

    public function test_admin_screens_filters_and_archive_options_render(): void
    {
        $program = Program::create(['name' => $this->prefix, 'slug' => strtolower($this->prefix), 'status' => true]);
        $stream = $this->stream($this->agenda(['program_id' => $program->id, 'visibility' => 'public']));
        $this->actingAs($this->actor())->get(route('admin.live-streams.show', $stream->id))->assertOk()->assertSee($this->prefix)->assertSee('data-admin-theme-toggle', false);
        Livewire::actingAs($this->actor())->test(Index::class)->set('search', $this->prefix)->set('programId', $program->id)->set('visibility', 'public')->assertSee($this->prefix);
        Livewire::actingAs($this->actor())->test(Form::class, ['streamId' => $stream->id])->assertSet('expectedVersion', 1)->assertSee('Preview YouTube');
    }

    public function test_four_role_route_and_livewire_authorization(): void
    {
        $stream = $this->stream();
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_PRODUCTION] as $role) {
            $this->actingAs($this->actor($role))->get(route('admin.live-streams.index'))->assertOk();
        }
        foreach ([User::ROLE_NEWS, User::ROLE_TVCLUB] as $role) {
            $this->actingAs($this->actor($role))->get(route('admin.live-streams.index'))->assertForbidden();
            Livewire::actingAs($this->actor($role))->test(Show::class, ['streamId' => $stream->id])->assertForbidden();
        }
    }

    public function test_livewire_actions_complete_visibility_lifecycle_and_archive(): void
    {
        $stream = $this->stream();
        $screen = Livewire::actingAs($this->actor())->test(Show::class, ['streamId' => $stream->id]);
        $screen->set('visibility', 'public')->call('saveVisibility')->assertHasNoErrors()->assertSet('expectedVersion', 2);
        $this->assertTrue(LiveStream::publicVisible()->whereKey($stream->id)->exists());
        $screen->call('markLive')->assertHasNoErrors()->assertSet('expectedVersion', 3);
        $screen->call('end')->assertHasNoErrors()->assertSet('expectedVersion', 4);
        $video = Video::create(['title' => $this->prefix.' UI archive', 'youtube_id' => $stream->youtube_id, 'air_date' => today(), 'user_id' => $this->actor()->id]);
        $screen->set('archiveVideoId', $video->id)->call('saveArchive')->assertHasNoErrors()->assertSet('expectedVersion', 5);
        $this->assertTrue($stream->fresh()->hasValidArchive());
        $screen->call('unlinkArchive')->assertHasNoErrors()->assertSet('expectedVersion', 6);
        $this->assertNull($stream->fresh()->archive_video_id);
        $this->assertNotNull($video->fresh());
        $this->assertSame(6, $stream->events()->count());
    }

    public function test_real_mysql_unique_and_check_constraints(): void
    {
        $stream = $this->stream();
        $this->conflict(fn () => $this->stream($this->agenda()), 'LIVE_DUPLICATE');
        try {
            DB::table('live_streams')->where('id', $stream->id)->update(['status' => 'not-valid']);
            $this->fail('CHECK must reject invalid status.');
        } catch (QueryException $error) {
            $this->assertSame(3819, (int) $error->errorInfo[1]);
        }
        $this->assertSame('scheduled', $stream->fresh()->status);
    }
}
