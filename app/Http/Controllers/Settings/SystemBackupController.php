<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Core\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Settings\SystemBackupRun;
use App\Services\Settings\SystemBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SystemBackupController extends Controller
{
    public function __construct(private readonly SystemBackupService $backupService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeBackupAccess($request);

        $setting = $this->backupService->currentSetting();
        $runs = SystemBackupRun::query()
            ->latest('StartedOn')
            ->limit(15)
            ->get();

        return view('settings.system-backups.index', compact('setting', 'runs'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeBackupAccess($request);

        $data = $request->validate([
            'BackupPath' => ['required', 'string', 'max:500', 'not_regex:/[\'";]/'],
            'FileNamePrefix' => ['nullable', 'string', 'max:100'],
            'ScheduleEnabled' => ['nullable', 'boolean'],
            'ScheduleTime' => ['nullable', 'date_format:H:i', 'required_if:ScheduleEnabled,1'],
        ], [
            'BackupPath.not_regex' => 'Backup path cannot contain quotes or semicolons.',
            'ScheduleTime.required_if' => 'Set the daily backup time when scheduled backups are enabled.',
        ]);

        $data['ScheduleEnabled'] = $request->boolean('ScheduleEnabled');
        $this->backupService->saveSettings($data, $request->user()?->getAuthIdentifier());

        return redirect()
            ->route('settings.system-backups.index')
            ->with('success', 'System backup settings saved.');
    }

    public function runNow(Request $request): RedirectResponse
    {
        $this->authorizeBackupAccess($request);

        try {
            $run = $this->backupService->runBackup(
                $this->backupService->currentSetting(),
                'manual',
                $request->user()?->getAuthIdentifier()
            );

            return redirect()
                ->route('settings.system-backups.index')
                ->with('success', 'Backup completed: ' . $run->BackupFile);
        } catch (Throwable $e) {
            return redirect()
                ->route('settings.system-backups.index')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    private function authorizeBackupAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPermissionTo(PermissionEnum::Admin->value)
                || $request->user()?->hasPermissionTo(PermissionEnum::Roles->value)
                || $request->user()?->hasRole('admin'),
            403,
            'Only system administrators can manage database backups.'
        );
    }
}
