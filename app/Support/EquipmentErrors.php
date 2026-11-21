<?php

namespace App\Support;

use App\Exceptions\EquipmentOperationException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class EquipmentErrors
{
    public static function run(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            throw self::sanitize($exception);
        }
    }

    public static function sanitize(Throwable $exception): Throwable
    {
        if ($exception instanceof EquipmentOperationException) {
            return $exception;
        }

        // View/render exceptions may wrap the original SQL failure.
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof EquipmentOperationException) {
                return $cause;
            }
            if ($cause instanceof DeadlockException) {
                return EquipmentOperationException::concurrency();
            }
            if ($cause instanceof QueryException || $cause instanceof PDOException) {
                return match ((int) ($cause->errorInfo[1] ?? 0)) {
                    1062 => EquipmentOperationException::duplicate(),
                    1205, 1213 => EquipmentOperationException::concurrency(),
                    default => ($cause->errorInfo[0] ?? (string) $cause->getCode()) === '40001'
                        ? EquipmentOperationException::concurrency()
                        : EquipmentOperationException::database(),
                };
            }
        }

        // Retain validation, authorization and normal 404/HTTP behavior.
        if ($exception instanceof ValidationException || $exception instanceof AuthorizationException
            || $exception instanceof AuthenticationException || $exception instanceof TokenMismatchException
            || $exception instanceof ModelNotFoundException || $exception instanceof HttpResponseException
            || $exception instanceof HttpExceptionInterface) {
            return $exception;
        }

        return EquipmentOperationException::unexpected();
    }

    /** Laravel fallback for component reads, route binding and Blade failures. */
    public static function forHandler(Throwable $exception, ?Request $request = null): Throwable
    {
        if ($request?->routeIs('admin.inventory.*', 'admin.equipment-loans.*', 'admin.equipment-reports.*')) {
            return self::sanitize($exception);
        }

        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof EquipmentOperationException) {
                return $cause;
            }
            // Compiled Blade filenames no longer identify their source. Laravel
            // appends the original view path to ViewException, including on the
            // shared Livewire update endpoint. Inspect it; never log the message.
            if ($cause instanceof ViewException && preg_match('~resources[/\\\\]views[/\\\\]livewire[/\\\\]admin[/\\\\](?:inventory|equipment-loans)[/\\\\]~', $cause->getMessage())) {
                return self::sanitize($exception);
            }
            foreach ([$cause->getFile(), ...array_column($cause->getTrace(), 'file')] as $file) {
                $file = str_replace('\\', '/', $file);
                if (preg_match('~/app/(?:Livewire/Admin/(?:Inventory|EquipmentLoans)/|Services/(?:EquipmentLoan(?:Checkout|Return|Loss|Extension|Correction)|EquipmentReports|InventoryUnitRecovery)\.php$)~', $file)
                    || preg_match('~/resources/views/livewire/admin/(?:inventory|equipment-loans)/~', $file)) {
                    return self::sanitize($exception);
                }
            }
            // Route binding can fail entirely inside Eloquent, before mount.
            if ($cause instanceof QueryException && preg_match('/\b(?:inventory_categories|inventory_units|equipment_loans|equipment_loan_items|equipment_audit_events)\b/', $cause->getSql())) {
                return self::sanitize($exception);
            }
        }

        return $exception;
    }
}
