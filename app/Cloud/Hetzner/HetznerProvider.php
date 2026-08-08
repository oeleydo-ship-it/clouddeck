<?php

namespace App\Cloud\Hetzner;

use App\Cloud\Contracts\CloudProvider;
use App\Cloud\Data\CreateServerData;
use App\Cloud\Exceptions\CloudCredentialException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Hetzner Cloud adapter. Catalog and server payloads are normalized to the DigitalOcean-shaped
 * contract the rest of Uplary already consumes (slug/vcpus/memory-MB, status=active, networks.v4).
 */
final class HetznerProvider implements CloudProvider
{
    public function __construct(private readonly string $token) {}

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.hetzner.url', 'https://api.hetzner.cloud/v1'))
            ->withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->retry(3, 500, throw: false)
            ->timeout(30);
    }

    public function validateCredentials(): bool
    {
        $this->assertValidationResponse($this->client()->get('/servers', ['page' => 1, 'per_page' => 1]), 'servers');
        $this->assertValidationResponse($this->client()->get('/ssh_keys', ['page' => 1, 'per_page' => 1]), 'SSH keys');

        return true;
    }

    private function assertValidationResponse(Response $response, string $resource): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $message = match ($status) {
            401 => 'Hetzner rejected this API token. Create an active project token and paste it again.',
            403 => "The token cannot read {$resource}. Grant read and write access in the Hetzner Cloud Console.",
            429 => 'Hetzner rate-limited this check. Wait a minute and try again.',
            default => $status >= 500
                ? 'Hetzner Cloud is temporarily unavailable. Try again shortly.'
                : "Hetzner could not verify {$resource} (HTTP {$status}).",
        };

        throw new CloudCredentialException($message, $status === 429 ? 429 : 422, $status);
    }

    public function regions(): array
    {
        return collect($this->client()->get('/locations')->throw()->json('locations', []))
            ->map(fn (array $location) => [
                'slug' => (string) ($location['name'] ?? ''),
                'name' => (string) ($location['description'] ?? $location['name'] ?? ''),
                'available' => true,
                'country' => $location['country'] ?? null,
                'city' => $location['city'] ?? null,
            ])
            ->filter(fn (array $region) => $region['slug'] !== '')
            ->values()
            ->all();
    }

    public function sizes(): array
    {
        return collect($this->client()->get('/server_types')->throw()->json('server_types', []))
            ->map(function (array $type) {
                $prices = collect($type['prices'] ?? []);
                $monthly = $prices
                    ->map(fn (array $price) => (float) data_get($price, 'price_monthly.gross', data_get($price, 'price_monthly.net', 0)))
                    ->filter(fn (float $amount) => $amount > 0)
                    ->min() ?? 0.0;

                return [
                    'slug' => (string) ($type['name'] ?? ''),
                    'vcpus' => (int) ($type['cores'] ?? 0),
                    // Hetzner reports memory in GB; Uplary catalog expects MB (same as DigitalOcean).
                    'memory' => (int) round(((float) ($type['memory'] ?? 0)) * 1024),
                    'disk' => (int) ($type['disk'] ?? 0),
                    'price_monthly' => round((float) $monthly, 2),
                    'available' => ! ($type['deprecated'] ?? false),
                    'architecture' => $type['architecture'] ?? null,
                ];
            })
            ->filter(fn (array $size) => $size['slug'] !== '' && ($size['available'] ?? true))
            ->values()
            ->all();
    }

    public function images(): array
    {
        return collect($this->client()->get('/images', ['type' => 'system', 'status' => 'available'])->throw()->json('images', []))
            ->map(fn (array $image) => [
                'slug' => (string) ($image['name'] ?? $image['id'] ?? ''),
                'name' => (string) ($image['description'] ?? $image['name'] ?? ''),
                'distribution' => ucfirst((string) ($image['os_flavor'] ?? 'Unknown')),
                'created_at' => (string) ($image['created'] ?? now()->toIso8601String()),
                'id' => $image['id'] ?? null,
                'architecture' => $image['architecture'] ?? null,
            ])
            ->filter(fn (array $image) => $image['slug'] !== '')
            ->values()
            ->all();
    }

    public function servers(): array
    {
        return collect($this->client()->get('/servers', ['per_page' => 50])->throw()->json('servers', []))
            ->map(fn (array $server) => $this->normalizeServer($server))
            ->values()
            ->all();
    }

    public function ensureSshKey(string $name, string $publicKey, ?string $fingerprint = null): string
    {
        if ($existing = $this->findSshKey($publicKey, $fingerprint)) {
            return $existing;
        }

        $response = $this->client()->post('/ssh_keys', ['name' => $name, 'public_key' => $publicKey]);

        if (in_array($response->status(), [409, 422], true) && $existing = $this->findSshKey($publicKey, $fingerprint)) {
            return $existing;
        }

        return (string) $response->throw()->json('ssh_key.id');
    }

    private function findSshKey(string $publicKey, ?string $fingerprint): ?string
    {
        $material = $this->keyMaterial($publicKey);
        $keys = collect($this->client()->get('/ssh_keys', ['per_page' => 50])->throw()->json('ssh_keys', []));

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
        $payload = [
            'name' => $data->name,
            'location' => $data->region,
            'server_type' => $data->size,
            'image' => $data->image,
            'ssh_keys' => array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $data->sshKeyIds),
            'start_after_create' => true,
            'labels' => ['managed-by' => 'uplary'],
        ];

        $server = $this->client()->post('/servers', $payload)->throw()->json('server');

        return $this->normalizeServer((array) $server);
    }

    public function server(string $providerId): array
    {
        return $this->normalizeServer((array) $this->client()->get("/servers/{$providerId}")->throw()->json('server'));
    }

    public function action(string $providerId, string $action, array $parameters = []): array
    {
        [$hetznerAction, $body] = $this->mapAction($action, $parameters);
        $result = $this->client()->post("/servers/{$providerId}/actions/{$hetznerAction}", $body)->throw()->json('action');

        return $this->normalizeAction((array) $result);
    }

    public function actionStatus(string $providerId, string $actionId): array
    {
        return $this->normalizeAction((array) $this->client()->get("/servers/{$providerId}/actions/{$actionId}")->throw()->json('action'));
    }

    public function snapshots(string $providerId): array
    {
        return collect($this->client()->get('/images', ['type' => 'snapshot', 'status' => 'available', 'per_page' => 50])->throw()->json('images', []))
            ->filter(fn (array $image) => (string) data_get($image, 'created_from.id') === (string) $providerId)
            ->map(fn (array $image) => [
                'id' => $image['id'],
                'name' => $image['description'] ?? $image['name'] ?? ('snapshot-'.$image['id']),
                'size_gigabytes' => isset($image['image_size']) ? (float) $image['image_size'] : null,
                'created_at' => $image['created'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function deleteSnapshot(string $snapshotId): void
    {
        $response = $this->client()->delete("/images/{$snapshotId}");
        if ($response->status() === 404) {
            return;
        }
        $response->throw();
    }

    public function deleteServer(string $providerId): void
    {
        $response = $this->client()->delete("/servers/{$providerId}");
        if ($response->status() === 404) {
            return;
        }
        $response->throw();
    }

    /**
     * @param  array<string, mixed>  $server
     * @return array<string, mixed>
     */
    private function normalizeServer(array $server): array
    {
        $status = (string) ($server['status'] ?? '');
        $ip = data_get($server, 'public_net.ipv4.ip');

        return [
            'id' => $server['id'] ?? null,
            'name' => $server['name'] ?? null,
            'status' => in_array($status, ['running', 'active'], true) ? 'active' : $status,
            'networks' => [
                'v4' => $ip ? [['type' => 'public', 'ip_address' => $ip]] : [],
            ],
            'region' => ['slug' => data_get($server, 'datacenter.location.name') ?? data_get($server, 'location.name')],
            'size' => ['slug' => data_get($server, 'server_type.name')],
            'image' => ['slug' => data_get($server, 'image.name')],
            'created_at' => $server['created'] ?? null,
            'provider' => 'hetzner',
            'raw' => $server,
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function mapAction(string $action, array $parameters): array
    {
        return match ($action) {
            'snapshot' => ['create_image', [
                'type' => 'snapshot',
                'description' => (string) ($parameters['name'] ?? ('snapshot-'.now()->format('YmdHis'))),
            ]],
            'restore' => ['rebuild', [
                'image' => $parameters['image'] ?? null,
            ]],
            'power_on' => ['poweron', []],
            'power_off' => ['poweroff', []],
            'shutdown' => ['shutdown', []],
            'reboot', 'power_cycle' => ['reboot', []],
            default => [$action, $parameters],
        };
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function normalizeAction(array $action): array
    {
        $status = (string) ($action['status'] ?? '');

        return [
            'id' => $action['id'] ?? null,
            'status' => match ($status) {
                'running' => 'in-progress',
                'success' => 'completed',
                'error' => 'errored',
                default => $status,
            },
            'type' => $action['command'] ?? $action['type'] ?? null,
            'raw' => $action,
        ];
    }
}
