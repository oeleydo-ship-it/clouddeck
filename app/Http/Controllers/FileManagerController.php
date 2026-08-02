<?php

namespace App\Http\Controllers;

use App\Jobs\RemoteManagement\ExecuteFileOperationJob;
use App\Models\FileOperation;
use App\Models\Site;
use App\Services\SiteRelativePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerController extends Controller
{
    public function store(Request $request, Site $site, SiteRelativePath $paths): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'action' => ['required', Rule::in(['list', 'read', 'download', 'write', 'upload', 'mkdir', 'rename', 'delete', 'chmod', 'zip', 'unzip'])],
            'path' => ['nullable', 'string', 'max:500'],
            'destination' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:1048576'],
            'mode' => ['nullable', 'regex:/^0[0-7]{3}$/'],
            'file' => ['nullable', 'file', 'max:10240'],
            'confirmed' => ['accepted_if:action,delete'],
        ]);
        $action = $data['action'];
        $path = $paths->normalize($data['path'] ?? '.');
        $destination = in_array($action, ['rename', 'zip', 'unzip'], true) ? $paths->normalize($data['destination'] ?? null) : null;
        abort_if(in_array($action, ['rename', 'zip', 'unzip'], true) && blank($data['destination'] ?? null), 422, 'A destination path is required.');
        abort_if($action === 'write' && ! array_key_exists('content', $data), 422, 'File content is required.');
        abort_if($action === 'chmod' && blank($data['mode'] ?? null), 422, 'A permission mode is required.');
        abort_if($action === 'upload' && ! $request->hasFile('file'), 422, 'Choose a file to upload.');

        $disk = config('remote_management.transfer_disk');
        $storagePath = $action === 'upload' ? $request->file('file')->store('pending-remote-files', $disk) : null;
        $operation = $site->fileOperations()->create(['user_id' => $request->user()->id, 'action' => $action, 'path' => $path, 'destination' => $destination, 'payload' => $action === 'write' ? ($data['content'] ?? '') : ($action === 'chmod' ? $data['mode'] : null), 'disk' => $disk, 'storage_path' => $storagePath]);
        ExecuteFileOperationJob::dispatch($operation->id);

        $query = ['path' => $action === 'list' ? $path : ($request->input('current_path', '.'))];
        if ($action === 'read') {
            $query['editor'] = $path;
        }

        return redirect()->route('sites.remote', ['site' => $site, ...$query])->with('status', ucfirst($action).' operation queued.');
    }

    public function download(Request $request, FileOperation $fileOperation): StreamedResponse
    {
        abort_unless($fileOperation->user_id === $request->user()->id && $fileOperation->action === 'download' && $fileOperation->status === 'successful' && $fileOperation->storage_path && Storage::disk($fileOperation->disk)->exists($fileOperation->storage_path), 404);

        return Storage::disk($fileOperation->disk)->download($fileOperation->storage_path, basename($fileOperation->path));
    }
}
