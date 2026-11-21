<?php

namespace Tests\Unit;

use App\Exceptions\LiveStreamOperationException;
use App\Livewire\Admin\Agenda\Form;
use App\Livewire\Admin\Agenda\Index;
use App\Livewire\Concerns\HandlesLiveStreamErrors;
use App\Models\LiveStream;
use App\Models\WpitvAgenda;
use App\Support\AgendaLiveGuard;
use App\Support\LiveStreamAgendaChanges;
use App\Support\LiveStreamState;
use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Attributes\Locked;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/** Composer-only bootstrap. SQL calls are mocked; any actual PDO access fails. */
class AgendaLiveIntegrationTest extends TestCase
{
    private $oldApp;

    private $oldResolver;

    private $oldDispatcher;

    private Container $oldContainer;

    private Container $container;

    private MySqlConnection $connection;

    private int $pdoAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldApp = Facade::getFacadeApplication();
        $this->oldContainer = Container::getInstance();
        $this->oldResolver = Model::getConnectionResolver();
        $this->oldDispatcher = Model::getEventDispatcher();
        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
        $this->container->instance('config', new Repository);
        $this->container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $this->container));
        $this->container->scoped(AgendaLiveGuard::class);
        $this->connection = Mockery::mock(MySqlConnection::class.'[select,transaction,transactionLevel]', [function () {
            $this->pdoAttempts++;
            throw new RuntimeException('PDO forbidden in pure Agenda tests.');
        }, 'offline_only', '', ['driver' => 'mysql', 'name' => 'offline']]);
        $this->connection->shouldReceive('transactionLevel')->andReturn(0)->byDefault();
        $resolver = new ConnectionResolver(['offline' => $this->connection]);
        $resolver->setDefaultConnection('offline');
        Model::setConnectionResolver($resolver);
        Model::setEventDispatcher(new Dispatcher($this->container));
        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            Model::unsetConnectionResolver();
            if ($this->oldResolver !== null) {
                Model::setConnectionResolver($this->oldResolver);
            }
            Model::unsetEventDispatcher();
            if ($this->oldDispatcher !== null) {
                Model::setEventDispatcher($this->oldDispatcher);
            }
            Model::clearBootedModels();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->oldApp);
            Container::setInstance($this->oldContainer);
            $this->assertSame(0, $this->pdoAttempts);
            parent::tearDown();
        }
    }

    private function schema(array $present): object
    {
        $schema = new class($present)
        {
            public array $calls = [];

            public function __construct(private array $present) {}

            public function connection($name): self
            {
                return $this;
            }

            public function getSchemaBuilder(): self
            {
                return $this;
            }

            public function hasTable(string $name): bool
            {
                $this->calls[] = $name;

                return in_array($name, $this->present, true);
            }
        };
        Schema::swap($schema);
        $this->container->instance('db', $schema);

        return $schema;
    }

    private function agenda(array $input = []): WpitvAgenda
    {
        $agenda = (new WpitvAgenda)->setDateFormat('Y-m-d H:i:s')->forceFill(array_replace([
            'id' => 10, 'title' => 'Synthetic agenda', 'type' => 'live', 'status' => 'scheduled', 'visibility' => 'public',
            'agenda_date' => '2026-12-31', 'start_time' => '23:00:00', 'end_time' => '01:00:00',
        ], $input));
        $agenda->exists = true;
        $agenda->syncOriginal();

        return $agenda;
    }

    private function stream(string $status = 'scheduled'): LiveStream
    {
        $stream = (new LiveStream)->forceFill(['id' => 20, 'agenda_id' => 10, 'status' => $status, 'version' => 4, 'youtube_id' => 'AbC_dE-1234']);
        if (in_array($status, ['live', 'ended'], true)) {
            $stream->live_marked_at = CarbonImmutable::parse('2026-11-08 00:00:00.123456', 'UTC');
        }
        if ($status === 'ended') {
            $stream->ended_marked_at = CarbonImmutable::parse('2026-11-08 01:00:00.123456', 'UTC');
        }
        if ($status === 'cancelled') {
            $stream->cancelled_marked_at = CarbonImmutable::parse('2026-11-08', 'UTC');
        }
        $stream->syncOriginal();

        return $stream;
    }

    public function test_missing_schema_is_cached_without_querying_live_or_opening_pdo(): void
    {
        $schema = $this->schema([]);
        $this->connection->shouldNotReceive('select');
        $guard = $this->container->make(AgendaLiveGuard::class);
        $this->assertSame($guard, $this->container->make(AgendaLiveGuard::class));
        $this->assertNull($guard->stream($this->agenda()));
        $this->assertNull($guard->stream($this->agenda()));
        $this->assertSame('ordinary path', $guard->write($this->agenda(), 'save', fn () => 'ordinary path'));
        $this->assertSame(['live_streams', 'live_stream_events'], $schema->calls);
    }

    public static function incompleteSchemas(): array
    {
        return [[['live_streams']], [['live_stream_events']]];
    }

    #[DataProvider('incompleteSchemas')]
    public function test_partial_provisioning_fails_closed(array $present): void
    {
        $this->schema($present);
        $this->connection->shouldNotReceive('select');
        $this->expectExceptionMessage('LIVE_SCHEMA');
        $this->container->make(AgendaLiveGuard::class)->write($this->agenda(), 'save', fn () => $this->fail('Must not write with partial schema.'));
    }

    public static function directWrites(): array
    {
        return [['save', 'LIVE_COORDINATION'], ['saveQuietly', 'LIVE_COORDINATION'], ['delete', 'LIVE_DEPENDENCY'], ['deleteQuietly', 'LIVE_DEPENDENCY']];
    }

    #[DataProvider('directWrites')]
    public function test_direct_and_quiet_model_writes_lock_agenda_then_live_and_are_blocked(string $method, string $code): void
    {
        $this->schema(['live_streams', 'live_stream_events']);
        $agenda = $this->agenda();
        $calls = [];
        $this->connection->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        $this->connection->shouldReceive('select')->twice()->andReturnUsing(function ($sql, $bindings, $readPdo) use (&$calls, $agenda) {
            $calls[] = $sql;
            $this->assertStringContainsString('for update', $sql);
            $this->assertFalse($readPdo);

            return str_contains($sql, 'wpitv_agendas') ? [(object) $agenda->getAttributes()] : [(object) $this->stream()->getAttributes()];
        });
        try {
            $agenda->$method();
            $this->fail('Linked direct write must be rejected.');
        } catch (LiveStreamOperationException $error) {
            $this->assertSame($code, $error->errorCode);
        }
        $this->assertStringContainsString('wpitv_agendas', $calls[0]);
        $this->assertStringContainsString('live_streams', $calls[1]);
    }

    public function test_unlinked_write_remains_allowed_with_schema_present(): void
    {
        $this->schema(['live_streams', 'live_stream_events']);
        $agenda = $this->agenda(['type' => 'meeting']);
        $this->connection->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        $this->connection->shouldReceive('select')->twice()->andReturn([(object) $agenda->getAttributes()], []);
        $this->assertSame('ordinary path', $this->container->make(AgendaLiveGuard::class)->write($agenda, 'save', fn () => 'ordinary path'));
    }

    public function test_new_agenda_does_not_probe_live_schema(): void
    {
        $schema = $this->schema([]);
        $agenda = $this->agenda();
        $agenda->exists = false;
        $this->assertSame('created', $this->container->make(AgendaLiveGuard::class)->write($agenda, 'save', fn () => 'created'));
        $this->assertSame([], $schema->calls);
    }

    public function test_coordinated_save_requires_a_transaction(): void
    {
        $this->connection->shouldNotReceive('select');
        $this->expectException(LogicException::class);
        $this->container->make(AgendaLiveGuard::class)->persist($this->agenda());
    }

    public function test_coordinated_permission_is_cleared_when_persistence_throws(): void
    {
        $schema = $this->schema([]);
        $this->connection->shouldReceive('transactionLevel')->andReturn(1);
        $this->connection->shouldNotReceive('select');
        $agenda = new class extends WpitvAgenda
        {
            public function save(array $options = [])
            {
                return app(AgendaLiveGuard::class)->write($this, 'save', fn () => throw new RuntimeException('Synthetic persistence failure.'));
            }
        };
        $agenda->exists = true;
        $guard = $this->container->make(AgendaLiveGuard::class);
        try {
            $guard->persist($agenda);
            $this->fail('Synthetic failure must escape.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic persistence failure.', $error->getMessage());
        }
        $this->assertSame([], $schema->calls);
        $this->assertTrue($guard->write($agenda, 'save', fn () => true));
        $this->assertSame(['live_streams', 'live_stream_events'], $schema->calls);
    }

    public static function statusChanges(): array
    {
        return [
            ['scheduled', 'scheduled', 'cancelled', 'cancelled', 'Pembatalan'],
            ['scheduled', 'scheduled', 'completed', 'ended', 'Penandaan mulai terlewat'],
            ['live', 'scheduled', 'completed', 'ended', null],
            ['scheduled', 'scheduled', 'draft', 'scheduled', null],
            ['scheduled', 'draft', 'scheduled', 'scheduled', null],
            ['cancelled', 'cancelled', 'scheduled', 'scheduled', 'Jadwal ulang'],
        ];
    }

    #[DataProvider('statusChanges')]
    public function test_agenda_status_intent_uses_live_lifecycle(string $liveStatus, string $agendaStatus, string $desired, string $expected, ?string $reason): void
    {
        $stream = $this->stream($liveStatus);
        $agenda = $this->agenda(['status' => $agendaStatus]);
        $input = ['status' => $desired, 'agenda_date' => '2026-12-31', 'start_time' => '23:00:00', 'end_time' => '01:00:00', 'visibility' => 'public', 'title' => 'Changed title'];
        LiveStreamAgendaChanges::apply($stream, $agenda, $input, CarbonImmutable::parse('2026-11-09 00:00:00.123456', 'UTC'), $reason);
        $this->assertSame($expected, $stream->status);
        $this->assertSame($desired, $agenda->status);
        $this->assertSame('Changed title', $agenda->title);
        LiveStreamState::assertConsistent($stream, $agenda);
    }

    public function test_linked_type_cannot_change(): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamAgendaChanges::apply($this->stream(), $this->agenda(), ['type' => 'meeting'], CarbonImmutable::now('UTC'), null);
    }

    public function test_ended_schedule_is_immutable_but_metadata_can_change(): void
    {
        $stream = $this->stream('ended');
        $agenda = $this->agenda(['status' => 'completed']);
        LiveStreamAgendaChanges::apply($stream, $agenda, ['title' => 'Corrected title', 'coverage_id' => 7], CarbonImmutable::now('UTC'), null);
        $this->assertSame('Corrected title', $agenda->title);
        $this->assertSame(7, $agenda->coverage_id);
        $this->expectException(ValidationException::class);
        LiveStreamAgendaChanges::apply($stream, $agenda, ['agenda_date' => '2027-01-01'], CarbonImmutable::now('UTC'), null);
    }

    public function test_cancellation_during_live_cannot_bypass_transition_rules(): void
    {
        $this->expectException(LiveStreamOperationException::class);
        LiveStreamAgendaChanges::apply($this->stream('live'), $this->agenda(), ['status' => 'cancelled'], CarbonImmutable::now('UTC'), 'Reason');
    }

    public function test_cancel_requires_reason_and_reschedule_requires_full_input(): void
    {
        try {
            LiveStreamAgendaChanges::apply($this->stream(), $this->agenda(), ['status' => 'cancelled'], CarbonImmutable::now('UTC'), null);
            $this->fail('Missing reason must fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('reason', $error->errors());
        }
        $this->expectException(ValidationException::class);
        LiveStreamAgendaChanges::apply($this->stream('cancelled'), $this->agenda(['status' => 'cancelled']), ['status' => 'scheduled'], CarbonImmutable::now('UTC'), 'Reason');
    }

    public function test_form_rule_preserves_normal_agenda_time_validation(): void
    {
        $form = new Form;
        $data = ['title' => 'Synthetic', 'type' => 'live', 'agenda_date' => '2026-12-31', 'start_time' => '23:00', 'end_time' => '01:00', 'status' => 'scheduled', 'visibility' => 'public'];
        $form->type = 'live';
        $rules = (new ReflectionMethod(Form::class, 'rules'))->invoke($form);
        $this->assertFalse($this->container->make('validator')->make($data, $rules)->fails());
        $form->type = 'meeting';
        $data['type'] = 'meeting';
        $rules = (new ReflectionMethod(Form::class, 'rules'))->invoke($form);
        $this->assertArrayHasKey('end_time', $this->container->make('validator')->make($data, $rules)->errors()->toArray());
        $this->assertSame('23.00–01.00 (+1 hari) WIB', $this->agenda()->time_label);
    }

    public function test_identity_version_and_original_times_are_locked_livewire_properties(): void
    {
        foreach ([Form::class => ['agenda', 'agendaId', 'expectedLiveVersion', 'initialLiveTimes'], Index::class => ['selectedAgenda', 'expectedLiveVersion', 'selectedLiveStatus']] as $class => $properties) {
            foreach ($properties as $property) {
                $this->assertCount(1, (new ReflectionProperty($class, $property))->getAttributes(Locked::class));
            }
        }
    }

    public function test_agenda_blade_views_compile_and_parse_without_rendering_or_writing_cache(): void
    {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        foreach (['form', 'index'] as $view) {
            $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/agenda/'.$view.'.blade.php');
            $compiled = $compiler->compileString($source);
            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
        }
    }

    public function test_safe_conflicts_use_existing_component_error_feedback(): void
    {
        $component = new class
        {
            use HandlesLiveStreamErrors;

            public array $errors = [];

            public function addError($key, $message): void
            {
                $this->errors[$key] = $message;
            }
        };
        $stopped = false;
        $component->exceptionHandlesLiveStreamErrors(new LiveStreamOperationException('LIVE_DEPENDENCY'), function () use (&$stopped) {
            $stopped = true;
        });
        $this->assertTrue($stopped);
        $this->assertStringContainsString('tidak dapat dihapus', $component->errors['operation']);
        $stopped = false;
        $component->exceptionHandlesLiveStreamErrors(new LiveStreamOperationException('LIVE_DATABASE'), function () use (&$stopped) {
            $stopped = true;
        });
        $this->assertFalse($stopped, 'Unexpected failures must reach the safe Laravel reporter/renderer.');
    }
}
