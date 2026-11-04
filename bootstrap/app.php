<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception): void {
            Log::error('Database operation failed.', [
                'exception_type' => $exception::class,
                'sql_state' => $exception->errorInfo[0] ?? null,
                'driver_code' => $exception->errorInfo[1] ?? null,
            ]);
        })->stop();
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?Response {
            if (config('app.debug') || $exception->getStatusCode() < 500) {
                return null;
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Server Error'], $exception->getStatusCode())
                : response('Server Error', $exception->getStatusCode());
        });
    })->create();
