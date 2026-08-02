<?php

namespace App\Http\Controllers;

use App\Jobs\Sites\BackupWordPressSiteJob;
use App\Jobs\Sites\InstallWordPressCoreJob;
use App\Jobs\Sites\RefreshWordPressInventoryJob;
use App\Jobs\Sites\RestoreWordPressSiteJob;
use App\Jobs\Sites\RunWordPressCommandJob;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WordPressController extends Controller
{
    public function manage(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $this->assertInstalled($site);

        $data = $request->validate([
            'target' => ['required', Rule::in(['plugin', 'theme'])],
            'action' => ['required', Rule::in(['install', 'activate', 'deactivate', 'delete', 'update', 'list'])],
            // A wordpress.org slug, which is what WP-CLI resolves. Anything outside this
            // shape would be handed to a shell command.
            'slug' => ['required_unless:action,list', 'nullable', 'regex:/^[a-z0-9][a-z0-9-]{0,62}$/'],
        ]);

        $command = $site->terminalCommands()->create([
            'user_id' => $request->user()->id,
            'command' => trim(sprintf('wp %s %s %s', $data['target'], $data['action'], $data['slug'] ?? '')),
        ]);

        RunWordPressCommandJob::dispatch($command->id, $data['action'], $data['target'], $data['slug'] ?? '')->onQueue('operations');

        return back()->with('status', 'Queued: '.$command->command.'. Output appears in the console.');
    }

    /**
     * Finishes WordPress's own setup so a deployed site is a working site rather than one
     * parked on the installer. The password is generated here and shown once: asking for one
     * would mean carrying it through a form, a queue payload, and a log.
     */
    public function install(Request $request, Site $site, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->isWordPress(), 404);
        abort_if($site->wordpressIsInstalled(), 422, 'WordPress is already installed on this site.');
        abort_unless($site->last_deployed_at !== null, 422, 'Deploy the site before finishing the install.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            // WordPress's own rule for a username, minus the characters that would need
            // quoting once this reaches a shell.
            'admin_user' => ['required', 'string', 'regex:/^[A-Za-z0-9_.@-]{3,60}$/'],
            'admin_email' => ['required', 'email', 'max:190'],
        ]);

        $password = Str::password(24, symbols: false);

        InstallWordPressCoreJob::dispatch($site->id, $data['title'], $data['admin_user'], $data['admin_email'], $password)
            ->onQueue('operations');

        // Deliberately not written to the audit trail or to the site's console history.
        $audit->record($request, 'site.wordpress_install_started', $site, [], ['admin_user' => $data['admin_user']]);

        return back()
            ->with('status', 'Installing WordPress. Sign in at https://'.$site->domain.'/wp-admin as '.$data['admin_user'].'.')
            ->with('wordpress_admin_password', $password);
    }

    public function refresh(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $this->assertInstalled($site);
        RefreshWordPressInventoryJob::dispatch($site->id)->onQueue('operations');

        return back()->with('status', 'Reading the installed plugins and themes.');
    }

    public function backup(Request $request, Site $site, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $site);
        $this->assertInstalled($site);

        $backup = $site->backups()->create([
            'user_id' => $request->user()->id,
            'label' => now()->format('Ymd-His'),
            'status' => 'pending',
        ]);

        BackupWordPressSiteJob::dispatch($backup->id)->onQueue('operations');
        $audit->record($request, 'site.backup_started', $site, [], ['label' => $backup->label]);

        return back()->with('status', 'Backing up the database and wp-content.');
    }

    public function restore(Request $request, SiteBackup $siteBackup, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $siteBackup->site);
        abort_unless($siteBackup->status === 'completed', 422, 'That backup did not finish, so there is nothing to restore.');

        RestoreWordPressSiteJob::dispatch($siteBackup->id)->onQueue('operations');
        $audit->record($request, 'site.restore_started', $siteBackup->site, [], ['label' => $siteBackup->label]);

        return back()->with('status', 'Restoring '.$siteBackup->label.'. The current database and content are captured first.');
    }

    /**
     * Every one of these runs WP-CLI against the live install, which needs the tables the
     * browser setup creates. Running them before that fails with an unhelpful WP-CLI error.
     */
    private function assertInstalled(Site $site): void
    {
        abort_unless($site->isWordPress(), 404);
        abort_unless($site->wordpressIsInstalled(), 422, 'Finish the WordPress install before managing plugins, themes, or backups.');
    }
}
