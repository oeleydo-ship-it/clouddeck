<?php

namespace App\Http\Controllers;

use App\Jobs\Operations\InstallCustomSslCertificateJob;
use App\Jobs\Operations\InstallSslCertificateJob;
use App\Jobs\Operations\RemoveSslCertificateJob;
use App\Models\Site;
use App\Services\CustomSslValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class SslCertificateController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422);
        $request->validate(['force_https' => ['sometimes', 'boolean'], 'auto_renew' => ['sometimes', 'boolean']]);
        $certificate = $site->sslCertificates()->latest()->first();
        if ($certificate && in_array($certificate->status, ['issuing', 'removing'], true)) {
            return back()->withErrors(['ssl' => 'An SSL operation is already in progress for this site.']);
        }
        if (! $certificate) {
            $certificate = $site->sslCertificates()->create([
                'user_id' => $request->user()->id,
                'domains' => [$site->domain],
                'provider' => 'letsencrypt',
                'force_https' => $request->boolean('force_https', true),
                'auto_renew' => $request->boolean('auto_renew', true),
            ]);
        } else {
            $certificate->update([
                'provider' => 'letsencrypt',
                'force_https' => $request->boolean('force_https'),
                'auto_renew' => $request->boolean('auto_renew'),
                'status' => 'pending',
                'certificate_pem' => null,
                'private_key_pem' => null,
                'failure_reason' => null,
            ]);
        }
        InstallSslCertificateJob::dispatch($certificate->id)->onQueue('operations');

        return back()->with('status', 'Let’s Encrypt certificate issuance queued.');
    }

    public function storeCustom(Request $request, Site $site, CustomSslValidator $validator): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422);

        $request->validate([
            'force_https' => ['sometimes', 'boolean'],
            'fullchain' => ['nullable', 'file', 'max:256'],
            'private_key' => ['nullable', 'file', 'max:64'],
            'fullchain_pem' => ['nullable', 'string', 'max:65535'],
            'private_key_pem' => ['nullable', 'string', 'max:16384'],
        ]);

        $certificate = $site->sslCertificates()->latest()->first();
        if ($certificate && in_array($certificate->status, ['issuing', 'removing'], true)) {
            return back()->withErrors(['ssl' => 'An SSL operation is already in progress for this site.'])->withInput();
        }

        $fullchain = $this->pemFromRequest($request, 'fullchain', 'fullchain_pem');
        $privateKey = $this->pemFromRequest($request, 'private_key', 'private_key_pem');
        if ($fullchain === '' || $privateKey === '') {
            return back()->withErrors(array_filter([
                'fullchain' => $fullchain === '' ? 'Provide a certificate fullchain PEM file or paste.' : null,
                'private_key' => $privateKey === '' ? 'Provide a private key PEM file or paste.' : null,
            ]))->withInput();
        }

        $parsed = $validator->validate($fullchain, $privateKey);
        $domains = $parsed['domains'] !== [] ? $parsed['domains'] : [$site->domain];

        $payload = [
            'user_id' => $request->user()->id,
            'domains' => $domains,
            'provider' => 'custom',
            'force_https' => $request->boolean('force_https', true),
            'auto_renew' => false,
            'status' => 'pending',
            'certificate_pem' => $parsed['fullchain'],
            'private_key_pem' => $parsed['private_key'],
            'expires_at' => $parsed['expires_at'],
            'failure_reason' => null,
        ];

        if (! $certificate) {
            $certificate = $site->sslCertificates()->create($payload);
        } else {
            $certificate->update($payload);
        }

        InstallCustomSslCertificateJob::dispatch($certificate->id)->onQueue('operations');

        return back()->with('status', 'Custom certificate install queued.');
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422);

        $certificate = $site->sslCertificates()->latest()->first();
        if (! $certificate) {
            return back()->withErrors(['ssl' => 'No SSL certificate is installed on this site.']);
        }
        if (in_array($certificate->status, ['removing', 'issuing'], true)) {
            return back()->withErrors(['ssl' => 'An SSL operation is already in progress for this site.']);
        }

        $certificate->update(['status' => 'removing', 'failure_reason' => null]);
        RemoveSslCertificateJob::dispatch($certificate->id)->onQueue('operations');

        return back()->with('status', 'SSL removal queued. The site will serve HTTP until you issue or upload a new certificate.');
    }

    private function pemFromRequest(Request $request, string $fileKey, string $textKey): string
    {
        /** @var UploadedFile|null $file */
        $file = $request->file($fileKey);
        if ($file) {
            return (string) file_get_contents($file->getRealPath());
        }

        return trim((string) $request->input($textKey, ''));
    }
}
