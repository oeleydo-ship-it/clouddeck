<?php

namespace App\Http\Controllers;

use App\Actions\Deployments\StartDeployment;
use App\Enums\DeploymentStatus;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Site $site, StartDeployment $start): JsonResponse
    {
        abort_unless($site->auto_deploy && $site->webhook_secret && $site->status === 'active', 404);
        $this->verifySignature($request, $site->webhook_secret);
        $payload = $request->json()->all();
        $branch = $this->branch($payload);
        if ($branch !== $site->branch) {
            return response()->json(['message' => 'Branch ignored.']);
        }
        $hash = data_get($payload, 'after') ?? data_get($payload, 'checkout_sha') ?? data_get($payload, 'push.changes.0.new.target.hash');
        if ($hash && $site->deployments()->where('commit_hash', $hash)->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running, DeploymentStatus::Successful])->exists()) {
            return response()->json(['message' => 'Commit already deployed.']);
        }
        $deployment = $start->execute($site, null, 'webhook', ['hash' => $hash, 'message' => data_get($payload, 'head_commit.message') ?? data_get($payload, 'commits.0.message') ?? data_get($payload, 'push.changes.0.new.target.message')]);

        return response()->json(['deployment_id' => $deployment->id, 'message' => 'Deployment queued.'], 202);
    }

    private function verifySignature(Request $request, string $secret): void
    {
        $sha256 = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        $provided = $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Hub-Signature')
            ?? $request->header('X-Uplary-Signature')
            ?? $request->header('X-CloudDeck-Signature');
        $valid = ($provided && hash_equals($sha256, $provided)) || ($request->header('X-Gitlab-Token') && hash_equals($secret, $request->header('X-Gitlab-Token')));
        abort_unless($valid, 403, 'Invalid webhook signature.');
    }

    private function branch(array $payload): ?string
    {
        $ref = data_get($payload, 'ref') ?? data_get($payload, 'object_attributes.ref');

        return $ref ? str_replace('refs/heads/', '', $ref) : data_get($payload, 'push.changes.0.new.name');
    }
}
