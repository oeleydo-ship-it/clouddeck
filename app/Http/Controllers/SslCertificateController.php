<?php

namespace App\Http\Controllers;

use App\Jobs\Operations\InstallSslCertificateJob;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SslCertificateController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422);
        $data = $request->validate(['force_https' => ['sometimes', 'boolean'], 'auto_renew' => ['sometimes', 'boolean']]);
        $certificate = $site->sslCertificates()->latest()->first();
        if (! $certificate) {
            $certificate = $site->sslCertificates()->create(['user_id' => $request->user()->id, 'domains' => [$site->domain], 'force_https' => $request->boolean('force_https', true), 'auto_renew' => $request->boolean('auto_renew', true)]);
        } else {
            $certificate->update(['force_https' => $request->boolean('force_https'), 'auto_renew' => $request->boolean('auto_renew'), 'status' => 'pending']);
        }InstallSslCertificateJob::dispatch($certificate->id)->onQueue('operations');

        return back()->with('status', 'SSL issuance queued.');
    }
}
