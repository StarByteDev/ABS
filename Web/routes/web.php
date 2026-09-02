<?php

use App\Http\Controllers\Admin\AdminBackupController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEnterpriseController;
use App\Http\Controllers\Admin\AdminMaintenanceController;
use App\Http\Controllers\Admin\AdminMarketDataController;
use App\Http\Controllers\Admin\AdminPortfolioController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminPulseController;
use App\Http\Controllers\Admin\AdminReleaseController;
use App\Http\Controllers\Pulse\AlertController as PulseAlertController;
use App\Http\Controllers\Pulse\BinanceController as PulseBinanceController;
use App\Http\Controllers\Pulse\DashboardController as PulseDashboardController;
use App\Http\Controllers\Pulse\EntryController as PulseEntryController;
use App\Http\Controllers\Pulse\ExecutionController as PulseExecutionController;
use App\Http\Controllers\Pulse\PlanController as PulsePlanController;
use App\Http\Controllers\Pulse\MembershipController as PulseMembershipController;
use App\Http\Controllers\Pulse\OrdersController as PulseOrdersController;
use App\Http\Controllers\Pulse\PositionController as PulsePositionController;
use App\Http\Controllers\Pulse\ReportsController as PulseReportsController;
use App\Http\Controllers\Pulse\RiskController as PulseRiskController;
use App\Http\Controllers\Pulse\ScannerController as PulseScannerController;
use App\Http\Controllers\Pulse\SettingsController as PulseSettingsController;
use App\Http\Controllers\Pulse\SignalController as PulseSignalController;
use App\Http\Controllers\Pulse\StrategyController as PulseStrategyController;
use App\Http\Controllers\Pulse\TradeController as PulseTradeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrivatePortalController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::redirect('/products', '/pulse', 301)->name('products');
Route::get('/markets', [PageController::class, 'markets'])->name('markets');
Route::redirect('/tools', '/pulse', 301)->name('tools');
Route::redirect('/economic-calendar', '/markets', 301)->name('calendar');
Route::get('/about', [PageController::class, 'about'])->name('about');

