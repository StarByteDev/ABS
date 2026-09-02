<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminBackupController extends Controller
{
    public function index(ApplicationBackupService $backups)
    {
        return view('admin.enterprise.backups', [
            'backups' => $backups->listBackups(),
            'uploadMax' => ini_get('upload_max_filesize'),
            'postMax' => ini_get('post_max_size'),
        ]);
    }

    public function create(Request $request, ApplicationBackupService $backups)
    {
        try {
            $result = $backups->create($request->boolean('include_uploads'));
            return back()->with('success', 'Backup created successfully: '.$result['name'].' — SHA-256 '.$result['sha256']);
        } catch (Throwable $e) {
            report($e);
            return back()->with('warning', 'Backup could not be created: '.$e->getMessage());
        }
    }

    public function download(string $backup, ApplicationBackupService $backups)
    {
        try {
            $path = $backups->resolveBackup($backup);
            return response()->download($path, basename($path), ['Content-Type' => 'application/zip']);
        } catch (Throwable $e) {
            abort(404, $e->getMessage());
        }
    }

    public function destroy(string $backup, ApplicationBackupService $backups)
    {
        try {
            File::delete($backups->resolveBackup($backup));
            return back()->with('success', 'Backup deleted.');
        } catch (Throwable $e) {
            return back()->with('warning', 'Backup could not be deleted: '.$e->getMessage());
        }
    }

    public function restore(Request $request, ApplicationBackupService $backups)
    {
        $data = $request->validate([
            'backup_file' => ['required', 'file', 'max:512000'],
            'confirmation' => ['required', 'string'],
        ]);
        if (strtolower($request->file('backup_file')->getClientOriginalExtension()) !== 'zip') {
            throw ValidationException::withMessages(['backup_file' => 'Please upload an ABS .zip backup archive.']);
        }
        if (strtoupper(trim($data['confirmation'])) !== 'RESTORE') {
            throw ValidationException::withMessages(['confirmation' => 'Type RESTORE exactly to confirm the database replacement.']);
        }

        try {
            $safetyBackup = $backups->create(false);
            $result = $backups->restore(
                $request->file('backup_file')->getRealPath(),
                $request->boolean('restore_uploads'),
                $request->boolean('run_migrations')
            );

            return redirect()->route('admin.enterprise.backups')->with(
                'success',
                'ABS backup restored successfully. '.$result['uploads_restored'].' uploaded files restored. A pre-restore safety backup was created as '.$safetyBackup['name'].'. If your restored database contains different login/session records, sign in again using the restored administrator account.'
            );
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('warning', 'Restore failed: '.$e->getMessage());
        }
    }
}
