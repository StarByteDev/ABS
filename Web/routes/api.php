<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\PrivatePortalController;
use App\Http\Controllers\Api\V1\WatchlistController;
use App\Http\Controllers\Api\V1\PulseController;
use App\Http\Controllers\Api\V1\AdminPulseController;
use App\Http\Controllers\RecoveryController;
use Illuminate\Support\Facades\Route;


// Emergency web recovery routes. These intentionally live outside /api/v1 so an
// empty database can be initialized or an ABS backup restored before login exists.
Route::get('/recovery', [RecoveryController::class, 'index']);
Route::post('/recovery/repair', [RecoveryController::class, 'repair'])->middleware('throttle:5,1');
Route::post('/recovery/initialize', [RecoveryController::class, 'initialize'])->middleware('throttle:5,1');
Route::post('/recovery/restore', [RecoveryController::class, 'restore'])->middleware('throttle:5,1');

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/activation/resend', [AuthController::class, 'resendActivation'])->middleware('throttle:6,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::get('/bootstrap', [AppController::class, 'bootstrap']);

    Route::get('/market/overview', [MarketController::class, 'overview']);
    Route::get('/market/movers', [MarketController::class, 'movers']);
    Route::get('/market/chart/{symbol}', [MarketController::class, 'chart']);

    Route::get('/products', [ContentController::class, 'products']);
    Route::get('/products/{product:slug}', [ContentController::class, 'product']);
    Route::get('/news', [ContentController::class, 'news']);
    Route::get('/news/live', [ContentController::class, 'liveNews']);
    Route::get('/news/{article:slug}', [ContentController::class, 'newsShow']);
    Route::get('/research', [ContentController::class, 'research']);
    Route::get('/research/{report:slug}', [ContentController::class, 'researchShow']);
    Route::get('/learning', [ContentController::class, 'learning']);
    Route::get('/learning/{lesson:slug}', [ContentController::class, 'learningShow']);
    Route::get('/economic-calendar', [ContentController::class, 'calendar']);
    Route::get('/content/settings', [ContentController::class, 'settings']);
    Route::get('/legal/{type}', [ContentController::class, 'legal'])->whereIn('type',['privacy','terms','risk','disclaimer']);
    Route::post('/newsletter', [NewsletterController::class, 'store']);
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/search', [AccountController::class, 'search']);

    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/{token}', [AuthController::class, 'revokeSession']);
        Route::get('/dashboard', [AccountController::class, 'dashboard']);
        Route::patch('/profile', [AccountController::class, 'updateProfile']);
        Route::get('/notifications', [AccountController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [AccountController::class, 'readNotification']);
        Route::get('/notification-preferences', [AccountController::class, 'notificationPreferences']);
        Route::put('/notification-preferences', [AccountController::class, 'updateNotificationPreferences']);
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::put('/devices/{device}', [DeviceController::class, 'update']);
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);
        Route::get('/watchlist', [WatchlistController::class, 'index']);
        Route::post('/watchlist', [WatchlistController::class, 'store']);
        Route::delete('/watchlist/{symbol}', [WatchlistController::class, 'destroy']);

        Route::get('/pulse/access', [PulseController::class, 'access']);
        Route::get('/pulse/plans', [PulseController::class, 'plans']);
        Route::get('/pulse/membership', [PulseController::class, 'membership']);
        Route::post('/pulse/membership/quote', [PulseController::class, 'membershipQuote']);
        Route::post('/pulse/membership/requests', [PulseController::class, 'submitMembershipRequest']);
        Route::patch('/pulse/membership/requests/{membershipRequest}', [PulseController::class, 'cancelMembershipRequest']);

        Route::middleware(['pulse.access', 'pulse.capability:mobile_api'])->prefix('pulse')->group(function () {
            Route::get('/dashboard', [PulseController::class, 'dashboard']);
            Route::get('/usage', [PulseController::class, 'usage']);
            Route::get('/pairs', [PulseController::class, 'pairs']);
            Route::get('/strategies', [PulseController::class, 'strategies'])->middleware('pulse.capability:signals');
            Route::get('/strategies/overview', [PulseController::class, 'strategiesOverview'])->middleware('pulse.capability:signals');
            Route::get('/execution/readiness', [PulseController::class, 'executionReadiness'])->middleware('pulse.capability:signals');
            Route::get('/execution/ticket', [PulseController::class, 'executionTicket'])->middleware('pulse.capability:signals');
            Route::get('/positions', [PulseController::class, 'positions'])->middleware('pulse.capability:orders');
            Route::get('/risk-controls', [PulseController::class, 'riskControls'])->middleware('pulse.capability:settings');
            Route::put('/risk-controls', [PulseController::class, 'updateRiskControls'])->middleware('pulse.capability:settings');
            Route::get('/settings', [PulseController::class, 'settings'])->middleware('pulse.capability:settings');
            Route::put('/settings', [PulseController::class, 'updateSettings'])->middleware('pulse.capability:settings');
            Route::get('/scanner/runs', [PulseController::class, 'scannerRuns'])->middleware('pulse.capability:scanner');
            Route::get('/scanner/overview', [PulseController::class, 'scannerOverview'])->middleware('pulse.capability:scanner');
            Route::post('/scanner/run', [PulseController::class, 'runScanner'])->middleware('pulse.capability:scanner');
            Route::get('/signals', [PulseController::class, 'signals'])->middleware('pulse.capability:signals');
            Route::get('/signals/overview', [PulseController::class, 'signalsOverview'])->middleware('pulse.capability:signals');
            Route::patch('/signals/{signal}/dismiss', [PulseController::class, 'dismissSignal'])->middleware('pulse.capability:signals');
            Route::post('/signals/{signal}/execute', [PulseController::class, 'executeSignal'])->middleware(['pulse.capability:signals', 'pulse.capability:manual_trading']);
            Route::get('/signals/{signal}', [PulseController::class, 'signal'])->middleware('pulse.capability:signals');
            Route::get('/trades', [PulseController::class, 'trades'])->middleware('pulse.capability:trades');
            Route::get('/trades/{trade}', [PulseController::class, 'trade'])->middleware('pulse.capability:trades');
            Route::post('/trades/{trade}/close', [PulseController::class, 'closeTrade'])->middleware(['pulse.capability:trades', 'pulse.capability:manual_trading']);
            Route::post('/trades/sync', [PulseController::class, 'syncTrades'])->middleware('pulse.capability:trades');
            Route::post('/emergency-stop', [PulseController::class, 'emergencyStop'])->middleware('pulse.capability:trades');
            Route::get('/orders', [PulseController::class, 'orders'])->middleware('pulse.capability:orders');
            Route::get('/reports', [PulseController::class, 'reports'])->middleware('pulse.capability:reports');
            Route::get('/reports/signals', [PulseController::class, 'signalPerformance'])->middleware('pulse.capability:reports');
            Route::get('/reports/strategies', [PulseController::class, 'strategyPerformance'])->middleware('pulse.capability:reports');
            Route::get('/reports/learning', [PulseController::class, 'learningInsights'])->middleware('pulse.capability:reports');
            Route::get('/market-data/health', [PulseController::class, 'marketDataHealth'])->middleware('pulse.capability:scanner');
            Route::get('/market-data/prices', [PulseController::class, 'marketPrices'])->middleware('pulse.capability:scanner');
            Route::get('/signals/{signal}/validation', [PulseController::class, 'signalValidation'])->middleware('pulse.capability:signals');
            Route::get('/binance/connections', [PulseController::class, 'connections'])->middleware('pulse.capability:binance');
            Route::post('/binance/connections', [PulseController::class, 'saveConnection'])->middleware('pulse.capability:binance');
            Route::post('/binance/connections/{connection}/test', [PulseController::class, 'testConnection'])->middleware('pulse.capability:binance');
            Route::post('/binance/connections/{connection}/activate', [PulseController::class, 'activateConnection'])->middleware('pulse.capability:binance');
            Route::delete('/binance/connections/{connection}', [PulseController::class, 'deleteConnection'])->middleware('pulse.capability:binance');
            Route::get('/alerts', [PulseController::class, 'alerts'])->middleware('pulse.capability:alerts');
            Route::patch('/alerts/{alert}/read', [PulseController::class, 'readAlert'])->middleware('pulse.capability:alerts');
            Route::patch('/alerts/read-all', [PulseController::class, 'readAllAlerts'])->middleware('pulse.capability:alerts');
        });

        Route::middleware('role:admin')->prefix('admin/pulse')->group(function () {
            Route::get('/dashboard', [AdminPulseController::class, 'dashboard']);
            Route::get('/access', [AdminPulseController::class, 'access']);
            Route::put('/access/{user}', [AdminPulseController::class, 'updateAccess']);
            Route::get('/plans', [AdminPulseController::class, 'plans']);
            Route::post('/plans', [AdminPulseController::class, 'storePlan']);
            Route::put('/plans/{plan}', [AdminPulseController::class, 'updatePlan']);
            Route::delete('/plans/{plan}', [AdminPulseController::class, 'deletePlan']);
            Route::get('/strategies', [AdminPulseController::class, 'strategies']);
            Route::post('/strategies', [AdminPulseController::class, 'storeStrategy']);
            Route::put('/strategies/{strategy}', [AdminPulseController::class, 'updateStrategy']);
            Route::delete('/strategies/{strategy}', [AdminPulseController::class, 'deleteStrategy']);
            Route::get('/pairs', [AdminPulseController::class, 'pairs']);
            Route::post('/pairs', [AdminPulseController::class, 'storePair']);
            Route::put('/pairs/{pair}', [AdminPulseController::class, 'updatePair']);
            Route::post('/pairs/sync', [AdminPulseController::class, 'syncPairs']);
            Route::get('/signals', [AdminPulseController::class, 'signals']);
            Route::put('/signals/{signal}', [AdminPulseController::class, 'updateSignal']);
            Route::get('/trades', [AdminPulseController::class, 'trades']);
            Route::get('/settings', [AdminPulseController::class, 'settings']);
            Route::put('/settings', [AdminPulseController::class, 'updateSettings']);
            Route::post('/alerts/broadcast', [AdminPulseController::class, 'broadcastAlert']);
            Route::get('/logs', [AdminPulseController::class, 'logs']);
        });

        Route::middleware('private.member')->prefix('private')->group(function () {
            Route::get('/account', [PrivatePortalController::class, 'account']);
            Route::get('/statements/{statement}', [PrivatePortalController::class, 'statement']);
        });
    });
});
