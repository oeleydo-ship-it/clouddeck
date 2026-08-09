<?php

namespace App\Http\Controllers;

use App\Jobs\Sites\BackupApplicationSiteJob;
use App\Jobs\Sites\RestoreApplicationSiteJob;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Services\AuditLogger;
use App\Services\BackupStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteBackupController extends Controller
{
    public function store(Request $request, Site $site, AuditLogger $audit, BackupStorage $backupStorage): RedirectResponse
    {
        $this->authorize('update', $site);

        $disk = $backupStorage->defaultDisk();

        $backup = $site->backups()->create([
            'user_id' => $request->user()->id,
            'label' => now()->format('Ymd-His'),
            'kind' => 'full_app',
            'source' => 'manual',
            'disk' => $disk,
            'status' => 'pending',
        ]);

        BackupApplicationSiteJob::dispatch($backup->id)->onQueue('operations');
        $audit->record($request, 'site.full-backup_started', $site, [], [
            'backup_id' => $backup->id,
            'label' => $backup->label,
            'disk' => $disk,
        ]);

        return back()->with('status', 'Full site backup (code + database) queued. It will appear here when ready.');
    }

    public function download(Request $request, SiteBackup $siteBackup): StreamedResponse
    {
        $this->authorize('update', $siteBackup->site);
        abort_unless($siteBackup->isFullApp() && $siteBackup->status === 'ready' && $siteBackup->disk_path, 404);
        abort_unless(Storage::disk($siteBackup->disk)->exists($siteBackup->disk_path), 404);

        return Storage::disk($siteBackup->disk)->download(
            $siteBackup->disk_path,
            $siteBackup->site->domain.'-'.$siteBackup->label.'.tar.gz'
        );
    }

    public function restore(Request $request, SiteBackup $siteBackup, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $siteBackup->site);
        abort_unless($siteBackup->isFullApp() && $siteBackup->status === 'ready' && $siteBackup->disk_path, 422, 'That backup is not ready to restore.');

        $request->validate([
            'confirmation' => ['required', Rule::in([$siteBackup->site->domain])],
        ]);

        RestoreApplicationSiteJob::dispatch($siteBackup->id)->onQueue('operations');
        $audit->record($request, 'site.full-restore_started', $siteBackup->site, [], [
            'backup_id' => $siteBackup->id,
            'label' => $siteBackup->label,
        ]);

        return back()->with('status', 'Restoring '.$siteBackup->label.'. The live site will be replaced with this archive.');
    }

    public function destroy(Request $request, SiteBackup $siteBackup, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $siteBackup->site);
        abort_unless($siteBackup->isFullApp(), 422);

        if ($siteBackup->disk_path && $siteBackup->disk) {
            Storage::disk($siteBackup->disk)->delete($siteBackup->disk_path);
        }

        $site = $siteBackup->site;
        $old = $siteBackup->only(['label', 'kind', 'disk_path']);
        $siteBackup->delete();
        $audit->record($request, 'site.full-backup_deleted', $site, $old, []);

        return back()->with('status', 'Site backup removed.');
    }
}
