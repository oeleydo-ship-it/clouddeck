<?php

namespace App\Cloud\Contracts;

use App\Cloud\Data\CreateServerData;

interface CloudProvider
{
    public function validateCredentials(): bool;

    public function regions(): array;

    public function sizes(): array;

    public function images(): array;

    public function servers(): array;

    public function ensureSshKey(string $name, string $publicKey, ?string $fingerprint = null): string;

    public function createServer(CreateServerData $data): array;

    public function server(string $providerId): array;

    public function action(string $providerId, string $action, array $parameters = []): array;

    public function actionStatus(string $providerId, string $actionId): array;

    public function snapshots(string $providerId): array;

    public function deleteSnapshot(string $snapshotId): void;

    public function deleteServer(string $providerId): void;
}