Route::get('/pulse', PulseEntryController::class)->name('pulse.entry');
Route::get('/search', SearchController::class)->name('search');
Route::get('/legal/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/legal/risk-disclosure', [LegalController::class, 'risk'])->name('legal.risk');
Route::get('/legal/market-disclaimer', [LegalController::class, 'disclaimer'])->name('legal.disclaimer');

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{article:slug}', [NewsController::class, 'show'])->name('news.show');
Route::redirect('/research', '/news', 301)->name('research.index');
Route::get('/research/{report:slug}', [PageController::class, 'researchShowRedirect'])->name('research.show');
Route::redirect('/learn', '/pulse', 301)->name('learn.index');
Route::get('/learn/{lesson:slug}', [PageController::class, 'learningShowRedirect'])->name('learn.show');
Route::redirect('/community', '/pulse', 301)->name('community.index');
Route::post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::post('/forgot-username-or-email', [AuthController::class, 'sendAccountIdentifierReminder'])->middleware('throttle:6,1')->name('account.identifier.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});
Route::get('/verify-email/{user}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:10,1'])->name('verification.verify');

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::delete('/watchlist/{symbol}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    Route::get('/pulse/access', [PulseEntryController::class, 'access'])->name('pulse.access');
    Route::get('/pulse/plans', [PulsePlanController::class, 'index'])->name('pulse.plans');
    Route::get('/pulse/membership', [PulseMembershipController::class, 'index'])->name('pulse.membership.index');
    Route::get('/pulse/membership/checkout/{plan}', [PulseMembershipController::class, 'checkout'])->name('pulse.membership.checkout');
    Route::post('/pulse/membership/request', [PulseMembershipController::class, 'store'])->name('pulse.membership.store');
    Route::patch('/pulse/membership/requests/{membershipRequest}/cancel', [PulseMembershipController::class, 'cancel'])->name('pulse.membership.cancel');

    Route::middleware('pulse.access')->prefix('pulse')->name('pulse.')->group(function () {
        Route::get('/dashboard', PulseDashboardController::class)->name('dashboard');
        Route::get('/scanner', [PulseScannerController::class, 'index'])->middleware('pulse.capability:scanner')->name('scanner');
        Route::post('/scanner/run', [PulseScannerController::class, 'run'])->middleware('pulse.capability:scanner')->name('scanner.run');
        Route::get('/scanner/refresh', [PulseScannerController::class, 'refresh'])->middleware('pulse.capability:scanner')->name('scanner.refresh');
        Route::get('/signals', [PulseSignalController::class, 'index'])->middleware('pulse.capability:signals')->name('signals.index');
        Route::get('/signals/{signal}', [PulseSignalController::class, 'show'])->middleware('pulse.capability:signals')->name('signals.show');
        Route::patch('/signals/{signal}/dismiss', [PulseSignalController::class, 'dismiss'])->middleware('pulse.capability:signals')->name('signals.dismiss');
        Route::post('/signals/{signal}/execute', [PulseSignalController::class, 'execute'])->middleware(['pulse.capability:signals', 'pulse.capability:manual_trading'])->name('signals.execute');
        Route::get('/strategies', PulseStrategyController::class)->middleware('pulse.capability:signals')->name('strategies');
        Route::get('/execution', PulseExecutionController::class)->middleware('pulse.capability:signals')->name('execution');
        Route::get('/positions', PulsePositionController::class)->middleware('pulse.capability:orders')->name('positions');
        Route::get('/orders', PulseOrdersController::class)->middleware('pulse.capability:orders')->name('orders');
        Route::get('/risk-controls', [PulseRiskController::class, 'index'])->middleware('pulse.capability:settings')->name('risk.index');
        Route::put('/risk-controls', [PulseRiskController::class, 'update'])->middleware('pulse.capability:settings')->name('risk.update');
        Route::get('/reports', PulseReportsController::class)->middleware('pulse.capability:reports')->name('reports');
        Route::get('/trades', [PulseTradeController::class, 'index'])->middleware('pulse.capability:trades')->name('trades.index');
        Route::get('/trades/{trade}', [PulseTradeController::class, 'show'])->middleware('pulse.capability:trades')->name('trades.show');
        Route::post('/trades/{trade}/close', [PulseTradeController::class, 'close'])->middleware(['pulse.capability:trades', 'pulse.capability:manual_trading'])->name('trades.close');
        Route::post('/trades/sync', [PulseTradeController::class, 'sync'])->middleware('pulse.capability:trades')->name('trades.sync');
        Route::post('/emergency-stop', [PulseTradeController::class, 'emergencyStop'])->middleware('pulse.capability:trades')->name('emergency-stop');
        Route::get('/binance', [PulseBinanceController::class, 'index'])->middleware('pulse.capability:binance')->name('binance.index');
        Route::post('/binance', [PulseBinanceController::class, 'store'])->middleware('pulse.capability:binance')->name('binance.store');
        Route::post('/binance/{connection}/test', [PulseBinanceController::class, 'test'])->middleware('pulse.capability:binance')->name('binance.test');
        Route::post('/binance/{connection}/activate', [PulseBinanceController::class, 'activate'])->middleware('pulse.capability:binance')->name('binance.activate');
        Route::delete('/binance/{connection}', [PulseBinanceController::class, 'destroy'])->middleware('pulse.capability:binance')->name('binance.destroy');
        Route::get('/settings', [PulseSettingsController::class, 'edit'])->middleware('pulse.capability:settings')->name('settings.edit');
        Route::put('/settings', [PulseSettingsController::class, 'update'])->middleware('pulse.capability:settings')->name('settings.update');
        Route::get('/alerts', [PulseAlertController::class, 'index'])->middleware('pulse.capability:alerts')->name('alerts.index');
        Route::patch('/alerts/{alert}/read', [PulseAlertController::class, 'read'])->middleware('pulse.capability:alerts')->name('alerts.read');
        Route::patch('/alerts/read-all', [PulseAlertController::class, 'readAll'])->middleware('pulse.capability:alerts')->name('alerts.read-all');
    });
});

Route::middleware(['auth', 'account.active', 'private.member'])->prefix('private')->name('private.')->group(function () {
    Route::get('/', [PrivatePortalController::class, 'index'])->name('index');
    Route::get('/statements/{statement}', [PrivatePortalController::class, 'statement'])->name('statement');
    Route::get('/statements/{statement}/export', [PrivatePortalController::class, 'exportStatement'])->name('statement.export');
});

