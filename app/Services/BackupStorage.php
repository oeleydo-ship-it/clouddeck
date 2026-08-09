<?php

namespace App\Services;

/**
 * Private Laravel disks used for database and full-site backup archives.
 */
final class BackupStorage
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function defaultDisk(): string
    {
        $disk = $this->settings->databaseBackupDisk();

        if ($disk === 's3' && ! $this->settings->objectStorageConfigured()) {
            return 'local';
        }

        return $disk;
    }

    /**
     * Disk names customers may select for new recovery points.
     *
     * @return list<string>
     */
    public function privateDiskNames(): array
    {
        return collect(config('filesystems.disks'))
            ->reject(fn (array $disk) => ($disk['visibility'] ?? null) === 'public')
            ->keys()
            ->filter(function (string $name): bool {
                if ($name === 's3') {
                    return $this->settings->objectStorageConfigured();
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string> disk name => label
     */
    public function privateDiskOptions(): array
    {
        $labels = [
            'local' => 'Local (this server)',
            's3' => 'Object storage (S3-compatible)',
        ];

        $options = [];
        foreach ($this->privateDiskNames() as $name) {
            $options[$name] = $labels[$name] ?? $name;
        }

        return $options;
    }
}
