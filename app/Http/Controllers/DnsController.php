<?php

namespace App\Http\Controllers;

use App\Dns\Cloudflare\CloudflareDns;
use App\Dns\Exceptions\DnsCredentialException;
use App\Models\DnsAccount;
use App\Models\DnsZone;
use App\Services\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DnsController extends Controller
{
    /**
     * Record types worth offering. Deliberately not the full Cloudflare catalogue: these
     * cover pointing a domain at a server, mail, and verification, and every one of them
     * takes a single content string, so one form serves all of them.
     */
    private const TYPES = ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS'];

    public function index(Request $request): View
    {
        return view('dns.index', [
            'accounts' => $request->user()->dnsAccounts()->withCount('zones')->latest()->get(),
            'zones' => DnsZone::where('user_id', $request->user()->id)->with('account')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'token' => ['required', 'string', 'min:20', 'max:255'],
        ]);

        try {
            (new CloudflareDns($data['token']))->validateCredentials();
        } catch (DnsCredentialException $exception) {
            return back()->withErrors(['token' => $exception->getMessage()])->onlyInput('name');
        } catch (ConnectionException) {
            return back()->withErrors(['token' => 'Could not reach Cloudflare. Check the connection and try again.'])->onlyInput('name');
        }

        $account = $request->user()->dnsAccounts()->create([
            'name' => $data['name'],
            'provider' => 'cloudflare',
            'credentials' => ['token' => $data['token']],
            'validated_at' => now(),
        ]);

        $audit->record($request, 'dns.account.connected', $account, [], ['name' => $account->name]);

        return redirect()->route('dns.index')->with('status', 'Cloudflare connected. Import the zones you want to manage here.');
    }

    /**
     * Pulls the zone list from Cloudflare and records the ones this platform has not seen.
     * Zones are never deleted here: removing one from this list because an API call came
     * back short would quietly drop a domain somebody is relying on.
     */
    public function sync(Request $request, DnsAccount $dnsAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $dnsAccount);

        try {
            $zones = $this->client($dnsAccount)->zones();
        } catch (DnsCredentialException $exception) {
            return back()->withErrors(['dns' => $exception->getMessage()]);
        } catch (ConnectionException) {
            return back()->withErrors(['dns' => 'Could not reach Cloudflare. Check the connection and try again.']);
        }

        foreach ($zones as $zone) {
            DnsZone::updateOrCreate(
                ['dns_account_id' => $dnsAccount->id, 'provider_zone_id' => $zone['id']],
                ['user_id' => $request->user()->id, 'name' => $zone['name'], 'status' => $zone['status'], 'synced_at' => now()],
            );
        }

        $dnsAccount->update(['validated_at' => now()]);

        return back()->with('status', count($zones).' '.str('zone')->plural(count($zones)).' available from Cloudflare.');
    }

    public function show(Request $request, DnsZone $dnsZone): View
    {
        $this->authorizeZone($request, $dnsZone);
        $records = [];
        $error = null;

        try {
            $records = $this->client($dnsZone->account)->records($dnsZone->provider_zone_id);
        } catch (DnsCredentialException $exception) {
            $error = $exception->getMessage();
        } catch (ConnectionException) {
            $error = 'Could not reach Cloudflare, so these records could not be read.';
        }

        return view('dns.zone', [
            'zone' => $dnsZone,
            'records' => $records,
            'error' => $error,
            'types' => self::TYPES,
            // Offered as one-click targets so the common case — point this domain at that
            // box — does not mean copying an IP between two browser tabs.
            'sites' => $dnsZone->sites()->with('server')->get()->filter(fn ($site) => $site->server?->public_ip),
        ]);
    }

    public function storeRecord(Request $request, DnsZone $dnsZone): RedirectResponse
    {
        $this->authorizeZone($request, $dnsZone);
        $data = $this->validateRecord($request);

        return $this->attempt(fn () => $this->client($dnsZone->account)->createRecord($dnsZone->provider_zone_id, $data), 'Record created.');
    }

    public function updateRecord(Request $request, DnsZone $dnsZone, string $record): RedirectResponse
    {
        $this->authorizeZone($request, $dnsZone);
        $data = $this->validateRecord($request);

        return $this->attempt(fn () => $this->client($dnsZone->account)->updateRecord($dnsZone->provider_zone_id, $record, $data), 'Record updated.');
    }

    public function destroyRecord(Request $request, DnsZone $dnsZone, string $record): RedirectResponse
    {
        $this->authorizeZone($request, $dnsZone);

        return $this->attempt(fn () => $this->client($dnsZone->account)->deleteRecord($dnsZone->provider_zone_id, $record), 'Record deleted.');
    }

    public function destroy(Request $request, DnsAccount $dnsAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $dnsAccount);
        $dnsAccount->zones()->delete();
        $dnsAccount->delete();

        return back()->with('status', 'Cloudflare disconnected. The zones themselves are untouched at Cloudflare.');
    }

    /**
     * @return array{type: string, name: string, content: string, ttl: int, proxied: bool}
     */
    private function validateRecord(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:2048'],
            // 1 is Cloudflare's "automatic"; anything else is seconds and has a floor of 60.
            'ttl' => ['required', 'integer', Rule::in([1, 60, 300, 1800, 3600, 86400])],
            'priority' => ['nullable', 'integer', 'between:0,65535'],
            'proxied' => ['sometimes', 'boolean'],
        ]);

        $record = [
            'type' => $data['type'],
            'name' => $data['name'],
            'content' => $data['content'],
            'ttl' => (int) $data['ttl'],
            // Only A, AAAA and CNAME can sit behind Cloudflare's proxy; sending the flag on
            // anything else is rejected by the API rather than ignored.
            'proxied' => in_array($data['type'], ['A', 'AAAA', 'CNAME'], true) && $request->boolean('proxied'),
        ];

        if ($data['type'] === 'MX') {
            $record['priority'] = (int) ($data['priority'] ?? 10);
        }

        return $record;
    }

    private function attempt(callable $callback, string $success): RedirectResponse
    {
        try {
            $callback();
        } catch (DnsCredentialException $exception) {
            return back()->withErrors(['dns' => $exception->getMessage()])->withInput();
        } catch (ConnectionException) {
            return back()->withErrors(['dns' => 'Could not reach Cloudflare, so nothing was changed.'])->withInput();
        }

        return back()->with('status', $success);
    }

    private function client(DnsAccount $account): CloudflareDns
    {
        return new CloudflareDns($account->credentials['token']);
    }

    private function authorizeAccount(Request $request, DnsAccount $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 404);
    }

    private function authorizeZone(Request $request, DnsZone $zone): void
    {
        abort_unless($zone->user_id === $request->user()->id, 404);
    }
}
