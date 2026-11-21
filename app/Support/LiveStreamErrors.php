<?php

namespace App\Support;

use App\Exceptions\LiveStreamOperationException;
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

final class LiveStreamErrors
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
        $code = null;
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof LiveStreamOperationException) {
                return $cause;
            }
            if ($cause instanceof DeadlockException) {
                $code = 'LIVE_CONCURRENCY';
                break;
            }
            if ($cause instanceof QueryException || $cause instanceof PDOException) {
                $code = match ((int) ($cause->errorInfo[1] ?? 0)) {
                    1062 => 'LIVE_DUPLICATE',
                    1205, 1213 => 'LIVE_CONCURRENCY',
                    default => ($cause->errorInfo[0] ?? (string) $cause->getCode()) === '40001' ? 'LIVE_CONCURRENCY' : 'LIVE_DATABASE',
                };
                break;
            }
        }
        if ($code === null && ($exception instanceof ValidationException || $exception instanceof AuthorizationException
            || $exception instanceof ModelNotFoundException || $exception instanceof AuthenticationException
            || $exception instanceof TokenMismatchException || $exception instanceof HttpResponseException || $exception instanceof HttpExceptionInterface)) {
            return $exception;
        }

        // No previous exception: SQL, bindings and input must not escape.
        return new LiveStreamOperationException($code ?? 'LIVE_UNEXPECTED');
    }

    public static function forHandler(Throwable $exception, ?Request $request = null): Throwable
    {
        if ($request?->routeIs('admin.live-streams.*', 'live.index', 'live.show')) {
            return self::sanitize($exception);
        }
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ViewException && preg_match('~resources[/\\\\]views[/\\\\]livewire[/\\\\]admin[/\\\\]live-streams[/\\\\]~', $cause->getMessage())) {
                return self::sanitize($exception);
            }
            if ($cause instanceof LiveStreamOperationException) {
                return $cause;
            }
            foreach ([$cause->getFile(), ...array_column($cause->getTrace(), 'file')] as $file) {
                if (preg_match('~/app/Livewire/Admin/LiveStreams/|/resources/views/livewire/admin/live-streams/~', str_replace('\\', '/', $file))) {
                    return self::sanitize($exception);
                }
            }
        }

        return $exception;
    }
}
