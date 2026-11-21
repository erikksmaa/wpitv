<?php

namespace Tests\Unit;

use App\Exceptions\EquipmentOperationException;
use App\Exceptions\LiveStreamOperationException;
use App\Livewire\Admin\LiveStreams\Form;
use App\Livewire\Admin\LiveStreams\Index;
use App\Livewire\Admin\LiveStreams\Show;
use App\Livewire\Concerns\HandlesLiveStreamErrors;
use App\Models\LiveStream;
use App\Models\Role;
use App\Models\User;
use App\Support\AgendaLiveGuard;
use App\Support\LiveStreamErrors;
use App\Support\LiveStreamFilters;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Gate;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\ViewException;
use Livewire\Attributes\Locked;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/** Pure UI safety checks; no app bootstrap, credentials, database, or migrations. */
class LiveStreamingAdminTest extends TestCase
{
    private $oldApp;

    private $oldResolver;

    private Container $oldContainer;

    private Container $container;

    private User $actor;

    private int $pdoAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldApp = Facade::getFacadeApplication();
        $this->oldResolver = Model::getConnectionResolver();
        $this->oldContainer = Container::getInstance();
        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
        $this->container->instance('config', new Repository);
        $this->container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $this->container));
        $this->container->scoped(AgendaLiveGuard::class);
        $this->actor = (new User)->forceFill(['id' => 1, 'status' => true]);
        $this->actor->setRelation('role', (new Role)->forceFill(['name' => User::ROLE_SUPER_ADMIN]));
        $this->container->instance(GateContract::class, new Gate($this->container, fn () => $this->actor));
        Schema::swap(new class
        {
            public function hasTable($name): bool
            {
                return false;
            }
        });
        $offline = new MySqlConnection(function () {
            $this->pdoAttempts++;
            throw new RuntimeException('PDO forbidden in pure Live admin tests.');
        }, 'offline_only', '', ['driver' => 'mysql']);
        $resolver = new ConnectionResolver(['offline' => $offline]);
        $resolver->setDefaultConnection('offline');
        Model::setConnectionResolver($resolver);
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();
        if ($this->oldResolver !== null) {
            Model::setConnectionResolver($this->oldResolver);
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->oldApp);
        Container::setInstance($this->oldContainer);
        $this->assertSame(0, $this->pdoAttempts);
        parent::tearDown();
    }

    public static function roles(): array
    {
        return [[User::ROLE_SUPER_ADMIN, true, true], [User::ROLE_PRODUCTION, true, true], [User::ROLE_NEWS, true, false], [User::ROLE_TVCLUB, true, false], [User::ROLE_SUPER_ADMIN, false, false]];
    }

    #[DataProvider('roles')]
    public function test_all_admin_screens_authorize_before_live_queries(string $role, bool $active, bool $allowed): void
    {
        $this->actor->status = $active;
        $this->actor->setRelation('role', (new Role)->forceFill(['name' => $role]));
        foreach ([Index::class, Form::class, Show::class] as $class) {
            try {
                $component = new $class;
                $class === Index::class ? $component->mount() : $component->mount(10);
                $this->assertTrue($allowed);
            } catch (AuthorizationException) {
                $this->assertFalse($allowed);
            }
        }
    }

    public static function actions(): array
    {
        return array_map(fn ($action) => [$action], ['markLive', 'end', 'cancel', 'reschedule', 'saveVisibility', 'saveArchive', 'unlinkArchive']);
    }

    #[DataProvider('actions')]
    public function test_direct_show_actions_are_blocked_when_schema_is_missing(string $action): void
    {
        $component = new Show;
        $component->streamId = 10;
        $component->expectedVersion = 1;
        $this->expectException(LiveStreamOperationException::class);
        $this->expectExceptionMessage('LIVE_SCHEMA');
        $component->$action();
    }

    public static function deniedActors(): array
    {
        return [[User::ROLE_NEWS, true], [User::ROLE_TVCLUB, true], [User::ROLE_PRODUCTION, false]];
    }

    #[DataProvider('deniedActors')]
    public function test_forged_livewire_mutations_are_denied_before_schema_or_database_access(string $role, bool $active): void
    {
        $this->actor->status = $active;
        $this->actor->setRelation('role', (new Role)->forceFill(['name' => $role]));
        $component = new Show;
        $component->streamId = 10;
        foreach (array_column(self::actions(), 0) as $action) {
            try {
                $component->$action();
                $this->fail('Unauthorized mutation must be denied.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(AuthorizationException::class);
        (new Form)->save();
    }

    public function test_create_action_cannot_fall_back_to_an_unguarded_write(): void
    {
        $this->expectExceptionMessage('LIVE_SCHEMA');
        (new Form)->save();
    }

    public function test_record_identity_and_loaded_version_cannot_be_changed_by_client(): void
    {
        foreach ([Form::class, Show::class] as $class) {
            foreach (['streamId', 'expectedVersion'] as $field) {
                $this->assertCount(1, (new ReflectionProperty($class, $field))->getAttributes(Locked::class));
            }
        }
    }

    public function test_exception_hook_uses_livewire_named_argument_contract(): void
    {
        $parameters = (new ReflectionMethod(HandlesLiveStreamErrors::class, 'exceptionHandlesLiveStreamErrors'))->getParameters();
        $this->assertSame('e', $parameters[0]->getName());
        $this->assertSame('stopPropagation', $parameters[1]->getName());
    }

    public function test_filters_bound_sort_direction_search_program_and_pagination(): void
    {
        $filter = LiveStreamFilters::normalize(['sort' => 'title desc; DROP TABLE users', 'direction' => 'invalid', 'search' => str_repeat('x', 200), 'program' => 'not-an-id', 'per_page' => 9999, 'status' => 'invalid', 'visibility' => 'secret']);
        $this->assertSame(LiveStream::START_WIB, $filter['sort']);
        $this->assertSame('asc', $filter['direction']);
        $this->assertSame(150, mb_strlen($filter['search']));
        $this->assertSame(10, $filter['per_page']);
        $this->assertNull($filter['program']);
        $this->assertNull($filter['status']);
        $this->assertNull($filter['visibility']);
    }

    public static function invalidDates(): array
    {
        return [['2026-02-30', ''], ['2027-01-01', '2026-01-01'], ['2026-01-01', '2028-01-01'], ['tomorrow', '']];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_or_excessive_date_filters_are_rejected(string $from, string $to): void
    {
        $this->expectException(ValidationException::class);
        LiveStreamFilters::normalize(compact('from', 'to'));
    }

    public function test_valid_leap_day_filter_keeps_wib_calendar_dates(): void
    {
        $filter = LiveStreamFilters::normalize(['from' => '2028-02-29', 'to' => '2028-03-01', 'sort' => 'title', 'direction' => 'desc', 'program' => 4]);
        $this->assertSame('2028-02-29', $filter['from']);
        $this->assertSame(LiveStream::TITLE_SQL, $filter['sort']);
        $this->assertSame(4, $filter['program']);
    }

    public function test_all_live_views_and_admin_layout_compile_without_rendering(): void
    {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        foreach (['livewire/admin/live-streams/index', 'livewire/admin/live-streams/form', 'livewire/admin/live-streams/show', 'livewire/admin/live-streams/feedback', 'layouts/admin'] as $view) {
            $compiled = $compiler->compileString(file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$view.'.blade.php'));
            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE), $view);
        }
    }

    public function test_scoped_handler_sanitizes_live_and_shared_livewire_blade_errors(): void
    {
        $pdo = new PDOException('PRIVATE_SENTINEL');
        $pdo->errorInfo = ['42S02', 1146, 'PRIVATE_SENTINEL'];
        $raw = new QueryException('offline', 'select * from live_streams where youtube_id = ?', ['PRIVATE_SENTINEL'], $pdo);
        $request = Request::create('/admin/live-streams');
        $route = (new Route('GET', '/admin/live-streams', fn () => null))->name('admin.live-streams.index');
        $request->setRouteResolver(fn () => $route);
        foreach ([LiveStreamErrors::forHandler($raw, $request), LiveStreamErrors::forHandler(new ViewException('View: resources/views/livewire/admin/live-streams/show.blade.php', 0, E_ERROR, __FILE__, __LINE__, $raw))] as $safe) {
            $this->assertInstanceOf(LiveStreamOperationException::class, $safe);
            $this->assertSame('LIVE_DATABASE', $safe->errorCode);
            $this->assertNull($safe->getPrevious());
            $this->assertStringNotContainsString('PRIVATE_SENTINEL', $safe->getMessage());
        }
        $other = EquipmentOperationException::database();
        $this->assertSame($other, LiveStreamErrors::forHandler($other));
        $this->assertSame($raw, LiveStreamErrors::forHandler($raw));
    }
}
