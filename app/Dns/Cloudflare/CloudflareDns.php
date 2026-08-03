<?php

namespace App\Dns\Cloudflare;

use App\Dns\Exceptions\DnsCredentialException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare's DNS API.
 *
 * Records are read from Cloudflare on every request rather than mirrored into this
 * database. A zone can be edited from Cloudflare's own dashboard, by another tool, or by
 * a teammate at any moment, and a local copy would confidently show whatever it last saw.
 * The cost is a round trip per page; the benefit is that what is displayed is true.
 */
final class CloudflareDns
{
    public function __construct(private readonly string $token) {}

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.cloudflare.url', 'https://api.cloudflare.com/client/v4'))
            ->withToken($this->token)
            ->acceptJson()
            ->retry(3, 500, throw: false)
            ->timeout(30);
    }

    /**
     * Proves the token works before it is stored, so a bad paste fails on the form rather
     * than silently later when somebody is trying to fix DNS in a hurry.
     */
    public function validateCredentials(): bool
    {
        $verify = $this->client()->get('/user/tokens/verify');

        if ($verify->status() === 401 || $verify->status() === 403) {
            throw new DnsCredentialException('Cloudflare rejected this API token. Create a token with Zone:Read and DNS:Edit and paste it again.');
        }

        $this->assertSucceeded($verify, 'verify the token');

        if ($verify->json('result.status') !== 'active') {
            throw new DnsCredentialException('This Cloudflare token is not active. Check its status in Cloudflare and try again.');
        }

        // A token can verify and still be scoped to nothing useful, which would only show
        // up as an empty zone list that looks like an account with no domains.
        $this->assertSucceeded($this->client()->get('/zones', ['per_page' => 1]), 'read zones');

        return true;
    }

    /**
     * @return array<int, array{id: string, name: string, status: string}>
     */
    public function zones(): array
    {
        $response = $this->client()->get('/zones', ['per_page' => 50]);
        $this->assertSucceeded($response, 'read zones');

        return collect($response->json('result') ?? [])
            ->map(fn (array $zone) => [
                'id' => (string) $zone['id'],
                'name' => (string) $zone['name'],
                'status' => (string) ($zone['status'] ?? 'unknown'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, type: string, name: string, content: string, ttl: int, proxied: bool}>
     */
    public function records(string $zoneId): array
    {
        $response = $this->client()->get("/zones/{$zoneId}/dns_records", ['per_page' => 100]);
        $this->assertSucceeded($response, 'read DNS records');

        return collect($response->json('result') ?? [])
            ->map(fn (array $record) => [
                'id' => (string) $record['id'],
                'type' => (string) $record['type'],
                'name' => (string) $record['name'],
                'content' => (string) ($record['content'] ?? ''),
                'ttl' => (int) ($record['ttl'] ?? 1),
                'proxied' => (bool) ($record['proxied'] ?? false),
            ])
            ->sortBy([['type', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    public function createRecord(string $zoneId, array $attributes): array
    {
        $response = $this->client()->post("/zones/{$zoneId}/dns_records", $attributes);
        $this->assertSucceeded($response, 'create the record');

        return $response->json('result') ?? [];
    }

    public function updateRecord(string $zoneId, string $recordId, array $attributes): array
    {
        $response = $this->client()->put("/zones/{$zoneId}/dns_records/{$recordId}", $attributes);
        $this->assertSucceeded($response, 'update the record');

        return $response->json('result') ?? [];
    }

    public function deleteRecord(string $zoneId, string $recordId): void
    {
        $this->assertSucceeded($this->client()->delete("/zones/{$zoneId}/dns_records/{$recordId}"), 'delete the record');
    }

    /**
     * Cloudflare answers 200 with `success: false` for a rejected change, so the status
     * code alone is not enough to know whether anything happened. Its own error messages
     * are specific ("Record already exists", "Content for A record is invalid") and worth
     * far more to whoever is reading them than a generic failure would be.
     */
    private function assertSucceeded(Response $response, string $action): void
    {
        if ($response->successful() && $response->json('success') === true) {
            return;
        }

        $errors = collect($response->json('errors') ?? [])
            ->map(fn ($error) => is_array($error) ? ($error['message'] ?? null) : (string) $error)
            ->filter()
            ->implode('; ');

        throw new DnsCredentialException(
            "Cloudflare could not {$action}".($errors !== '' ? ": {$errors}" : ' (HTTP '.$response->status().').')
        );
    }
}
