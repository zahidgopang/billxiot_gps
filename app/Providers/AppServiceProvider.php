<?php

namespace App\Providers;

use App\Auth\TcAwareUserProvider;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TcAwareUserProvider::class, function ($app) {
            return new TcAwareUserProvider(
                $app['hash'],
                User::class,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('device-ingest', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            $key = $request->ip().'|'.strtolower((string) $request->input('email', ''));

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('traccar-forward', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });

        Paginator::useBootstrapFive();

        Auth::provider('tc_aware', function ($app, array $config) {
            return new TcAwareUserProvider(
                $app['hash'],
                $config['model'],
            );
        });

        View::composer('*', function ($view): void {
            $locale = app()->getLocale();
            $view->with('htmlLang', $locale);
            $view->with('htmlDir', $locale === 'ar' ? 'rtl' : 'ltr');
            $view->with('isRtl', $locale === 'ar');
        });
    }
}
