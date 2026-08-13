<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
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
        $broadcastMode = config('traccar.broadcast_mode', 'light');

        if ($broadcastMode === 'forward' && blank(config('traccar.forward.secret'))) {
            $broadcastMode = 'light';
        }

        if (config('traccar.broadcast_positions', true)
            && ! in_array($broadcastMode, ['off', 'forward'], true)) {
            $interval = max(15, min(120, (int) config('traccar.broadcast_interval_seconds', 30)));
            $overlap = max(2, (int) ceil($interval / 10));

            $broadcast = $schedule->command('traccar:broadcast-positions')
                ->withoutOverlapping($overlap);

            if ($interval <= 15) {
                $broadcast->everyFifteenSeconds();
            } elseif ($interval <= 30) {
                $broadcast->everyThirtySeconds();
            } else {
                $broadcast->everyMinute();
            }

            if ($broadcastMode === 'light') {
                $eventMinutes = max(1, (int) config('traccar.broadcast_events_interval_minutes', 2));
                $events = $schedule->command('traccar:process-position-events')
                    ->withoutOverlapping(max(3, $eventMinutes + 1));

                if ($eventMinutes === 1) {
                    $events->everyMinute();
                } else {
                    $events->cron("*/{$eventMinutes} * * * *");
                }
            }
        }

        $broadcastOff = ! config('traccar.broadcast_positions', true)
            || in_array($broadcastMode, ['off', 'forward'], true);

        if ($broadcastOff
            && $broadcastMode !== 'forward'
            && filter_var(config('traccar.schedule_position_events', true), FILTER_VALIDATE_BOOL)) {
            $eventMinutes = max(1, (int) config('traccar.position_events_interval_minutes', 5));
            $events = $schedule->command('traccar:process-position-events')
                ->withoutOverlapping(max(3, $eventMinutes + 1));

            if ($eventMinutes === 1) {
                $events->everyMinute();
            } else {
                $events->cron("*/{$eventMinutes} * * * *");
            }
        }

        if (config('firebase.enabled') && config('firebase.event_notifications_enabled')) {
            $schedule->command('devices:check-connectivity')
                ->everyFiveMinutes()
                ->withoutOverlapping(5);
        }

        // Backup: Traccar-native geofenceEnter/Exit → FCM (works in forward mode too).
        // Always schedule when FCM is on; the command itself no-ops if geofence push is disabled.
        if (filter_var(config('firebase.enabled', false), FILTER_VALIDATE_BOOL)
            || filter_var(config('services.firebase.enabled', false), FILTER_VALIDATE_BOOL)) {
            $schedule->command('traccar:dispatch-geofence-pushes')
                ->everyMinute()
                ->withoutOverlapping(2);
        }

        $schedule->command('maintenance:check')
            ->hourly()
            ->withoutOverlapping(10);

        $schedule->command('commands:reconcile-status')
            ->everyMinute()
            ->withoutOverlapping(2);
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
            'mobile.app_user' => \App\Http\Middleware\EnsureMobileAppUser::class,
            'mobile.entitlement' => \App\Http\Middleware\EnsureMobileEntitlement::class,
            'mobile.app_version' => \App\Http\Middleware\EnsureMobileAppVersion::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\DiscardBrokenAuthSession::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\RestrictScrapers::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            if ($user) {
                return route(app(\App\Services\Authorization\RbacService::class)->panelRouteFor($user));
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $message = (string) __('app.auth.please_login_again');
            $loginUrl = route('login');

            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code' => 'session_expired',
                ], 419);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code' => 'session_expired',
                    'redirect' => $loginUrl,
                ], 419);
            }

            return redirect()->guest($loginUrl)
                ->with('session_expired', $message);
        });

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
