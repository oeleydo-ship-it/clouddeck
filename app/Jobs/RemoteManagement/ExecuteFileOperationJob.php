<?php

namespace App\Jobs\RemoteManagement;

use App\Models\FileOperation;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExecuteFileOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly string $operationId)
    {
        $this->onQueue('operations');
    }

    public function handle(SshClient $ssh): void
    {
        $operation = FileOperation::with('site.server.sshKey')->findOrFail($this->operationId);
        $operation->update(['status' => 'running', 'started_at' => now(), 'failure_reason' => null]);
        $payload = match ($operation->action) {
            'write' => base64_encode($operation->payload ?? ''),
            'upload' => base64_encode(Storage::disk($operation->disk)->get($operation->storage_path)),
            default => '',
        };
        $output = $ssh->runScript($operation->site->server, resource_path('scripts/site-file-operation.sh'), [
            'DOMAIN' => $operation->site->domain,
            'ACTION' => $operation->action,
            'PATH' => $operation->path,
            'DESTINATION' => $operation->destination ?? '',
            'PAYLOAD' => $payload,
            'MODE' => $operation->payload ?? '0644',
        ]);

        $updates = ['status' => 'successful', 'finished_at' => now()];
        if ($operation->action === 'list') {
            $updates['result'] = json_encode(collect(preg_split('/\r?\n/', trim($output)))->filter()->map(function (string $line): array {
                [$encoded, $type, $size, $modified, $mode, $owner] = array_pad(explode("\t", $line), 6, null);

                // Mode and owner come from newer listings only. An older one still in the
                // table simply shows no permissions rather than breaking the page.
                return ['name' => base64_decode($encoded, true) ?: '', 'type' => $type, 'size' => (int) $size, 'modified_at' => (int) $modified, 'mode' => $mode, 'owner' => $owner];
            })->values()->all(), JSON_THROW_ON_ERROR);
        } elseif ($operation->action === 'read') {
            $updates['result'] = $this->decode($output);
        } elseif ($operation->action === 'download') {
            $contents = $this->decode($output);
            $path = 'remote-files/'.$operation->id.'/'.basename($operation->path);
            Storage::disk($operation->disk)->put($path, $contents);
            $updates['storage_path'] = $path;
            $updates['size'] = strlen($contents);
        } else {
            $updates['result'] = trim($output);
        }
        $operation->update($updates);
        if ($operation->action === 'upload' && $operation->storage_path) {
            Storage::disk($operation->disk)->delete($operation->storage_path);
            $operation->update(['storage_path' => null]);
        }
    }

    public function failed(Throwable $exception): void
    {
        FileOperation::find($this->operationId)?->update(['status' => 'failed', 'failure_reason' => $exception->getMessage(), 'finished_at' => now()]);
    }

    private function decode(string $output): string
    {
        $decoded = base64_decode(trim($output), true);
        if ($decoded === false) {
            throw new RuntimeException('The server returned an invalid file payload.');
        }

        return $decoded;
    }
}
