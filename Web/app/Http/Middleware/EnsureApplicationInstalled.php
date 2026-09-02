<?php

namespace App\Http\Middleware;

use App\Support\AbsSchemaRepair;
use App\Support\RecoveryKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Recovery must stay reachable even when the database schema is completely empty.
        // On some shared-hosting/rewrite setups Laravel can see API paths with or without
        // the leading `api/` prefix, so match the normalized request path instead of
        // relying only on Request::is('api/recovery').
        $path = trim((string) $request->path(), '/');
        $isRecoveryPath = preg_match('#(?:^|/)recovery(?:/|$)#i', $path) === 1;

        // Keep the authenticated Database Fix center reachable when a build
        // introduces new required columns. Authentication still applies at the
        // route level; recovery remains the fallback for a severely broken DB.
        $isMaintenancePath = preg_match('#(?:^|/)admin/enterprise/maintenance(?:/|$)#i', $path) === 1;

        if ($request->is('up') || $request->is('setup-required') || $isRecoveryPath || $isMaintenancePath) {
            return $next($request);
        }

        // The cache key is derived from the required schema itself. A new build
        // that adds a table/column therefore cannot inherit a stale READY result
        // from the previous release and then fail later with a raw SQL/500 page.
        try {
            $diagnosis = Cache::remember(
                AbsSchemaRepair::diagnosisCacheKey(),
                now()->addMinutes(5),
                fn (): array => AbsSchemaRepair::diagnose(),
            );
        } catch (\Throwable) {
            $diagnosis = AbsSchemaRepair::diagnose();
        }

        $environmentIssues = [];
        if (blank(config('app.key'))) {
            $environmentIssues[] = 'APP_KEY is missing. Run: php artisan key:generate';
        }
        foreach ([storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $environmentIssues[] = 'Directory is missing or not writable: '.$path;
            }
        }
        if (! app()->environment('testing') && ! $request->is('api/*')) {
            foreach (['assets/css/abs-app.css', 'assets/css/pulse-app.css', 'assets/js/abs-app.js', 'assets/js/pulse-app.js'] as $asset) {
                if (! file_exists(public_path($asset))) {
                    $environmentIssues[] = 'Required frontend asset is missing: public/'.$asset;
                }
            }
        }
        $diagnosis['environment_issues'] = $environmentIssues;
        $diagnosis['ready'] = $diagnosis['ready'] && $environmentIssues === [];

        if ($diagnosis['ready']) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'ABS database setup or repair is required.',
                'database' => $diagnosis['database'],
                'missing_tables' => $diagnosis['missing_tables'],
                'missing_columns' => $diagnosis['missing_columns'],
                'database_error' => $diagnosis['error'],
                'environment_issues' => $diagnosis['environment_issues'],
                'repair_available' => RecoveryKey::enabled(),
                'repair_url' => '/api/recovery/repair',
            ], 503);
        }

        return response()->view('errors.setup-required', [
            'diagnosis' => $diagnosis,
            'recoveryEnabled' => RecoveryKey::enabled(),
            'result' => null,
            'validationErrors' => [],
        ], 503);
    }
}