Route::middleware(['auth', 'account.active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/password', [AdminUserController::class, 'resetPassword'])->name('users.password');
    Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{userId}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
    Route::delete('/users/{userId}/force', [AdminUserController::class, 'forceDelete'])->name('users.force-delete');
    Route::get('/portfolios', [AdminPortfolioController::class, 'index'])->name('portfolios');
    Route::post('/portfolios', [AdminPortfolioController::class, 'store'])->name('portfolios.store');
    Route::post('/portfolios/{account}/transactions', [AdminPortfolioController::class, 'transaction'])->name('portfolios.transaction');
    Route::post('/portfolios/{account}/statements', [AdminPortfolioController::class, 'statement'])->name('portfolios.statement');

    Route::get('/content/{type}', [AdminContentController::class, 'index'])->name('content.index');
    Route::get('/content/{type}/create', [AdminContentController::class, 'create'])->name('content.create');
    Route::post('/content/{type}', [AdminContentController::class, 'store'])->name('content.store');
    Route::get('/content/{type}/{id}/edit', [AdminContentController::class, 'edit'])->name('content.edit');
    Route::put('/content/{type}/{id}', [AdminContentController::class, 'update'])->name('content.update');
    Route::delete('/content/{type}/{id}', [AdminContentController::class, 'destroy'])->name('content.destroy');
    Route::patch('/news/{article}/headline', [AdminContentController::class, 'toggleHeadline'])->name('news.headline');
    Route::patch('/news/{article}/publish', [AdminContentController::class, 'publishNow'])->name('news.publish');
    Route::patch('/news/{article}/unpublish', [AdminContentController::class, 'unpublish'])->name('news.unpublish');

    Route::prefix('cms')->name('enterprise.')->group(function () {
        Route::get('/content/{type}', [AdminEnterpriseController::class, 'contentIndex'])->name('content.index')->whereIn('type', ['research','learning','events','products']);
        Route::get('/content/{type}/create', [AdminEnterpriseController::class, 'contentCreate'])->name('content.create')->whereIn('type', ['research','learning','events','products']);
        Route::post('/content/{type}', [AdminEnterpriseController::class, 'contentStore'])->name('content.store')->whereIn('type', ['research','learning','events','products']);
        Route::get('/content/{type}/{id}/edit', [AdminEnterpriseController::class, 'contentEdit'])->name('content.edit')->whereIn('type', ['research','learning','events','products']);
        Route::put('/content/{type}/{id}', [AdminEnterpriseController::class, 'contentUpdate'])->name('content.update')->whereIn('type', ['research','learning','events','products']);
        Route::delete('/content/{type}/{id}', [AdminEnterpriseController::class, 'contentDestroy'])->name('content.destroy')->whereIn('type', ['research','learning','events','products']);
        Route::get('/settings', [AdminEnterpriseController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminEnterpriseController::class, 'updateSettings'])->name('settings.update');
        Route::get('/newsletters', [AdminEnterpriseController::class, 'newsletters'])->name('newsletters');
        Route::put('/newsletters/{subscriber}', [AdminEnterpriseController::class, 'updateNewsletter'])->name('newsletters.update');
        Route::get('/contacts', [AdminEnterpriseController::class, 'contacts'])->name('contacts');
        Route::get('/contacts/{contact}', [AdminEnterpriseController::class, 'contactShow'])->name('contacts.show');
        Route::put('/contacts/{contact}', [AdminEnterpriseController::class, 'contactUpdate'])->name('contacts.update');
        Route::get('/emails', [AdminEnterpriseController::class, 'emails'])->name('emails');
        Route::put('/emails/settings', [AdminEnterpriseController::class, 'updateEmailSettings'])->name('emails.settings');
        Route::post('/emails/test', [AdminEnterpriseController::class, 'testEmail'])->name('emails.test');
        Route::get('/backups', [AdminBackupController::class, 'index'])->name('backups');
        Route::post('/backups', [AdminBackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{backup}/download', [AdminBackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{backup}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');
        Route::post('/backups/restore', [AdminBackupController::class, 'restore'])->name('backups.restore');
        Route::get('/updates', [AdminReleaseController::class, 'index'])->name('updates');
        Route::post('/updates/upload', [AdminReleaseController::class, 'upload'])->name('updates.upload');
        Route::post('/updates/restore-point', [AdminReleaseController::class, 'createRestorePoint'])->name('updates.restore-point');
        Route::post('/updates/packages/{package}/install', [AdminReleaseController::class, 'install'])->name('updates.install');
        Route::get('/updates/packages/{package}/download', [AdminReleaseController::class, 'downloadPackage'])->name('updates.packages.download');
        Route::delete('/updates/packages/{package}', [AdminReleaseController::class, 'deletePackage'])->name('updates.packages.destroy');
        Route::post('/updates/restore-points/{restorePoint}/restore', [AdminReleaseController::class, 'restore'])->name('updates.restore');
        Route::get('/updates/restore-points/{restorePoint}/download', [AdminReleaseController::class, 'downloadRestorePoint'])->name('updates.restore-points.download');
        Route::delete('/updates/restore-points/{restorePoint}', [AdminReleaseController::class, 'deleteRestorePoint'])->name('updates.restore-points.destroy');
        Route::get('/maintenance', [AdminMaintenanceController::class, 'index'])->name('maintenance');
        Route::post('/maintenance/run', [AdminMaintenanceController::class, 'run'])->name('maintenance.run');
    });

    Route::get('/market-data', [AdminMarketDataController::class, 'index'])->name('market-data');
    Route::post('/market-data/refresh', [AdminMarketDataController::class, 'refresh'])->middleware('throttle:2,1')->name('market-data.refresh');

    Route::prefix('pulse')->name('pulse.')->group(function () {
        Route::get('/', [AdminPulseController::class, 'dashboard'])->name('dashboard');
        Route::get('/plans', [AdminPulseController::class, 'plans'])->name('plans');
        Route::post('/plans', [AdminPulseController::class, 'storePlan'])->name('plans.store');
        Route::put('/plans/{plan}', [AdminPulseController::class, 'updatePlan'])->name('plans.update');
        Route::delete('/plans/{plan}', [AdminPulseController::class, 'deletePlan'])->name('plans.destroy');

        Route::get('/memberships', [AdminPulseController::class, 'memberships'])->name('memberships');
        Route::put('/memberships/settings', [AdminPulseController::class, 'updateMembershipSettings'])->name('memberships.settings');
        Route::post('/membership-requests/{membershipRequest}/approve', [AdminPulseController::class, 'approveMembershipRequest'])->name('membership-requests.approve');
        Route::post('/membership-requests/{membershipRequest}/reject', [AdminPulseController::class, 'rejectMembershipRequest'])->name('membership-requests.reject');
        Route::get('/membership-requests/{membershipRequest}/proof', [AdminPulseController::class, 'membershipProof'])->name('membership-requests.proof');
        Route::post('/promotions', [AdminPulseController::class, 'storePromotion'])->name('promotions.store');
        Route::put('/promotions/{promotion}', [AdminPulseController::class, 'updatePromotion'])->name('promotions.update');
        Route::delete('/promotions/{promotion}', [AdminPulseController::class, 'deletePromotion'])->name('promotions.destroy');

        Route::get('/strategies', [AdminPulseController::class, 'strategies'])->name('strategies');
        Route::post('/strategies', [AdminPulseController::class, 'storeStrategy'])->name('strategies.store');
        Route::put('/strategies/{strategy}', [AdminPulseController::class, 'updateStrategy'])->name('strategies.update');
        Route::delete('/strategies/{strategy}', [AdminPulseController::class, 'deleteStrategy'])->name('strategies.destroy');

        Route::get('/pairs', [AdminPulseController::class, 'pairs'])->name('pairs');
        Route::post('/pairs', [AdminPulseController::class, 'storePair'])->name('pairs.store');
        Route::put('/pairs/{pair}', [AdminPulseController::class, 'updatePair'])->name('pairs.update');
        Route::post('/pairs/sync', [AdminPulseController::class, 'syncPairs'])->name('pairs.sync');

        Route::get('/access', [AdminPulseController::class, 'access'])->name('access');
        Route::put('/access/{user}', [AdminPulseController::class, 'updateAccess'])->name('access.update');
        Route::post('/access/{user}/renew', [AdminPulseController::class, 'renewAccess'])->name('access.renew');
        Route::get('/intelligence', [AdminPulseController::class, 'intelligence'])->name('intelligence');
        Route::get('/signals', [AdminPulseController::class, 'signals'])->name('signals');
        Route::put('/signals/{signal}', [AdminPulseController::class, 'updateSignal'])->name('signals.update');
        Route::get('/trades', [AdminPulseController::class, 'trades'])->name('trades');
        Route::get('/settings', [AdminPulseController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminPulseController::class, 'updateSettings'])->name('settings.update');
        Route::post('/alerts/broadcast', [AdminPulseController::class, 'broadcastAlert'])->name('alerts.broadcast');
        Route::get('/logs', [AdminPulseController::class, 'logs'])->name('logs');
    });
});
