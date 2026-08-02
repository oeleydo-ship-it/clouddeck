<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SslCertificateResource;
use App\Jobs\Operations\InstallSslCertificateJob;
use App\Models\Site;
use App\Models\SslCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SslCertificateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SslCertificateResource::collection(SslCertificate::where('user_id', $request->user()->id)->latest()->paginate());
    }

    public function store(Request $request): SslCertificateResource
    {
        $data = $request->validate(['site_id' => ['required', 'uuid', Rule::exists('sites', 'id')->where('user_id', $request->user()->id)], 'force_https' => ['sometimes', 'boolean'], 'auto_renew' => ['sometimes', 'boolean']]);
        $site = Site::findOrFail($data['site_id']);
        $certificate = $site->sslCertificates()->create(['user_id' => $request->user()->id, 'domains' => [$site->domain], 'force_https' => $request->boolean('force_https', true), 'auto_renew' => $request->boolean('auto_renew', true)]);
        InstallSslCertificateJob::dispatch($certificate->id)->onQueue('operations');

        return new SslCertificateResource($certificate);
    }
}
