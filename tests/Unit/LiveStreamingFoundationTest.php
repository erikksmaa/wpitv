<?php

namespace Tests\Unit;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Models\LiveStreamEvent;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\WpitvAgenda;
use App\Policies\LiveStreamPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\VideoPolicy;
use App\Policies\WpitvAgendaPolicy;
use App\Services\LiveStreamLifecycle;
use App\Support\LiveStreamErrors;
use App\Support\LiveStreamSchedule;
use App\Support\LiveStreamState;
use App\Support\YouTubeVideoId;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Gate;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionMethod;
use RuntimeException;

/** Pure suite: --no-configuration --bootstrap vendor/autoload.php; never tests/bootstrap.php. */
class LiveStreamingFoundationTest extends TestCase
{
    private $previousApplication;

    private $previousResolver;

    private $previousDispatcher;

    private Container $previousContainer;

    private Container $container;

    private string $previousTimezone;

    private int $pdoAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousApplication = Facade::getFacadeApplication();
        $this->previousContainer = Container::getInstance();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Honolulu');
        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
        $this->container->instance('config', new Repository);
        $this->container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $this->container));
        // Offline grammar supports query compilation, but PDO can never be opened.
        $offline = new MySqlConnection(function () {
            $this->pdoAttempts++;
            throw new RuntimeException('Database access forbidden in pure Live tests.');
        }, 'offline_only', '', ['driver' => 'mysql', 'version' => '8.0.30']);
        $resolver = new ConnectionResolver(['offline' => $offline]);
        $resolver->setDefaultConnection('offline');
        Model::setConnectionResolver($resolver);
        Model::setEventDispatcher(new Dispatcher($this->container));
        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        date_default_timezone_set($this->previousTimezone);
        Model::unsetConnectionResolver();
        if ($this->previousResolver !== null) {
            Model::setConnectionResolver($this->previousResolver);
        }
        Model::unsetEventDispatcher();
        if ($this->previousDispatcher !== null) {
            Model::setEventDispatcher($this->previousDispatcher);
        }
        Model::clearBootedModels();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousApplication);
        Container::setInstance($this->previousContainer);
        $this->assertSame(0, $this->pdoAttempts, 'No pure test may request PDO.');
        parent::tearDown();
    }

    public static function validInputs(): array
    {
        return array_map(fn ($input) => [$input], [
            'AbC_dE-1234', ' AbC_dE-1234 ', 'https://www.youtube.com/watch?v=AbC_dE-1234',
            'https://youtube.com/live/AbC_dE-1234?si=tracking', 'https://m.youtube.com/watch?t=9&v=AbC_dE-1234',
            'https://youtu.be/AbC_dE-1234?t=3', 'https://www.youtube.com/embed/AbC_dE-1234',
            'https://WWW.YOUTUBE.COM/watch?%76=AbC_dE-1234&feature=share',
        ]);
    }

    #[DataProvider('validInputs')]
    public function test_supported_inputs_produce_only_the_case_sensitive_id(string $input): void
    {
        $this->assertSame('AbC_dE-1234', YouTubeVideoId::parse($input));
    }

    public static function invalidInputs(): array
    {
        return array_map(fn ($input) => [$input], [
            '', 'AbC_dE-123', 'AbC_dE-12345', 'https://youtube.com/watch?v=AbC_dE-12345',
            'https://youtube.com/watch?v=AbC_dE-1234&v=XXXXXXXXXXX',
            'https://youtube.com/watch?v=AbC_dE-1234&%76=AbC_dE-1234',
            'https://youtube.com/watch?v[]=AbC_dE-1234', 'https://youtube.com/watch?v[0]=AbC_dE-1234&v=AbC_dE-1234',
            'https://youtube.com/watch?v=AbC_dE-1234;v=XXXXXXXXXXX',
            'https://evil.example/watch?v=AbC_dE-1234', 'https://youtube.com.evil.example/watch?v=AbC_dE-1234',
            'https://evil-youtube.com/watch?v=AbC_dE-1234', 'https://studio.youtube.com/watch?v=AbC_dE-1234',
            'https://youtube.com@evil.example/watch?v=AbC_dE-1234', 'https://user:pass@youtube.com/watch?v=AbC_dE-1234',
            'https://youtube.com:443/watch?v=AbC_dE-1234', 'https://youtube.com:invalid/watch?v=AbC_dE-1234',
            'http://youtube.com/watch?v=AbC_dE-1234', '//youtube.com/watch?v=AbC_dE-1234',
            'javascript:AbC_dE-1234', 'data:text/html,AbC_dE-1234',
            '<iframe src="https://youtube.com/embed/AbC_dE-1234"></iframe>',
            'https://youtube.com/@wpitv/live', 'https://youtube.com/playlist?list=AbC_dE-1234',
            'https://youtube.com/shorts/AbC_dE-1234', 'https://youtube.com/live/AbC_dE-1234/extra',
            'https://youtube.com/live/AbC_dE-1234?v=XXXXXXXXXXX', 'https://youtu.be/prefix/AbC_dE-1234',
            'https://youtu.be/AbC_dE-1234?v=XXXXXXXXXXX', 'https://youtube.com/live/AbC_dE-1234%2F',
            "AbC_dE-1234\n", "https://youtube.com/\tlive/AbC_dE-1234", 'https://youtube.com\\@evil.example/watch?v=AbC_dE-1234',
            'https://youtube.com/watch?v=AbC_dE-1234&x='.str_repeat('x', 2048),
        ]);
    }

    #[DataProvider('invalidInputs')]
    public function test_ambiguous_or_untrusted_input_is_rejected(string $input): void
    {
        $this->assertNull(YouTubeVideoId::parse($input));
    }

    public function test_player_urls_never_use_arbitrary_input(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/AbC_dE-1234?playsinline=1&autoplay=0', YouTubeVideoId::embedUrl('AbC_dE-1234'));
        $this->assertNull(YouTubeVideoId::embedUrl('https://evil.example'));
        $this->assertNull(YouTubeVideoId::watchUrl(null));
    }

    private function agenda(array $overrides = []): WpitvAgenda
    {
        return (new WpitvAgenda)->setDateFormat('Y-m-d H:i:s')->forceFill(array_replace([
            'id' => 10, 'title' => 'Synthetic Live', 'type' => 'live', 'status' => 'scheduled',
            'visibility' => 'public', 'agenda_date' => '2026-12-31', 'start_time' => '23:30:00', 'end_time' => '01:00:00',
        ], $overrides));
    }

    private function stream(string $status = 'scheduled'): LiveStream
    {
        $stream = (new LiveStream)->forceFill(['id' => 20, 'agenda_id' => 10, 'youtube_id' => 'AbC_dE-1234', 'status' => $status, 'version' => 1]);
        if (in_array($status, ['live', 'ended'], true)) {
            $stream->live_marked_at = CarbonImmutable::parse('2026-12-31 16:30:00.123456', 'UTC');
        }
        if ($status === 'ended') {
            $stream->ended_marked_at = CarbonImmutable::parse('2026-12-31 18:00:00.654321', 'UTC');
        }
        if ($status === 'cancelled') {
            $stream->cancelled_marked_at = CarbonImmutable::parse('2026-12-30', 'UTC');
        }

        return $stream;
    }

    public static function schedules(): array
    {
        return [
            ['2026-12-31', '23:30', '01:00', '2026-12-31 16:30:00', '2026-12-31 18:00:00'],
            ['2028-02-29', '23:59:59', '00:00:01', '2028-02-29 16:59:59', '2028-02-29 17:00:01'],
            ['2026-11-09', '09:00', '10:00', '2026-11-09 02:00:00', '2026-11-09 03:00:00'],
        ];
    }

    #[DataProvider('schedules')]
    public function test_schedule_uses_wib_and_rolls_an_earlier_end_clock_to_next_day(string $date, string $start, string $end, string $utcStart, string $utcEnd): void
    {
        $agenda = $this->agenda(['agenda_date' => $date, 'start_time' => $start, 'end_time' => $end]);
        $this->assertSame($utcStart, LiveStreamSchedule::startsAt($agenda)->format('Y-m-d H:i:s'));
        $this->assertSame($utcEnd, LiveStreamSchedule::endsAt($agenda)->format('Y-m-d H:i:s'));
        $this->assertTrue(LiveStreamSchedule::endsAt($agenda)->gt(LiveStreamSchedule::startsAt($agenda)));
    }

    public function test_identical_start_and_end_is_rejected_instead_of_guessing_a_24_hour_event(): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamSchedule::endsAt($this->agenda(['end_time' => '23:30:00']));
    }

    public function test_end_without_start_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamSchedule::endsAt($this->agenda(['start_time' => null]));
    }

    public static function roleAccess(): array
    {
        return [[User::ROLE_SUPER_ADMIN, true], [User::ROLE_PRODUCTION, true], [User::ROLE_NEWS, false], [User::ROLE_TVCLUB, false], ['Unknown', false]];
    }

    #[DataProvider('roleAccess')]
    public function test_policy_discovery_and_all_abilities_respect_four_roles(string $name, bool $allowed): void
    {
        $user = (new User)->forceFill(['id' => 1, 'status' => true]);
        $user->setRelation('role', (new Role)->forceFill(['name' => $name]));
        $gate = new Gate($this->container, fn () => $user);
        $this->assertInstanceOf(LiveStreamPolicy::class, $gate->getPolicyFor(LiveStream::class));
        foreach (['view', 'update', 'changeStatus', 'manageArchive'] as $ability) {
            $this->assertSame($allowed, $gate->allows($ability, $this->stream()));
        }
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertSame($allowed, $gate->allows($ability, LiveStream::class));
        }
        $this->assertFalse($gate->allows('delete', $this->stream()));
        $user->status = false;
        $this->assertFalse($gate->allows('update', $this->stream()));
    }

    public function test_existing_permissions_are_preserved(): void
    {
        $user = (new User)->forceFill(['status' => true]);
        $user->setRelation('role', (new Role)->forceFill(['name' => User::ROLE_TVCLUB]));
        $this->assertTrue((new ProgramPolicy)->update($user, new Program));
        $this->assertFalse((new WpitvAgendaPolicy)->viewAny($user));
        $user->setRelation('role', (new Role)->forceFill(['name' => User::ROLE_NEWS]));
        $this->assertTrue((new VideoPolicy)->update($user, new Video));
        $this->assertFalse((new VideoPolicy)->delete($user, new Video));
    }

    public function test_lifecycle_retains_markers_and_does_not_claim_platform_verification(): void
    {
        $stream = $this->stream();
        $agenda = $this->agenda();
        $now = CarbonImmutable::parse('2026-12-31 16:30:00.123456', 'UTC');
        LiveStreamState::applyTransition($stream, $agenda, 'live', $now, null);
        $this->assertSame('scheduled', $agenda->status);
        $this->assertSame('2026-12-31 16:30:00.123456', $stream->getAttributes()['live_marked_at']);
        LiveStreamState::applyTransition($stream, $agenda, 'ended', $now->addHours(2), null);
        $this->assertSame('completed', $agenda->status);
        $this->assertTrue($stream->live_marked_at->eq($now));
        $this->assertSame('manual', $stream->status_source);
        $this->assertSame('unknown', $stream->youtube_status);
        $this->assertNull($stream->archive_video_id);
    }

    public function test_administrative_close_does_not_invent_a_start_time(): void
    {
        $stream = $this->stream();
        $agenda = $this->agenda();
        LiveStreamState::applyTransition($stream, $agenda, 'ended', CarbonImmutable::now('UTC'), 'Penandaan awal terlewat');
        $this->assertNull($stream->live_marked_at);
        $this->assertSame('completed', $agenda->status);
    }

    public static function forbiddenTransitions(): array
    {
        return [['live', 'cancelled'], ['live', 'scheduled'], ['ended', 'live'], ['ended', 'scheduled'], ['cancelled', 'live']];
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_history_cannot_be_reopened_by_forbidden_transitions(string $from, string $to): void
    {
        $agendaStatus = match ($from) {
            'ended' => 'completed', 'cancelled' => 'cancelled', default => 'scheduled'
        };
        $this->expectException(LiveStreamOperationException::class);
        LiveStreamState::applyTransition($this->stream($from), $this->agenda(['status' => $agendaStatus]), $to, CarbonImmutable::now('UTC'), 'Synthetic reason');
    }

    public function test_cancellation_and_rescheduling_retain_current_marker_semantics(): void
    {
        $stream = $this->stream();
        $agenda = $this->agenda();
        $now = CarbonImmutable::parse('2026-12-30', 'UTC');
        LiveStreamState::applyTransition($stream, $agenda, 'cancelled', $now, 'Jadwal berubah');
        $this->assertSame('cancelled', $agenda->status);
        $this->assertTrue($stream->cancelled_marked_at->eq($now));
        LiveStreamState::applyTransition($stream, $agenda, 'scheduled', $now, 'Jadwal baru');
        $this->assertNull($stream->cancelled_marked_at);
        $this->assertSame('scheduled', $agenda->status);
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamState::applyTransition($this->stream(), $this->agenda(), 'cancelled', CarbonImmutable::now('UTC'), ' ');
    }

    public function test_live_requires_a_video_id(): void
    {
        $stream = $this->stream();
        $stream->youtube_id = null;
        $this->expectException(ValidationException::class);
        LiveStreamState::applyTransition($stream, $this->agenda(), 'live', CarbonImmutable::now('UTC'), null);
    }

    public function test_no_status_changes_just_because_time_has_passed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-02', 'UTC'));
        $stream = $this->stream();
        LiveStreamState::assertConsistent($stream, $this->agenda());
        $this->assertSame('scheduled', $stream->status);
        $this->assertSame('unknown', $stream->youtube_status);
    }

    public function test_utc_storage_keeps_microseconds_and_wib_round_trip(): void
    {
        $stream = $this->stream();
        $wib = CarbonImmutable::parse('2026-11-09 07:10:11.123456', 'Asia/Jakarta');
        $stream->status_changed_at = $wib;
        $stream->created_at = $wib;
        $this->assertSame('2026-11-09 00:10:11.123456', $stream->getAttributes()['status_changed_at']);
        $this->assertSame('2026-11-09 00:10:11.123456', $stream->getAttributes()['created_at']);
        $this->assertSame($wib->format('c.u'), $stream->status_changed_at->setTimezone('Asia/Jakarta')->format('c.u'));
        $this->assertSame('UTC', $stream->freshTimestamp()->timezoneName);
    }

    public function test_archive_checks_exact_case_visibility_and_editorial_changes(): void
    {
        $stream = $this->stream('ended');
        $stream->setRelation('agenda', $this->agenda(['status' => 'completed']));
        $stream->archive_video_id = 30;
        $video = (new Video)->forceFill(['id' => 30, 'youtube_id' => 'AbC_dE-1234']);
        $stream->setRelation('archiveVideo', $video);
        $this->assertTrue($stream->hasValidArchive());
        $video->youtube_id = 'abc_de-1234';
        $this->assertFalse($stream->hasValidArchive());
        $video->youtube_id = $stream->youtube_id;
        $stream->agenda->visibility = 'internal';
        $this->assertFalse($stream->hasValidArchive());
        $stream->setRelation('archiveVideo', null);
        $this->assertFalse($stream->hasValidArchive());
    }

    public function test_manual_schedule_and_lifecycle_without_agenda(): void
    {
        [$start, $end] = LiveStreamSchedule::manual('2026-12-31', '23:30', '01:15');
        $this->assertSame('2026-12-31 16:30:00', $start->toDateTimeString());
        $this->assertSame('2026-12-31 18:15:00', $end->toDateTimeString());
        $stream = (new LiveStream)->forceFill([
            'manual_title' => 'Manual test', 'manual_visibility' => 'public',
            'manual_starts_at' => $start, 'manual_ends_at' => $end,
            'youtube_id' => 'AbC_dE-1234', 'status' => 'scheduled', 'version' => 1,
        ]);
        $this->assertTrue($stream->isManual());
        $this->assertSame('31/12/2026', $stream->schedule_date);
        $this->assertStringContainsString('+1 hari', $stream->time_label);
        $this->assertSame('Manual test', $stream->display_title);
        LiveStreamState::applyTransition($stream, null, 'cancelled', $start->subDay(), 'Change schedule');
        $this->assertSame('cancelled', $stream->status);
        LiveStreamState::applyTransition($stream, null, 'scheduled', $start->subDay(), 'New schedule');
        $this->assertNull($stream->cancelled_marked_at);
        LiveStreamState::applyTransition($stream, null, 'live', $start, null);
        LiveStreamState::applyTransition($stream, null, 'ended', $end, null);
        $this->assertSame('ended', $stream->status);
        $this->assertTrue($stream->ended_marked_at->equalTo($end));
        $snapshot = (new ReflectionMethod(LiveStreamLifecycle::class, 'snapshot'))->invoke(new LiveStreamLifecycle, $stream, null);
        $this->assertNull($snapshot['agenda']);
        $this->assertSame('manual', $snapshot['live']['creation_mode']);
    }

    public function test_snapshot_whitelists_fields_and_formats_utc(): void
    {
        $agenda = $this->agenda(['coverage_id' => 99, 'created_by' => 99]);
        $snapshot = (new ReflectionMethod(LiveStreamLifecycle::class, 'snapshot'))->invoke(new LiveStreamLifecycle, $this->stream('live'), $agenda);
        $this->assertSame(99, $snapshot['agenda']['coverage_id']);
        $this->assertArrayNotHasKey('coverage', $snapshot['agenda']);
        $this->assertArrayNotHasKey('created_by', $snapshot['agenda']);
        $this->assertSame('2026-12-31', $snapshot['agenda']['agenda_date']);
        $this->assertSame('2026-12-31 16:30:00.123456', $snapshot['live']['live_marked_at']);
    }

    public function test_stale_version_is_rejected_before_a_change(): void
    {
        $stream = $this->stream();
        LiveStreamState::assertVersion($stream, 1);
        $stream->version = 2;
        $this->expectException(LiveStreamOperationException::class);
        $this->expectExceptionMessage('LIVE_STALE');
        LiveStreamState::assertVersion($stream, 1);
    }

    public function test_identical_current_state_is_a_noop(): void
    {
        $stream = $this->stream('live');
        $agenda = $this->agenda();
        $stream->syncOriginal();
        $agenda->syncOriginal();
        LiveStreamState::applyTransition($stream, $agenda, 'live', CarbonImmutable::now('UTC'), null);
        LiveStreamState::applyYouTubeId($stream, $stream->youtube_id);
        $this->assertFalse($stream->isDirty());
        $this->assertFalse($agenda->isDirty());
    }

    public static function immutableConfigurations(): array
    {
        return [['live'], ['ended']];
    }

    #[DataProvider('immutableConfigurations')]
    public function test_video_identity_is_immutable_after_broadcast_begins(string $status): void
    {
        $this->expectException(LiveStreamOperationException::class);
        LiveStreamState::applyYouTubeId($this->stream($status), 'XXXXXXXXXXX');
    }

    public function test_unstarted_broadcast_can_change_or_remove_video_id(): void
    {
        $stream = $this->stream();
        LiveStreamState::applyYouTubeId($stream, 'XXXXXXXXXXX');
        $this->assertSame('XXXXXXXXXXX', $stream->youtube_id);
        LiveStreamState::applyYouTubeId($stream, null);
        $this->assertNull($stream->youtube_id);
    }

    public function test_rescheduling_to_the_past_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamState::applyTransition($this->stream('cancelled'), $this->agenda(['status' => 'cancelled']), 'scheduled', CarbonImmutable::parse('2027-01-02', 'UTC'), 'Synthetic reason');
    }

    public function test_draft_cannot_go_live(): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamState::applyTransition($this->stream(), $this->agenda(['status' => 'draft']), 'live', CarbonImmutable::now('UTC'), null);
    }

    public static function inconsistentStates(): array
    {
        return [
            [['version' => 0], []], [['status' => 'LIVE'], []], [['youtube_id' => 'invalid'], []],
            [['archive_video_id' => 3], []], [['live_marked_at' => '2026-11-09 00:00:00'], []],
            [[], ['type' => 'meeting']], [[], ['status' => 'completed']], [[], ['visibility' => 'invalid']],
        ];
    }

    #[DataProvider('inconsistentStates')]
    public function test_inconsistent_state_fails_closed(array $streamChanges, array $agendaChanges): void
    {
        $this->expectException(LiveStreamOperationException::class);
        LiveStreamState::assertConsistent($this->stream()->forceFill($streamChanges), $this->agenda($agendaChanges));
    }

    public function test_raw_agenda_input_normalizes_nullable_times_and_preserves_overnight_range(): void
    {
        $method = new ReflectionMethod(LiveStreamLifecycle::class, 'agendaInput');
        $data = $method->invoke(new LiveStreamLifecycle, ['agenda_date' => '2026-12-31', 'start_time' => '23:00', 'end_time' => '01:00', 'location' => ''], ['agenda_date', 'start_time', 'end_time', 'location']);
        $this->assertSame('23:00:00', $data['start_time']);
        $this->assertSame('01:00:00', $data['end_time']);
        $this->assertNull($data['location']);
    }

    public function test_invalid_raw_date_is_rejected_before_eloquent_can_coerce_it(): void
    {
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(LiveStreamLifecycle::class, 'agendaInput'))->invoke(new LiveStreamLifecycle, ['agenda_date' => '2026-02-30'], ['agenda_date']);
    }

    public function test_untrusted_agenda_fields_cannot_override_actor_or_type(): void
    {
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(LiveStreamLifecycle::class, 'agendaInput'))->invoke(new LiveStreamLifecycle, ['created_by' => 99, 'type' => 'meeting'], ['title']);
    }

    public function test_model_relations_and_public_scope_compile_without_pdo(): void
    {
        $this->assertSame('wpitv_agendas', $this->stream()->agenda()->getRelated()->getTable());
        $this->assertSame('ms_video', $this->stream()->archiveVideo()->getRelated()->getTable());
        $this->assertSame('live_stream_events', $this->stream()->events()->getRelated()->getTable());
        $this->assertSame('live_streams', (new WpitvAgenda)->liveStream()->getRelated()->getTable());
        $this->assertSame('live_streams', (new Video)->archivedLiveStream()->getRelated()->getTable());
        $this->assertSame('live_streams', (new Program)->liveStreams()->getRelated()->getTable());
        $sql = LiveStream::publicVisible()->toSql();
        $this->assertStringContainsString('`visibility` = ?', $sql);
        $this->assertSame(['public', 'live', 'public', 'scheduled', 'live', 'scheduled', 'ended', 'completed', 'cancelled', 'cancelled'], LiveStream::publicVisible()->getBindings());
    }

    public static function sqlFailures(): array
    {
        return [[1062, '23000', 'LIVE_DUPLICATE'], [1213, '40001', 'LIVE_CONCURRENCY'], [1205, 'HY000', 'LIVE_CONCURRENCY'], [1146, '42S02', 'LIVE_DATABASE']];
    }

    #[DataProvider('sqlFailures')]
    public function test_sql_failure_is_sanitized_without_previous_or_private_payload(int $driver, string $state, string $code): void
    {
        $raw = new PDOException('PRIVATE_SENTINEL');
        $raw->errorInfo = [$state, $driver, 'PRIVATE_SENTINEL'];
        $query = new QueryException('offline', 'insert into live_stream_events (reason) values (?)', ['PRIVATE_SENTINEL'], $raw);
        try {
            LiveStreamErrors::run(fn () => throw $query);
            $this->fail('Expected sanitized exception.');
        } catch (LiveStreamOperationException $safe) {
            $this->assertSame($code, $safe->errorCode);
            $this->assertNull($safe->getPrevious());
            $this->assertStringNotContainsString('PRIVATE_SENTINEL', $safe->getMessage());
            $logger = new class extends AbstractLogger
            {
                public array $entries = [];

                public function log($level, \Stringable|string $message, array $context = []): void
                {
                    $this->entries[] = [$level, (string) $message, $context];
                }
            };
            $safe->report($logger);
            $this->assertSame([['warning', 'Live streaming operation failed.', ['error_code' => $code]]], $logger->entries);
            $request = Request::create('/synthetic-live');
            $request->headers->set('Accept', 'application/json');
            $this->assertStringNotContainsString('PRIVATE_SENTINEL', $safe->render($request)->getContent());
        }
    }

    public function test_live_model_rejects_deletion_before_any_query(): void
    {
        $stream = $this->stream();
        $stream->exists = true;
        $this->expectException(LogicException::class);
        $stream->delete();
    }

    public function test_live_model_rejects_moving_history_to_another_agenda(): void
    {
        $stream = $this->stream();
        $stream->exists = true;
        $stream->syncOriginal();
        $stream->agenda_id = 11;
        $this->expectException(LogicException::class);
        $stream->save();
    }

    public function test_audit_model_rejects_changes_before_any_query(): void
    {
        $event = (new LiveStreamEvent)->forceFill(['id' => 1, 'event_type' => 'created']);
        $event->exists = true;
        $event->syncOriginal();
        $event->reason = 'changed';
        $this->expectException(LogicException::class);
        $event->save();
    }

    public function test_audit_model_rejects_deletion_before_any_query(): void
    {
        $event = (new LiveStreamEvent)->forceFill(['id' => 1]);
        $event->exists = true;
        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_quiet_methods_cannot_bypass_live_history_guards(): void
    {
        foreach ([new LiveStream, new LiveStreamEvent] as $model) {
            $model->forceFill(['id' => 1])->syncOriginal();
            $model->exists = true;
            foreach (['saveQuietly', 'deleteQuietly'] as $method) {
                $model->forceFill(['created_at' => '2026-11-01 00:00:00']);
                try {
                    $model->$method();
                    $this->fail('History mutation must be blocked.');
                } catch (LogicException) {
                    $this->assertTrue(true);
                }
            }
        }
    }
}
