<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // Custom probe: returns 503 (not 500) when DB is down; no web middleware.
            Route::get('/up', HealthController::class);
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        if (config('traccar.broadcast_positions', true)) {
            $schedule->command('traccar:broadcast-positions')
                ->everyFiveSeconds()
                ->withoutOverlapping(2);
        }

        if (config('firebase.enabled') && config('firebase.event_notifications_enabled')) {
            $schedule->command('devices:check-connectivity')
                ->everyFiveMinutes()
                ->withoutOverlapping(5);
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'map.access' => \App\Http\Middleware\EnsureMapAccess::class,
            'maps.tracking' => \App\Http\Middleware\EnsureMapTrackingAccess::class,
            'user.active' => \App\Http\Middleware\EnsureActiveUser::class,
            'tracker.access' => \App\Http\Middleware\EnsureTrackerAccess::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'panel' => \App\Http\Middleware\EnsurePanelAccess::class,
            'mobile.end_user' => \App\Http\Middleware\EnsureMobileEndUser::class,
            'mobile.entitlement' => \App\Http\Middleware\EnsureMobileEntitlement::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\DiscardBrokenAuthSession::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\RestrictScrapers::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return null;
            }

            $status = 500;
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }

            try {
                report($e);
            } catch (Throwable) {
                // Logging must not prevent a JSON error response.
            }

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Something went wrong. Please try again.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => 'server_error',
            ], $status);
        });
    })
    ->withBroadcasting(
        channels: __DIR__ . '/../routes/channels.php'
    )
    ->create();
