<?php

namespace App\Http\Controllers;

use App\Cloud\CloudProviderManager;
use App\Cloud\Exceptions\CloudCredentialException;
use App\Models\CloudAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CloudAccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('cloud-accounts.index', ['accounts' => $request->user()->cloudAccounts()->withCount('servers')->latest()->get()]);
    }

    public function store(Request $request, CloudProviderManager $providers): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'provider' => ['required', 'in:digitalocean'], 'token' => ['required', 'string', 'min:20', 'max:255']]);
        $account = new CloudAccount(['name' => $data['name'], 'provider' => $data['provider'], 'credentials' => ['token' => $data['token']]]);
        $account->user()->associate($request->user());

        try {
            $providers->for($account)->validateCredentials();
        } catch (CloudCredentialException $exception) {
            return back()->withErrors(['token' => $exception->getMessage()])->onlyInput('name');
        } catch (ConnectionException) {
            return back()->withErrors(['token' => 'CloudDeck could not reach DigitalOcean. Check this server\'s outbound HTTPS connection and try again.'])->onlyInput('name');
        }

        $account->validated_at = now();
        $account->save();

        return back()->with('status', 'Cloud account connected.');
    }

    public function destroy(Request $request, CloudAccount $cloudAccount): RedirectResponse
    {
        abort_unless($cloudAccount->user_id === $request->user()->id, 403);
        if ($cloudAccount->servers()->exists()) {
            return back()->withErrors(['account' => 'Delete the attached servers before disconnecting this account.']);
        }
        $cloudAccount->delete();

        return back()->with('status', 'Cloud account disconnected.');
    }
}
