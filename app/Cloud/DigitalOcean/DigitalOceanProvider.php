<?php

namespace App\Cloud\DigitalOcean;

use App\Cloud\Contracts\CloudProvider;
use App\Cloud\Data\CreateServerData;
use App\Cloud\Exceptions\CloudCredentialException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class DigitalOceanProvider implements CloudProvider
{
    public function __construct(private readonly string $token) {}

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.digitalocean.url', 'https://api.digitalocean.com/v2'))->withToken($this->token)->acceptJson()->retry(3, 500, throw: false)->timeout(30);
    }

    public function validateCredentials(): bool
    {
        $account = $this->client()->get('/account');
        $this->assertValidationResponse($account, 'account');

        if ($account->json('account.status') !== 'active') {
            throw new CloudCredentialException('The DigitalOcean account is not active. Resolve the account status in DigitalOcean and try again.');
        }

        $this->assertValidationResponse($this->client()->get('/droplets', ['per_page' => 1]), 'droplets');
        $this->assertValidationResponse($this->client()->get('/account/keys', ['per_page' => 1]), 'SSH keys');

        return true;
    }

    private function assertValidationResponse(Response $response, string $resource): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $message = match ($status) {
            401 => 'DigitalOcean rejected this API token. Create an active token and paste it again.',
            403 => "The token cannot read {$resource}. Grant read and write access to droplets and SSH keys.",
            429 => 'DigitalOcean rate-limited this check. Wait a minute and try again.',
            default => $status >= 500
                ? 'DigitalOcean is temporarily unavailable. Try again shortly.'
                : "DigitalOcean could not verify {$resource} (HTTP {$status}).",
        };

        throw new CloudCredentialException($message, $status === 429 ? 429 : 422, $status);
    }

    public function regions(): array
    {
        return $this->client()->get('/regions')->throw()->json('regions', []);
    }

    public function sizes(): array
    {
        return $this->client()->get('/sizes')->throw()->json('sizes', []);
    }

    public function images(): array
    {
        return $this->client()->get('/images', ['type' => 'distribution'])->throw()->json('images', []);
    }

    public function servers(): array
    {
        return $this->client()->get('/droplets', ['per_page' => 200])->throw()->json('droplets', []);
    }

    public function ensureSshKey(string $name, string $publicKey, ?string $fingerprint = null): string
    {
        if ($existing = $this->findSshKey($publicKey, $fingerprint)) {
            return $existing;
        }

        $response = $this->client()->post('/account/keys', ['name' => $name, 'public_key' => $publicKey]);

        // DigitalOcean rejects a re-upload of a key it already stores. Its listing reports MD5
        // fingerprints while CloudDeck records SHA256, so resolve the collision by key material.
        if ($response->status() === 422 && $existing = $this->findSshKey($publicKey, $fingerprint)) {
            return $existing;
        }

        return (string) $response->throw()->json('ssh_key.id');
    }

    private function findSshKey(string $publicKey, ?string $fingerprint): ?string
    {
        $material = $this->keyMaterial($publicKey);
        $keys = collect($this->client()->get('/account/keys', ['per_page' => 200])->throw()->json('ssh_keys', []));

        $match = $keys->first(fn (array $key) => $material !== null && $this->keyMaterial((string) ($key['public_key'] ?? '')) === $material)
            ?? ($fingerprint ? $keys->firstWhere('fingerprint', $fingerprint) : null);

        return $match ? (string) $match['id'] : null;
    }

    private function keyMaterial(string $publicKey): ?string
    {
        return preg_split('/\s+/', trim($publicKey))[1] ?? null;
    }

    public function createServer(CreateServerData $data): array
    {
        return $this->client()->post('/droplets', ['name' => $data->name, 'region' => $data->region, 'size' => $data->size, 'image' => $data->image, 'ssh_keys' => $data->sshKeyIds, 'backups' => false, 'monitoring' => true, 'tags' => ['managed-by-clouddeck']])->throw()->json('droplet');
    }

    public function server(string $providerId): array
    {
        return $this->client()->get("/droplets/{$providerId}")->throw()->json('droplet');
    }

    public function action(string $providerId, string $action, array $parameters = []): array
    {
        return $this->client()->post("/droplets/{$providerId}/actions", ['type' => $action, ...$parameters])->throw()->json('action');
    }

    public function actionStatus(string $providerId, string $actionId): array
    {
        return $this->client()->get("/droplets/{$providerId}/actions/{$actionId}")->throw()->json('action');
    }

    public function snapshots(string $providerId): array
    {
        return $this->client()->get("/droplets/{$providerId}/snapshots", ['per_page' => 200])->throw()->json('snapshots', []);
    }

    public function deleteSnapshot(string $snapshotId): void
    {
        $this->client()->delete("/snapshots/{$snapshotId}")->throw();
    }

    public function deleteServer(string $providerId): void
    {
        $response = $this->client()->delete("/droplets/{$providerId}");
        // A Droplet already removed at the provider (deleted manually, or by another CloudDeck
        // record that happened to point at the same one) is not a failure to delete it: there
        // is nothing left to delete, so treat 404 the same as a successful DELETE.
        if ($response->status() === 404) {
            return;
        }
        $response->throw();
    }
}
