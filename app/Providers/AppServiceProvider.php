<?php

namespace App\Providers;

use App\Auth\TcAwareUserProvider;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
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
