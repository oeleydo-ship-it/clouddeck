<?php

namespace App\Http\Controllers;

use App\Cloud\CloudProviderManager;
use App\Cloud\Exceptions\CloudCredentialException;
use App\Models\CloudAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CloudAccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('cloud-accounts.index', ['accounts' => $request->user()->cloudAccounts()->withCount('servers')->latest()->get()]);
    }

    public function store(Request $request, CloudProviderManager $providers): RedirectResponse
    {
        $providerKeys = array_keys(config('clouddeck.providers'));
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', Rule::in($providerKeys)],
        ]);
        $drivesApi = (bool) config('clouddeck.providers.'.$request->input('provider').'.api');

        // Each provider is asked only for what it actually needs. Uplary drives some
        // through an API, where a token can be proved here and then used to create and
        // destroy servers. For the rest it connects to a machine the operator already
        // runs, so an address is the useful thing and a token would be decoration.
        $data = $request->validate($drivesApi
            ? ['token' => ['required', 'string', 'min:20', 'max:255']]
            : [
                'public_ip' => ['required', 'ip', Rule::unique('servers', 'public_ip')->whereNull('deleted_at')],
                'ssh_port' => ['required', 'integer', 'between:1,65535'],
            ]);

        $account = new CloudAccount([
            'name' => $request->input('name'),
            'provider' => $request->input('provider'),
            'credentials' => $drivesApi ? ['token' => $data['token']] : [],
        ]);
        $account->user()->associate($request->user());

        if (! $drivesApi) {
            $account->save();

            // Handed to the SSH step with the address already filled in, because the key
            // still has to be authorised on the server before anything can reach it.
            return redirect()->route('servers.custom', [
                'cloud_account' => $account->id,
                'public_ip' => $data['public_ip'],
                'ssh_port' => $data['ssh_port'],
            ]);
        }

        try {
            $providers->for($account)->validateCredentials();
        } catch (CloudCredentialException $exception) {
            return back()->withErrors(['token' => $exception->getMessage()])->onlyInput('name');
        } catch (ConnectionException) {
            return back()->withErrors(['token' => 'Uplary could not reach DigitalOcean. Check this server\'s outbound HTTPS connection and try again.'])->onlyInput('name');
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
