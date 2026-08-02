<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SiteRelativePath;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemoteManagementController extends Controller
{
    public function __invoke(Request $request, Site $site, SiteRelativePath $paths): View
    {
        $this->authorize('view', $site);
        $path = $paths->normalize($request->query('path', '.'));
        $editor = $request->filled('editor') ? $paths->normalize($request->query('editor')) : null;
        $listingOperation = $site->fileOperations()->where('action', 'list')->where('path', $path)->where('status', 'successful')->latest()->first();
        $readOperation = $editor ? $site->fileOperations()->where('action', 'read')->where('path', $editor)->where('status', 'successful')->latest()->first() : null;
        $pending = $site->fileOperations()->whereIn('status', ['pending', 'running'])->exists() || $site->terminalCommands()->whereIn('status', ['pending', 'running'])->exists() || $site->configurations()->whereIn('status', ['pending', 'applying'])->exists();

        return view('sites.remote', [
            'site' => $site->load('server'),
            'configurations' => $site->configurations()->latest()->limit(20)->get(),
            'operations' => $site->fileOperations()->latest()->limit(20)->get(),
            'commands' => $site->terminalCommands()->latest()->limit(20)->get(),
            'path' => $path,
            'editor' => $editor,
            'listing' => $listingOperation?->result ? json_decode($listingOperation->result, true, flags: JSON_THROW_ON_ERROR) : [],
            'readOperation' => $readOperation,
            'pending' => $pending,
        ]);
    }
}
