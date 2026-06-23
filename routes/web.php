<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDevicesController;
use App\Http\Controllers\GeofenceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\UserFleetMapController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DeviceStockController;
use App\Http\Controllers\Admin\DeviceStockSaleController;
use App\Http\Controllers\Admin\ClientStockBalanceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AndroidAppController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapAccessController;
use App\Http\Controllers\VehicleAlertController;
/*
|--------------------------------------------------------------------------
| Public Route
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');
Route::get('/robots.txt', \App\Http\Controllers\RobotsController::class)->name('robots');

Route::get('/locale/{locale}', [\App\Http\Controllers\LocaleController::class, 'switch'])
    ->whereIn('locale', ['en', 'ar'])
    ->name('locale.switch');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES USER
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'user.active', 'tracker.access'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | User Dashboard Redirect Logic
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function () {
        return redirect()->route(app(\App\Services\Authorization\RbacService::class)->panelRouteFor(auth()->user()));
    })->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | USER PANEL ROUTES
    |--------------------------------------------------------------------------
    */
    Route::get('/user/dashboard', [UserController::class, 'dashboard'])
        ->name('user.dashboard');

    Route::get('/user/alerts', [VehicleAlertController::class, 'index'])
        ->name('user.alerts.index');

    Route::post('/map-tour/preference', [\App\Http\Controllers\MapTourPreferenceController::class, 'update'])
        ->name('map.tour.preference');

    Route::post('/map-session/end', [\App\Http\Controllers\MapSessionController::class, 'end'])
        ->name('map.session.end');

    Route::get('/user/devices/{device}/launch-map', [MapAccessController::class, 'launchUserMap'])
        ->name('user.devices.launch-map');

    Route::middleware('map.access')->group(function () {
        Route::get('/user/device/{token}/map', [MapController::class, 'map'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.map');
        Route::get('/user/device/{token}/history-json', [MapController::class, 'historyJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.history.json');
        Route::get('/user/device/{token}/live-json', [MapController::class, 'liveJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.live.json');
        Route::get('/user/device/{token}/summary-json', [MapController::class, 'summaryJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.summary.json');
        Route::get('/user/device/{token}/alerts-json', [MapController::class, 'alertsJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.alerts.json');
        Route::get('/user/device/{token}/reverse-geocode', [MapController::class, 'reverseGeocode'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.reverse.geocode');

        Route::get('/user/device/{token}/geofences-json', [GeofenceController::class, 'indexJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.geofences.json');
        Route::post('/user/device/{token}/geofences-save', [GeofenceController::class, 'store'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('user.device.geofences.save');
    });

    // User Profile Apis
    Route::get('/user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::post('/user/profile/update', [UserController::class, 'updateProfile'])->name('user.profile.update');
    Route::get('/user/change-password', [UserController::class, 'changePassword'])->name('user.change.password');
    Route::post('/user/change-password/update', [UserController::class, 'updatePassword'])->name('user.password.update');

    Route::delete('/user/geofence/{id}', [GeofenceController::class, 'destroy'])
        ->whereNumber('id')
        ->name('user.geofence.destroy');
    Route::post('/user/geofence/{id}/update', [GeofenceController::class, 'update'])
        ->whereNumber('id')
        ->name('user.geofence.update');

    // User Devices Routes
    Route::prefix('user/devices')->name('user.devices.')->group(function () {
        Route::get('/', [UserDevicesController::class, 'index'])->name('index');
        Route::get('/fleet-map', [UserDevicesController::class, 'fleetMap'])->name('fleet-map');
        Route::get('/fleet-map/live-json', [UserDevicesController::class, 'fleetMapLiveJson'])->name('fleet-map.live-json');
        Route::get('/live-json', [UserDevicesController::class, 'liveJson'])->name('live-json');
    });

    /*
    |--------------------------------------------------------------------------
    | USER PROFILE ROUTES
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| ADMIN PANEL ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'panel:admin', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('clients', \App\Http\Controllers\Admin\ClientController::class)
            ->except(['show', 'destroy']);
        Route::get('clients/{client}/users', [\App\Http\Controllers\Admin\ClientController::class, 'users'])
            ->name('clients.users');
        Route::get('clients/{client}/stock-balance', [ClientStockBalanceController::class, 'show'])
            ->name('clients.stock-balance');
        Route::get('clients/{client}/devices', [\App\Http\Controllers\Admin\ClientController::class, 'devices'])
            ->name('clients.devices');

        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])
            ->name('users.toggle-status');
        Route::resource('users', AdminUserController::class);
        Route::post('subscriptions/{subscription}/renew', [AdminSubscriptionController::class, 'renew'])
            ->name('subscriptions.renew');
        Route::get('subscriptions/{subscription}/histories', [AdminSubscriptionController::class, 'histories'])
            ->name('subscriptions.histories');
        Route::post('subscriptions/{subscription}/client-invoice/pay', [AdminSubscriptionController::class, 'markClientInvoicePaid'])
            ->name('subscriptions.client-invoice.pay');
        Route::post('subscriptions/{subscription}/client-invoice/cancel', [AdminSubscriptionController::class, 'cancelClientInvoice'])
            ->name('subscriptions.client-invoice.cancel');
        Route::get('subscription-plans/{subscriptionPlan}/pricing', [AdminSubscriptionController::class, 'planPricing'])
            ->name('subscription-plans.pricing');
        Route::get('subscriptions/device-pricing', [AdminSubscriptionController::class, 'devicePricing'])
            ->name('subscriptions.device-pricing');
        Route::resource('subscriptions', AdminSubscriptionController::class);

        Route::resource('subscription-plans', \App\Http\Controllers\Admin\SubscriptionPlanController::class)
            ->except(['show']);
        Route::get('billing-invoices', [\App\Http\Controllers\Admin\BillingInvoiceController::class, 'index'])
            ->name('billing-invoices.index');
        Route::get('billing-invoices/{billingInvoice}', [\App\Http\Controllers\Admin\BillingInvoiceController::class, 'show'])
            ->name('billing-invoices.show');
        Route::post('billing-invoices/{billingInvoice}/payments', [\App\Http\Controllers\Admin\BillingInvoiceController::class, 'storePayment'])
            ->name('billing-invoices.payments.store');
        Route::get('reports/profit-loss', [\App\Http\Controllers\Admin\ProfitLossReportController::class, 'index'])
            ->name('reports.profit-loss');
        Route::patch('devices/{device}/toggle-status', [AdminDeviceController::class, 'toggleStatus'])
            ->name('devices.toggle-status');
        Route::resource('devices', AdminDeviceController::class);
        Route::get('device-stock/repairs', [DeviceStockController::class, 'repairs'])
            ->name('device-stock.repairs');
        Route::resource('device-stock', DeviceStockController::class);
        Route::resource('device-stock-sales', DeviceStockSaleController::class)
            ->only(['index', 'create', 'store', 'show']);

        Route::get('activity-log', [ActivityLogController::class, 'index'])
            ->name('activity-log.index');

        Route::get('contact-messages', [\App\Http\Controllers\Admin\ContactMessageController::class, 'index'])
            ->name('contact-messages.index');
        Route::get('contact-messages/{contactMessage}', [\App\Http\Controllers\Admin\ContactMessageController::class, 'show'])
            ->name('contact-messages.show');
        Route::patch('contact-messages/{contactMessage}', [\App\Http\Controllers\Admin\ContactMessageController::class, 'update'])
            ->name('contact-messages.update');

        Route::middleware('maps.tracking')->group(function () {
            Route::get('locations', [\App\Http\Controllers\Admin\LocationHistoryController::class, 'index'])
                ->name('locations.index');

            Route::get('locations/live-json', [\App\Http\Controllers\Admin\LocationHistoryController::class, 'liveJson'])
                ->name('locations.live-json');

            Route::get('locations/device/{device}/launch-map', [MapAccessController::class, 'launchAdminMap'])
                ->name('locations.launch-map');

            Route::get('users/{user}/fleet-map', [UserFleetMapController::class, 'show'])
                ->name('users.fleet-map');
            Route::get('users/{user}/fleet-map/live-json', [UserFleetMapController::class, 'liveJson'])
                ->name('users.fleet-map.live-json');
        });

        Route::middleware('map.access')->group(function () {
        Route::get('device/{token}/map', [MapController::class, 'map'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.map');
        Route::get('device/{token}/history-json', [MapController::class, 'historyJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.history.json');
        Route::get('device/{token}/live-json', [MapController::class, 'liveJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.live.json');
        Route::get('device/{token}/summary-json', [MapController::class, 'summaryJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.summary.json');
        Route::get('device/{token}/alerts-json', [MapController::class, 'alertsJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.alerts.json');
        Route::get('device/{token}/reverse-geocode', [MapController::class, 'reverseGeocode'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.reverse.geocode');
        Route::get('device/{token}/geofences-json', [GeofenceController::class, 'indexJson'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.geofences.json');
        Route::post('device/{token}/geofences-save', [GeofenceController::class, 'store'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('device.geofences.save');
        });

        Route::delete('geofence/{id}', [GeofenceController::class, 'destroy'])
            ->whereNumber('id')
            ->name('geofence.destroy');
        Route::post('geofence/{id}/update', [GeofenceController::class, 'update'])
            ->whereNumber('id')
            ->name('geofence.update');
    });

/*
|--------------------------------------------------------------------------
| CLIENT PANEL (fleet / company managers)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'panel:client', 'can:client-panel'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Client\DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('purchases', [\App\Http\Controllers\Client\PurchaseHistoryController::class, 'index'])
            ->name('purchases.index');
        Route::get('purchases/{sale}', [\App\Http\Controllers\Client\PurchaseHistoryController::class, 'show'])
            ->name('purchases.show');

        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])
            ->name('users.toggle-status');
        Route::resource('users', AdminUserController::class)->except(['destroy', 'show']);

        Route::patch('devices/{device}/toggle-status', [AdminDeviceController::class, 'toggleStatus'])
            ->name('devices.toggle-status');
        Route::resource('devices', AdminDeviceController::class)->except(['destroy']);
        Route::get('stock-balance', [ClientStockBalanceController::class, 'show'])
            ->name('stock-balance');

        Route::post('subscriptions/{subscription}/renew', [AdminSubscriptionController::class, 'renew'])
            ->name('subscriptions.renew');
        Route::get('subscriptions/{subscription}/histories', [AdminSubscriptionController::class, 'histories'])
            ->name('subscriptions.histories');
        Route::post('subscriptions/{subscription}/client-invoice/pay', [AdminSubscriptionController::class, 'markClientInvoicePaid'])
            ->name('subscriptions.client-invoice.pay');
        Route::post('subscriptions/{subscription}/client-invoice/cancel', [AdminSubscriptionController::class, 'cancelClientInvoice'])
            ->name('subscriptions.client-invoice.cancel');
        Route::get('subscription-plans/{subscriptionPlan}/pricing', [AdminSubscriptionController::class, 'planPricing'])
            ->name('subscription-plans.pricing');
        Route::get('subscriptions/device-pricing', [AdminSubscriptionController::class, 'devicePricing'])
            ->name('subscriptions.device-pricing');
        Route::resource('subscriptions', AdminSubscriptionController::class)->except(['destroy']);

        Route::get('billing-invoices', [\App\Http\Controllers\Admin\BillingInvoiceController::class, 'index'])
            ->name('billing-invoices.index');
        Route::get('billing-invoices/{billingInvoice}', [\App\Http\Controllers\Admin\BillingInvoiceController::class, 'show'])
            ->name('billing-invoices.show');
        Route::post('billing-invoices/{billingInvoice}/payments', [\App\Http\Controllers\Admin\BillingInvoiceController::class, 'storePayment'])
            ->name('billing-invoices.payments.store');
        Route::get('reports/profit-loss', [\App\Http\Controllers\Admin\ProfitLossReportController::class, 'index'])
            ->name('reports.profit-loss');

        Route::get('activity-log', [ActivityLogController::class, 'index'])
            ->name('activity-log.index');

        Route::middleware('maps.tracking')->group(function () {
            Route::get('locations', [\App\Http\Controllers\Admin\LocationHistoryController::class, 'index'])
                ->name('locations.index');

            Route::get('locations/live-json', [\App\Http\Controllers\Admin\LocationHistoryController::class, 'liveJson'])
                ->name('locations.live-json');

            Route::get('locations/device/{device}/launch-map', [MapAccessController::class, 'launchAdminMap'])
                ->name('locations.launch-map');

            Route::get('users/{user}/fleet-map', [UserFleetMapController::class, 'show'])
                ->name('users.fleet-map');
            Route::get('users/{user}/fleet-map/live-json', [UserFleetMapController::class, 'liveJson'])
                ->name('users.fleet-map.live-json');
        });

        Route::middleware('map.access')->group(function () {
            Route::get('device/{token}/map', [MapController::class, 'map'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.map');
            Route::get('device/{token}/history-json', [MapController::class, 'historyJson'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.history.json');
            Route::get('device/{token}/live-json', [MapController::class, 'liveJson'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.live.json');
            Route::get('device/{token}/summary-json', [MapController::class, 'summaryJson'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.summary.json');
            Route::get('device/{token}/alerts-json', [MapController::class, 'alertsJson'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.alerts.json');
            Route::get('device/{token}/reverse-geocode', [MapController::class, 'reverseGeocode'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.reverse.geocode');
            Route::get('device/{token}/geofences-json', [GeofenceController::class, 'indexJson'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.geofences.json');
            Route::post('device/{token}/geofences-save', [GeofenceController::class, 'store'])
                ->where('token', '[A-Za-z0-9_-]+')
                ->name('device.geofences.save');
        });

        Route::delete('geofence/{id}', [GeofenceController::class, 'destroy'])
            ->whereNumber('id')
            ->name('geofence.destroy');
        Route::post('geofence/{id}/update', [GeofenceController::class, 'update'])
            ->whereNumber('id')
            ->name('geofence.update');
    });

/*
|--------------------------------------------------------------------------
| Breeze Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('demo')->group(function () {

    Route::get('/login', function () {
        session(['demo' => true]);
        return redirect('/demo/dashboard');
    });

    Route::get('/dashboard', function () {
        abort_unless(session('demo'), 403);
        return view('demo.dashboard');
    });

    Route::get('/tracking', function () {
        abort_unless(session('demo'), 403);
        return view('demo.tracking');
    });

    Route::get('/history', function () {
        abort_unless(session('demo'), 403);
        return response()->json(
            json_decode(
                file_get_contents(storage_path('app/demo/history.json')),
                true
            )
        );
    });

    Route::get('/logout', function () {
        session()->forget('demo');
        return redirect('/');
    });

});

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact/submit', [ContactController::class, 'submit'])
    ->middleware('throttle:6,1')
    ->name('contact.submit');
Route::get('/contact/rate-limit', [ContactController::class, 'checkRateLimit'])
    ->name('contact.rate-limit');
Route::get('/android-app', [AndroidAppController::class, 'show'])->name('android-app');

// Company pages
Route::get('/company', [PageController::class, 'company'])->name('company');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/careers', [PageController::class, 'careers'])->name('careers');
Route::get('/press', [PageController::class, 'press'])->name('press');
Route::get('/blog', [PageController::class, 'blog'])->name('blog');
Route::get('/pricing', \App\Http\Controllers\PublicPricingController::class)->name('pricing');

// Support pages
Route::get('/help', [PageController::class, 'help'])->name('help');
Route::get('/docs', [PageController::class, 'docs'])->name('docs');
Route::get('/api', [PageController::class, 'api'])->name('api');
Route::get('/status', [PageController::class, 'status'])->name('status');

Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/security', [PageController::class, 'security'])->name('security');
Route::get('/cookies', [PageController::class, 'cookies'])->name('cookies');

//Laravel Breeze
require __DIR__ . '/auth.php';
