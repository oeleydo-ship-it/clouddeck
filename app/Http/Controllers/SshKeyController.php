<?php

namespace App\Http\Controllers;

use App\Models\SshKey;
use App\Services\SshKeyGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use RuntimeException;

class SshKeyController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('SshKeys/Index', [
            'title' => 'SSH keys',
            'keys' => $request->user()->sshKeys()->latest()->get(),
        ]);
    }

    public function generate(Request $request, SshKeyGenerator $generator): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $pair = $generator->generate($request->user()->email);
        $key = $request->user()->sshKeys()->create([...$data, ...$pair]);

        return back()->with('status', 'SSH key generated.')->with('download_key', $key->id);
    }

    public function store(Request $request, SshKeyGenerator $generator): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'public_key' => ['required', 'string', 'max:16384']]);
        try {
            $data['fingerprint'] = $generator->fingerprint($data['public_key']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['public_key' => $exception->getMessage()])->onlyInput('name', 'public_key');
        }
        if ($request->user()->sshKeys()->where('fingerprint', $data['fingerprint'])->exists()) {
            return back()->withErrors(['public_key' => 'This SSH key is already registered.']);
        }
        $request->user()->sshKeys()->create($data);

        return back()->with('status', 'Public key uploaded.');
    }

    public function download(Request $request, SshKey $sshKey): Response
    {
        abort_unless($sshKey->user_id === $request->user()->id && $sshKey->private_key && ! $sshKey->private_key_downloaded_at, 404);
        $private = $sshKey->private_key;
        $sshKey->update(['private_key_downloaded_at' => now()]);

        return response($private)->header('Content-Type', 'application/x-pem-file')->header('Content-Disposition', 'attachment; filename="Uplary-'.$sshKey->id.'.pem"')->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, SshKey $sshKey): RedirectResponse
    {
        abort_unless($sshKey->user_id === $request->user()->id, 403);
        if ($sshKey->servers()->exists()) {
            return back()->withErrors(['key' => 'This key is assigned to one or more servers.']);
        }
        $sshKey->delete();

        return back()->with('status', 'SSH key deleted.');
    }
}
