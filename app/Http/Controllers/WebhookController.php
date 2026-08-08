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
        if ($branch === null || $branch !== $site->branch) {
            return response()->json(['message' => 'Branch ignored.']);
        }
        $hash = $this->commitHash($payload);
        if ($this->isDeletedRef($hash)) {
            return response()->json(['message' => 'Deleted branch ignored.']);
        }
        if ($hash && $site->deployments()->where('commit_hash', $hash)->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running, DeploymentStatus::Successful])->exists()) {
            return response()->json(['message' => 'Commit already deployed.']);
        }
        $deployment = $start->execute($site, null, 'webhook', [
            'hash' => $hash,
            'message' => $this->commitMessage($payload),
        ]);

        return response()->json(['deployment_id' => $deployment->id, 'message' => 'Deployment queued.'], 202);
    }

    /**
     * GitHub and Bitbucket sign with HMAC SHA-256 (X-Hub-Signature-256 / X-Hub-Signature).
     * GitLab authenticates with a shared token in X-Gitlab-Token.
     * Custom integrations may use X-Uplary-Signature (or legacy X-CloudDeck-Signature).
     */
    private function verifySignature(Request $request, string $secret): void
    {
        $body = $request->getContent();
        $provided = $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Hub-Signature')
            ?? $request->header('X-Uplary-Signature')
            ?? $request->header('X-CloudDeck-Signature');

        $valid = false;
        if (is_string($provided) && str_starts_with($provided, 'sha256=')) {
            $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
            $valid = hash_equals($expected, $provided);
        }

        $gitlabToken = $request->header('X-Gitlab-Token');
        if (! $valid && is_string($gitlabToken) && $gitlabToken !== '') {
            $valid = hash_equals($secret, $gitlabToken);
        }

        abort_unless($valid, 403, 'Invalid webhook signature.');
    }

    private function branch(array $payload): ?string
    {
        // GitHub / GitLab push: ref = refs/heads/<branch>
        // GitLab merge request style: object_attributes.ref
        // Bitbucket Cloud: push.changes[0].new.name
        $ref = data_get($payload, 'ref') ?? data_get($payload, 'object_attributes.ref');
        if (is_string($ref) && $ref !== '') {
            return str_replace('refs/heads/', '', $ref);
        }

        $bitbucket = data_get($payload, 'push.changes.0.new.name');

        return is_string($bitbucket) && $bitbucket !== '' ? $bitbucket : null;
    }

    private function commitHash(array $payload): ?string
    {
        $hash = data_get($payload, 'after')
            ?? data_get($payload, 'checkout_sha')
            ?? data_get($payload, 'push.changes.0.new.target.hash');

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    private function commitMessage(array $payload): ?string
    {
        $message = data_get($payload, 'head_commit.message')
            ?? data_get($payload, 'commits.0.message')
            ?? data_get($payload, 'push.changes.0.new.target.message');

        return is_string($message) && $message !== '' ? $message : null;
    }

    private function isDeletedRef(?string $hash): bool
    {
        return $hash !== null && preg_match('/^0+$/', $hash) === 1;
    }
}
