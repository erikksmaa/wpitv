<?php

namespace Tests\Unit;

use App\Exceptions\EquipmentOperationException;
use App\Livewire\Concerns\HandlesEquipmentErrors;
use App\Models\EquipmentAuditEvent;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Services\EquipmentLoanCheckout;
use App\Services\EquipmentLoanCorrection;
use App\Services\EquipmentLoanExtension;
use App\Services\EquipmentLoanLoss;
use App\Services\EquipmentLoanReturn;
use App\Services\EquipmentReports;
use App\Services\InventoryUnitRecovery;
use App\Support\EquipmentErrors;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Livewire\EventBus;
use Livewire\Features\SupportLifecycleHooks\SupportLifecycleHooks;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/** Run with --no-configuration; Composer's PHPUnit launcher autoloads without bootstrapping Laravel/testing. */
class EquipmentSafetyTest extends TestCase
{
    private const SENTINEL = 'SYNTHETIC_PRIVATE_SENTINEL';

    private $previousApplication;

    private Container $previousContainer;

    private $previousResolver;

    private string $previousTimezone;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousApplication = Facade::getFacadeApplication();
        $this->previousContainer = Container::getInstance();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Jakarta');
        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
        $this->container->instance('config', new Repository(['app' => ['debug' => true]]));
        $this->container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $this->container));
        // Even an accidental query in these pure tests cannot open a connection.
        Model::setConnectionResolver($this->blockedResolver(new RuntimeException('Database access forbidden.')));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        date_default_timezone_set($this->previousTimezone);
        Model::unsetConnectionResolver();
        if ($this->previousResolver !== null) {
            Model::setConnectionResolver($this->previousResolver);
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function blockedResolver(Throwable $failure): ConnectionResolverInterface
    {
        return new class($failure) implements ConnectionResolverInterface
        {
            public function __construct(private Throwable $failure) {}

            public function connection($name = null)
            {
                throw $this->failure;
            }

            public function getDefaultConnection()
            {
                return 'forbidden';
            }

            public function setDefaultConnection($name) {}
        };
    }

    private function sqlFailure(int $driverCode = 1146, string $state = '42S02', string $table = 'equipment_loans'): QueryException
    {
        $pdo = new PDOException(self::SENTINEL);
        $pdo->errorInfo = [$state, $driverCode, self::SENTINEL];

        return new QueryException('synthetic', 'insert into '.$table.' (notes) values (?)', [self::SENTINEL], $pdo);
    }

    public static function errorCodes(): array
    {
        return [[1062, '23000', 'EQ_DUPLICATE'], [1213, '40001', 'EQ_CONCURRENCY'],
            [1205, 'HY000', 'EQ_CONCURRENCY'], [0, '40001', 'EQ_CONCURRENCY'], [1146, '42S02', 'EQ_DATABASE']];
    }

    #[DataProvider('errorCodes')]
    public function test_sql_errors_have_safe_distinct_codes_and_no_previous(int $driverCode, string $state, string $expected): void
    {
        $raw = $this->sqlFailure($driverCode, $state);
        $this->assertStringContainsString(self::SENTINEL, $raw->getMessage());
        $safe = EquipmentErrors::sanitize(new RuntimeException(self::SENTINEL, 0, $raw));
        $this->assertInstanceOf(EquipmentOperationException::class, $safe);
        $this->assertSame($expected, $safe->errorCode);
        $this->assertNull($safe->getPrevious());
        $this->assertStringNotContainsString(self::SENTINEL, $safe->getMessage());
        $this->assertStringNotContainsString('insert into', $safe->getMessage());
    }

    public function test_debug_handler_renders_and_logs_only_safe_content(): void
    {
        $logger = new class extends AbstractLogger
        {
            public array $entries = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->entries[] = [$level, (string) $message, $context];
            }
        };
        $this->container->instance(LoggerInterface::class, $logger);
        $handler = new Handler($this->container);
        $handler->map(fn (Throwable $e) => EquipmentErrors::forHandler($e));
        $raw = $this->sqlFailure();
        $handler->report($raw);
        $this->assertSame([['warning', 'Equipment operation failed.', ['error_code' => 'EQ_DATABASE']]], $logger->entries);
        foreach (['application/json', 'text/html'] as $accept) {
            $request = Request::create('/admin/equipment-loans');
            $request->headers->set('Accept', $accept);
            $response = $handler->render($request, $raw);
            $this->assertSame(503, $response->getStatusCode());
            $this->assertStringContainsString('EQ_DATABASE', $response->getContent());
            $this->assertStringNotContainsString(self::SENTINEL, $response->getContent());
            $this->assertStringNotContainsString('trace', $response->getContent());
        }
    }

    public function test_framework_deadlocks_and_unknown_errors_do_not_keep_the_original_failure(): void
    {
        foreach ([
            [new DeadlockException(self::SENTINEL, 0, $this->sqlFailure(1213, '40001')), 'EQ_CONCURRENCY'],
            [new RuntimeException(self::SENTINEL), 'EQ_UNEXPECTED'],
        ] as [$raw, $code]) {
            $safe = EquipmentErrors::sanitize($raw);
            $this->assertSame($code, $safe->errorCode);
            $this->assertNull($safe->getPrevious());
            $this->assertStringNotContainsString(self::SENTINEL, $safe->getMessage());
        }
    }

    public function test_module_scoping_keeps_other_modules_and_expected_http_errors_intact(): void
    {
        $other = $this->sqlFailure(table: 'news');
        $this->assertSame($other, EquipmentErrors::forHandler($other));
        $request = Request::create('/admin/equipment-loans');
        $request->setRouteResolver(fn () => (new Route('GET', '/', fn () => null))->name('admin.equipment-loans.index'));
        $this->assertSame('EQ_UNEXPECTED', EquipmentErrors::forHandler(new RuntimeException(self::SENTINEL), $request)->errorCode);
        foreach ([new AuthorizationException, new AuthenticationException, new TokenMismatchException,
            ValidationException::withMessages(['items' => 'Invalid selection.'])] as $expected) {
            $this->assertSame($expected, EquipmentErrors::forHandler($expected, $request));
        }
    }

    public function test_livewire_conflicts_use_field_errors_without_recursive_exception_hooks(): void
    {
        $component = new class
        {
            use HandlesEquipmentErrors;

            public array $errors = [];

            public function addError($key, $message): void
            {
                $this->errors[$key] = $message;
            }
        };
        $stopped = false;
        $component->exceptionHandlesEquipmentErrors(EquipmentOperationException::stale('items'), function () use (&$stopped): void {
            $stopped = true;
        });
        $this->assertTrue($stopped);
        $this->assertStringContainsString('EQ_STALE', $component->errors['items']);
        // Exercise Livewire's real nested wrap/hook dispatch, without booting an
        // application. The hook must leave system failures for Laravel's mapper.
        $hook = new SupportLifecycleHooks;
        $hook->setComponent($component);
        $this->container->singleton(EventBus::class);
        $calls = 0;
        \Livewire\on('exception', function ($target, $error, $stop) use ($hook, &$calls): void {
            $calls++;
            $hook->exception($error, $stop);
        });
        $raw = $this->sqlFailure();
        $hook->exception($raw, fn () => null);
        $this->assertSame(0, $calls);
        $safe = EquipmentErrors::forHandler($raw);
        $this->assertSame('EQ_DATABASE', $safe->errorCode);
        $this->assertNull($safe->getPrevious());
    }

    public function test_wrapped_blade_failures_on_the_shared_update_endpoint_are_sanitized(): void
    {
        foreach (['inventory/units/show.blade.php', 'equipment-loans/show.blade.php'] as $view) {
            $raw = new ViewException(self::SENTINEL.' (View: C:/project/resources/views/livewire/admin/'.$view.')', 0, 1, 'compiled.php', 1, new RuntimeException(self::SENTINEL));
            $safe = EquipmentErrors::forHandler($raw);
            $this->assertSame('EQ_UNEXPECTED', $safe->errorCode);
            $this->assertNull($safe->getPrevious());
            $this->assertStringNotContainsString(self::SENTINEL, $safe->getMessage());
        }
    }

    public static function serviceBoundaries(): array
    {
        return [[EquipmentLoanCheckout::class, 'checkout'], [EquipmentLoanReturn::class, 'record'],
            [EquipmentLoanLoss::class, 'record'], [EquipmentLoanExtension::class, 'record'],
            [EquipmentLoanCorrection::class, 'record'], [InventoryUnitRecovery::class, 'reactivate'],
            [EquipmentReports::class, 'page'], [EquipmentReports::class, 'download']];
    }

    #[DataProvider('serviceBoundaries')]
    public function test_public_service_boundaries_sanitize_failures_before_transactions(string $class, string $method): void
    {
        $failure = $this->sqlFailure();
        Model::setConnectionResolver($this->blockedResolver($failure));
        $this->container->instance('db', new class($failure)
        {
            public function __construct(private Throwable $failure) {}

            public function transactionLevel()
            {
                throw $this->failure;
            }

            public function transaction(...$arguments)
            {
                throw $this->failure;
            }
        });
        $actor = new User;
        $args = match ($method) {
            'reactivate' => [$actor, 1, 1, 'Synthetic inspection.', true],
            'page' => [$actor, [], 10],
            'download' => [$actor, [], 'loans'],
            default => [$actor, '00000000-0000-4000-8000-000000000001', []],
        };
        try {
            (new $class)->{$method}(...$args);
            $this->fail('Expected a safe failure.');
        } catch (EquipmentOperationException $safe) {
            $this->assertSame('EQ_DATABASE', $safe->errorCode);
            $this->assertNull($safe->getPrevious());
        }
    }

    public static function utcModels(): array
    {
        return [[InventoryCategory::class], [InventoryUnit::class], [EquipmentLoan::class],
            [EquipmentLoanItem::class], [EquipmentAuditEvent::class]];
    }

    #[DataProvider('utcModels')]
    public function test_automatic_and_explicit_timestamps_roundtrip_utc_microseconds(string $class): void
    {
        $local = CarbonImmutable::parse('2026-11-08 00:00:00.123456', 'Asia/Jakarta');
        CarbonImmutable::setTestNow($local);
        $model = new $class;
        $model->updateTimestamps();
        $this->assertSame('2026-11-07 17:00:00.123456', $model->getAttributes()['created_at']);
        $this->assertSame('UTC', $model->created_at->timezoneName);
        $model->created_at = $local;
        $this->assertSame('2026-11-07 17:00:00.123456', $model->getAttributes()['created_at']);
        if ($model->getUpdatedAtColumn() !== null) {
            $this->assertSame('2026-11-07 17:00:00.123456', $model->getAttributes()['updated_at']);
            $model->updated_at = $local->addMicrosecond();
            $this->assertSame('2026-11-07 17:00:00.123457', $model->getAttributes()['updated_at']);
        } else {
            $this->assertArrayNotHasKey('updated_at', $model->getAttributes());
        }
        $hydrated = (new $class)->setRawAttributes($model->getAttributes(), true);
        $this->assertSame('2026-11-08 00:00:00.123456', $hydrated->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s.u'));
        $this->assertSame('2026-11-07 17:00:00.123456', $hydrated->created_at->format('Y-m-d H:i:s.u'));
    }

    public function test_lifecycle_dates_keep_microseconds_and_due_history_independent(): void
    {
        $utc = '2026-11-07 17:00:00.123456';
        $loan = new EquipmentLoan;
        foreach (['borrowed_at', 'due_at', 'initial_due_at'] as $field) {
            $loan->{$field} = CarbonImmutable::parse($utc, 'UTC')->timezone('Asia/Jakarta');
            $this->assertSame($utc, $loan->getAttributes()[$field]);
        }
        $item = new EquipmentLoanItem;
        foreach (['returned_at', 'due_at_on_return', 'lost_at'] as $field) {
            $item->{$field} = CarbonImmutable::parse($utc, 'UTC')->timezone('Asia/Jakarta');
            $this->assertSame($utc, $item->getAttributes()[$field]);
            $this->assertSame('UTC', $item->{$field}->timezoneName);
        }
        $loan->due_at = $loan->due_at->addDay();
        $this->assertSame($utc, $loan->initial_due_at->format('Y-m-d H:i:s.u'));
        $this->assertSame($utc, $item->due_at_on_return->format('Y-m-d H:i:s.u'));
        $this->assertSame('2026-11-08 17:00:00.123456', $loan->due_at->format('Y-m-d H:i:s.u'));
    }

    public function test_calendar_loan_includes_the_entire_due_day_in_jakarta(): void
    {
        $input = [
            'borrower_name' => 'Synthetic', 'student_number' => 'T1', 'study_program' => 'Studio',
            'whatsapp_number' => '62812345678', 'purpose' => 'QA',
            'borrowed_at' => '2025-12-31', 'due_at' => '2025-12-31',
            'items' => [['unit_id' => 1, 'version' => 1]],
        ];
        $payload = (new EquipmentLoanCheckout)->canonicalPayload($input);
        $this->assertSame('2025-12-30 17:00:00.000000', $payload['borrowed_at']);
        $this->assertSame('2025-12-31 16:59:59.999999', $payload['due_at']);
        $extension = (new EquipmentLoanExtension)->canonicalPayload([
            'loan_id' => 1, 'expected_version' => 1, 'new_due_at' => '2026-01-01', 'reason' => 'QA',
        ]);
        $this->assertSame('2026-01-01 16:59:59.999999', $extension['new_due_at']);
    }

    public function test_wib_request_dates_and_idempotency_hashes_ignore_the_process_timezone(): void
    {
        $hashes = null;
        foreach (['UTC', 'Asia/Jakarta', 'America/New_York'] as $timezone) {
            date_default_timezone_set($timezone);
            $return = (new EquipmentLoanReturn)->canonicalPayload([
                'loan_id' => 1, 'expected_version' => 1, 'items' => [
                    ['item_id' => 1, 'returned_at' => '2026-11-08T00:00:00', 'condition_in' => 'good', 'return_notes' => null],
                ],
            ]);
            $loss = (new EquipmentLoanLoss)->canonicalPayload([
                'loan_id' => 1, 'expected_version' => 1, 'items' => [
                    ['item_id' => 1, 'lost_at' => '2026-11-08T00:00:00', 'loss_notes' => 'Synthetic reason.'],
                ],
            ]);
            $extension = (new EquipmentLoanExtension)->canonicalPayload([
                'loan_id' => 1, 'expected_version' => 1, 'new_due_at' => '2026-11-08T00:00:00', 'reason' => 'Synthetic reason.',
            ]);
            $this->assertSame('2026-11-07 17:00:00.000000', $return['items'][0]['returned_at']);
            $this->assertSame('2026-11-07 17:00:00.000000', $loss['items'][0]['lost_at']);
            $this->assertSame('2026-11-07 17:00:00.000000', $extension['new_due_at']);
            $current = array_map(fn ($payload) => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), [$return, $loss, $extension]);
            $hashes ??= $current;
            $this->assertSame($hashes, $current);
        }
    }
}
