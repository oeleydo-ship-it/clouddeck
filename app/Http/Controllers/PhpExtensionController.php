<?php

namespace App\Http\Controllers;

use App\Jobs\Servers\InstallPhpExtensionJob;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhpExtensionController extends Controller
{
    private const EXTENSIONS = ['intl', 'gd', 'soap', 'xsl', 'ldap', 'imap', 'sqlite3', 'gmp', 'imagick', 'bz2', 'dba'];

    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $data = $request->validate(['extension' => ['required', Rule::in(self::EXTENSIONS)]]);
        $operation = $server->operations()->create(['user_id' => $request->user()->id, 'type' => 'php:extension:'.$data['extension'], 'status' => 'pending']);
        InstallPhpExtensionJob::dispatch($operation->id)->onQueue('operations');

        return back()->with('status', 'Installing php-'.$data['extension'].' for every PHP version on this server.');
    }
}
