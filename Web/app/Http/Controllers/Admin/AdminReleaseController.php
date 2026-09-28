<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationBackupService;
use App\Services\ApplicationReleaseService;
use App\Support\RecoveryKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminReleaseController extends Controller
{
    public function index(ApplicationReleaseService $releases, ApplicationBackupService $backups)
    {
        return view('admin.enterprise.updates', [
            'currentVersion' => File::isFile(base_path('BUILD_VERSION.txt')) ? trim((string) File::get(base_path('BUILD_VERSION.txt'))) : 'Unknown',
            'packages' => $releases->listPackages(),
            'restorePoint' => $releases->latestRestorePoint(),
            'databaseBackups' => $backups->listBackups(),
            'releaseState' => $releases->releaseState(),
            'auditEntries' => $releases->recentAudit(8),
            'recoveryEnabled' => RecoveryKey::enabled(),
            'uploadMax' => ini_get('upload_max_filesize'),
            'postMax' => ini_get('post_max_size'),
        ]);
    }

    public function upload(Request $request, ApplicationReleaseService $releases)
    {
        $request->validate(['release_package' => ['required','file','max:512000']]);
        $file = $request->file('release_package');
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'zip') {
            throw ValidationException::withMessages(['release_package' => 'Upload a complete ABS release ZIP package.']);
        }
        try {
            $result = $releases->stageUploadedPackage($file->getRealPath(), $file->getClientOriginalName());
            $migrationMessage = count($result['pending_migrations'] ?? [])
                ? ' '.count($result['pending_migrations']).' safe pending migration(s) detected.'
                : ' No database migration is pending.';
            return back()->with('success', 'Patch '.$result['version'].' validated and staged.'.$migrationMessage.' SHA-256 '.$result['sha256'].'.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Patch was not accepted: '.$e->getMessage());
        }
    }

    public function install(Request $request, string $package, ApplicationReleaseService $releases)
    {
        $data = $request->validate(['confirmation' => ['required','string']]);
        if (strtoupper(trim($data['confirmation'])) !== 'INSTALL') {
            throw ValidationException::withMessages(['confirmation' => 'Type INSTALL exactly to confirm this production patch.']);
        }
        try {
            $result = $releases->installStagedPackage($package);
            return redirect()->route('admin.enterprise.updates')->with(
                'success',
                'ABS updated to '.$result['installed_version'].'. The immediately previous build is saved for rollback. Live database records were preserved.'
            );
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', $e->getMessage());
        }
    }

    public function createRestorePoint(ApplicationReleaseService $releases)
    {
        try {
            $result = $releases->createRestorePoint('manual admin previous-build backup');
            return back()->with('success', 'Current application build saved as the rollback point: '.$result['version'].'. Existing live database data was not copied or changed.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Current build could not be backed up: '.$e->getMessage());
        }
    }

    public function restore(Request $request, string $restorePoint, ApplicationReleaseService $releases)
    {
        $data = $request->validate([
            'recovery_key' => ['required','string','max:255'],
            'confirmation' => ['required','accepted'],
        ]);
        $expected = RecoveryKey::value();
        if ($expected === '') {
            throw ValidationException::withMessages(['recovery_key' => 'ABS_RECOVERY_KEY is not configured in the production .env file.']);
        }
        if (! hash_equals($expected, trim((string) $data['recovery_key']))) {
            throw ValidationException::withMessages(['recovery_key' => 'The recovery key is incorrect.']);
        }

        try {
            $result = $releases->restoreRestorePoint($restorePoint, false);
            return redirect()->route('admin.enterprise.updates')->with(
                'success',
                'Previous ABS build '.$result['restored_version'].' restored. The live database was left exactly in place; no users, payments, trades or other records were rolled back.'
            );
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Previous build restore failed: '.$e->getMessage());
        }
    }

    public function downloadPackage(string $package, ApplicationReleaseService $releases)
    {
        try { $path = $releases->resolvePackage($package); return response()->download($path, basename($path)); }
        catch (Throwable $e) { abort(404, $e->getMessage()); }
    }

    public function deletePackage(string $package, ApplicationReleaseService $releases)
    {
        try { $releases->deletePackage($package); return back()->with('success','Staged patch deleted.'); }
        catch (Throwable $e) { return back()->with('warning',$e->getMessage()); }
    }

    public function downloadRestorePoint(string $restorePoint, ApplicationReleaseService $releases)
    {
        try { $path = $releases->resolveRestorePoint($restorePoint); return response()->download($path, basename($path)); }
        catch (Throwable $e) { abort(404, $e->getMessage()); }
    }

    public function deleteRestorePoint(string $restorePoint, ApplicationReleaseService $releases)
    {
        try { $releases->deleteRestorePoint($restorePoint); return back()->with('success','Previous-build rollback point deleted.'); }
        catch (Throwable $e) { return back()->with('warning',$e->getMessage()); }
    }
}
