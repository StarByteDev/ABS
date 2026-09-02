<?php

namespace App\Http\Controllers;

use App\Services\ApplicationBackupService;
use App\Support\AbsSchemaRepair;
use App\Support\RecoveryKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecoveryController extends Controller
{
    public function index()
    {
        return $this->renderRecovery();
    }

    public function repair(Request $request)
    {
        try {
            $this->assertRecoveryKey($request);
            DB::connection()->getPdo();

            $before = AbsSchemaRepair::diagnose();
            if (($before['ready'] ?? false) === true) {
                return $this->renderSetupRequired([
                    'ok' => true,
                    'title' => 'Database structure is already complete.',
                    'message' => 'ABS did not find any missing required tables or columns.',
                ], [], 200, $before);
            }

            // Normal abs:repair is intentionally non-destructive and does not seed.
            // It creates missing ABS/Pulse tables, adds missing required columns and
            // baselines already-satisfied legacy create-table migrations.
            $exitCode = Artisan::call('abs:repair');
            $output = trim(Artisan::output());
            $this->forgetDiagnosisCache();

            $after = AbsSchemaRepair::diagnose();
            $fixedTables = max(0, count((array) ($before['missing_tables'] ?? [])) - count((array) ($after['missing_tables'] ?? [])));
            $beforeColumns = collect((array) ($before['missing_columns'] ?? []))->sum(fn ($columns): int => count((array) $columns));
            $afterColumns = collect((array) ($after['missing_columns'] ?? []))->sum(fn ($columns): int => count((array) $columns));
            $fixedColumns = max(0, $beforeColumns - $afterColumns);

            $ok = $exitCode === 0 && ($after['ready'] ?? false);
            $message = $ok
                ? "Database repair completed. {$fixedTables} missing table(s) and {$fixedColumns} missing column(s) were reconciled. Existing ABS data was preserved."
                : 'The repair ran, but ABS still detects unresolved database structure items.';

            if ($output !== '') {
                $message .= "\n\nRepair details:\n".$output;
            }

            return $this->renderSetupRequired([
                'ok' => $ok,
                'title' => $ok ? 'Missing database structure fixed.' : 'Database repair needs attention.',
                'message' => $message,
            ], [], $ok ? 200 : 500, $after);
        } catch (ValidationException $e) {
            return $this->renderSetupRequired(null, $this->flattenValidationErrors($e), 422);
        } catch (Throwable $e) {
            report($e);

            return $this->renderSetupRequired([
                'ok' => false,
                'title' => 'Database repair failed.',
                'message' => $e->getMessage(),
            ], [], 500);
        }
    }

    public function initialize(Request $request)
    {
        try {
            $this->assertRecoveryKey($request);
            DB::connection()->getPdo();

            $exitCode = Artisan::call('abs:repair', ['--seed' => true]);
            $output = trim(Artisan::output());
            $this->forgetDiagnosisCache();

            $diagnosis = AbsSchemaRepair::diagnose();

            return $this->renderRecovery([
                'ok' => $exitCode === 0 && ($diagnosis['ready'] ?? false),
                'title' => $exitCode === 0 ? 'Database initialization completed.' : 'Database initialization returned an error.',
                'message' => $output !== '' ? $output : 'ABS database initialization finished.',
            ], [], 200, $diagnosis);
        } catch (ValidationException $e) {
            return $this->renderRecovery(null, $this->flattenValidationErrors($e), 422);
        } catch (Throwable $e) {
            report($e);
            return $this->renderRecovery([
                'ok' => false,
                'title' => 'Database initialization failed.',
                'message' => $e->getMessage(),
            ], [], 500);
        }
    }

    public function restore(Request $request, ApplicationBackupService $backups)
    {
        try {
            $this->assertRecoveryKey($request);

            $validated = $request->validate([
                'backup_file' => ['required', 'file', 'max:512000'],
                'confirmation' => ['required', 'string'],
            ]);

            if (strtoupper(trim((string) ($validated['confirmation'] ?? ''))) !== 'RESTORE') {
                throw ValidationException::withMessages([
                    'confirmation' => 'Type RESTORE exactly to confirm the database replacement.',
                ]);
            }

            $file = $request->file('backup_file');
            if (! $file || strtolower((string) $file->getClientOriginalExtension()) !== 'zip') {
                throw ValidationException::withMessages([
                    'backup_file' => 'Please upload the ABS .zip backup created by Admin → Backup & Restore.',
                ]);
            }

            DB::connection()->getPdo();

            // If the destination already has tables, create a best-effort safety backup first.
            $safetyBackup = null;
            try {
                $tableCount = (int) collect(DB::select(
                    'SELECT COUNT(*) AS aggregate FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
                    [DB::getDatabaseName(), 'BASE TABLE']
                ))->first()->aggregate;

                if ($tableCount > 0) {
                    $safetyBackup = $backups->create(false);
                }
            } catch (Throwable) {
                // A safety snapshot is best-effort only on a broken/empty schema.
            }

            $result = $backups->restore(
                $file->getRealPath(),
                $request->boolean('restore_uploads', true),
                false
            );

            // Reconcile the restored schema without reseeding or replacing restored business configuration.
            $repairCode = Artisan::call('abs:repair');
            $repairOutput = trim(Artisan::output());
            Artisan::call('optimize:clear');
            $this->forgetDiagnosisCache();

            $diagnosis = AbsSchemaRepair::diagnose();
            $message = 'Backup database restored successfully. '.((int) ($result['uploads_restored'] ?? 0)).' uploaded files restored.';
            if ($safetyBackup) {
                $message .= ' Pre-restore safety backup: '.$safetyBackup['name'].'.';
            }
            if ($repairOutput !== '') {
                $message .= "\n\nSchema repair:\n".$repairOutput;
            }

            return $this->renderRecovery([
                'ok' => $repairCode === 0 && ($diagnosis['ready'] ?? false),
                'title' => 'ABS backup restore completed.',
                'message' => $message,
            ], [], 200, $diagnosis);
        } catch (ValidationException $e) {
            return $this->renderRecovery(null, $this->flattenValidationErrors($e), 422);
        } catch (Throwable $e) {
            report($e);
            return $this->renderRecovery([
                'ok' => false,
                'title' => 'ABS backup restore failed.',
                'message' => $e->getMessage(),
            ], [], 500);
        }
    }

    private function assertRecoveryKey(Request $request): void
    {
        $expected = RecoveryKey::value();
        $provided = trim((string) $request->input('recovery_key'));

        if ($expected === '') {
            throw ValidationException::withMessages([
                'recovery_key' => 'ABS_RECOVERY_KEY is not configured in .env.',
            ]);
        }

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            throw ValidationException::withMessages([
                'recovery_key' => 'The recovery key is incorrect.',
            ]);
        }
    }

    private function renderSetupRequired(?array $result = null, array $validationErrors = [], int $status = 200, ?array $diagnosis = null)
    {
        $diagnosis ??= AbsSchemaRepair::diagnose();

        return response()->view('errors.setup-required', [
            'diagnosis' => $diagnosis,
            'recoveryEnabled' => RecoveryKey::enabled(),
            'result' => $result,
            'validationErrors' => $validationErrors,
        ], $status);
    }

    private function renderRecovery(?array $result = null, array $validationErrors = [], int $status = 200, ?array $diagnosis = null)
    {
        $diagnosis ??= AbsSchemaRepair::diagnose();

        return response()->view('errors.recovery', [
            'diagnosis' => $diagnosis,
            'recoveryEnabled' => RecoveryKey::enabled(),
            'uploadMax' => ini_get('upload_max_filesize'),
            'postMax' => ini_get('post_max_size'),
            'result' => $result,
            // API routes intentionally do not use Laravel's session-backed $errors bag.
            'validationErrors' => $validationErrors,
        ], $status);
    }

    private function flattenValidationErrors(ValidationException $e): array
    {
        $messages = [];
        foreach ($e->errors() as $fieldErrors) {
            foreach ((array) $fieldErrors as $message) {
                $messages[] = (string) $message;
            }
        }

        return $messages ?: ['Please check the recovery form and try again.'];
    }

    private function forgetDiagnosisCache(): void
    {
        Cache::forget(AbsSchemaRepair::diagnosisCacheKey());

        // Clear known legacy keys as well so upgrades from older releases do
        // not retain a stale installation result in shared cache stores.
        Cache::forget('abs.installation.diagnosis.v14.0.mysql');
        Cache::forget('abs.installation.diagnosis.v14.7.1.mysql');
    }
}
