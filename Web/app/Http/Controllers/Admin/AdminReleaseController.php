<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationBackupService;
use App\Services\ApplicationReleaseService;
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
            'restorePoints' => $releases->listRestorePoints(),
            'databaseBackups' => $backups->listBackups(),
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
            return back()->with('success', 'Release '.$result['version'].' validated and staged. SHA-256 '.$result['sha256'].'. Review it below before installing.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Release package was not accepted: '.$e->getMessage());
        }
    }

    public function install(Request $request, string $package, ApplicationReleaseService $releases)
    {
        $data = $request->validate(['confirmation' => ['required','string']]);
        if (strtoupper(trim($data['confirmation'])) !== 'INSTALL') {
            throw ValidationException::withMessages(['confirmation' => 'Type INSTALL exactly to confirm this production upgrade.']);
        }
        try {
            $result = $releases->installStagedPackage($package);
            return redirect()->route('admin.enterprise.updates')->with('success', 'ABS upgraded to '.$result['installed_version'].'. A complete pre-upgrade code + database + uploads restore point was created automatically as '.$result['restore_point']['name'].'.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', $e->getMessage());
        }
    }

    public function createRestorePoint(ApplicationReleaseService $releases)
    {
        try {
            $result = $releases->createRestorePoint('manual admin restore point');
            return back()->with('success', 'Full application restore point created: '.$result['name'].' — SHA-256 '.$result['sha256']);
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Restore point could not be created: '.$e->getMessage());
        }
    }

    public function restore(Request $request, string $restorePoint, ApplicationReleaseService $releases)
    {
        $data = $request->validate(['confirmation' => ['required','string']]);
        if (strtoupper(trim($data['confirmation'])) !== 'ROLLBACK') {
            throw ValidationException::withMessages(['confirmation' => 'Type ROLLBACK exactly to restore the selected release.']);
        }
        try {
            $result = $releases->restoreRestorePoint($restorePoint, true);
            return redirect()->route('admin.enterprise.updates')->with('success', 'Rollback completed to '.$result['restored_version'].'. A safety restore point of the state before rollback was also created.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Rollback failed: '.$e->getMessage());
        }
    }

    public function downloadPackage(string $package, ApplicationReleaseService $releases)
    {
        try { $path = $releases->resolvePackage($package); return response()->download($path, basename($path)); }
        catch (Throwable $e) { abort(404, $e->getMessage()); }
    }

    public function deletePackage(string $package, ApplicationReleaseService $releases)
    {
        try { $releases->deletePackage($package); return back()->with('success','Staged release package deleted.'); }
        catch (Throwable $e) { return back()->with('warning',$e->getMessage()); }
    }

    public function downloadRestorePoint(string $restorePoint, ApplicationReleaseService $releases)
    {
        try { $path = $releases->resolveRestorePoint($restorePoint); return response()->download($path, basename($path)); }
        catch (Throwable $e) { abort(404, $e->getMessage()); }
    }

    public function deleteRestorePoint(string $restorePoint, ApplicationReleaseService $releases)
    {
        try { $releases->deleteRestorePoint($restorePoint); return back()->with('success','Restore point deleted.'); }
        catch (Throwable $e) { return back()->with('warning',$e->getMessage()); }
    }
}
