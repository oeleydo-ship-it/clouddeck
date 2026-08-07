<?php

namespace App\Jobs\Operations;

use App\Models\FirewallRule;
use App\Models\SecurityIncident;
use App\Ssh\SshClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SyncFirewallRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $firewallRuleId) {}

    public function handle(SshClient $ssh): void
    {
        $rule = FirewallRule::with('server.sshKey')->withTrashed()->findOrFail($this->firewallRuleId);
        $server = $rule->server;
        $removing = $rule->trashed();

        $output = $ssh->runScript($server, resource_path('scripts/sync-firewall.sh'), [
            'ACTION' => $removing ? 'remove' : 'apply',
            'TYPE' => $rule->type,
            'PROTOCOL' => $rule->protocol,
            'PORT' => (string) ($rule->port ?? ''),
            'FROM_IP' => (string) ($rule->from_ip ?? ''),
            'COMMENT' => $rule->ufwComment(),
        ]);

        if (str_contains($output, 'UFW_NOT_INSTALLED')) {
            $message = 'UFW is not installed on this server. Install and enable UFW before applying firewall rules.';
            $this->markUfwMissing($rule, $server, $message);

            throw new RuntimeException($message);
        }

        if (str_contains($output, 'EMPTY_RULE') || str_contains($output, 'NAMED_PORT_WITH_FROM_UNSUPPORTED')) {
            $message = 'The firewall rule could not be applied with the given port and source combination.';
            if (! $removing) {
                $rule->update(['status' => 'failed', 'status_message' => $message]);
            }

            throw new RuntimeException($message);
        }

        if (! $removing) {
            $rule->update([
                'status' => 'synced',
                'status_message' => null,
            ]);
        }
        SecurityIncident::where('firewall_rule_id', $rule->id)->update([
            'mitigation_status' => $removing ? 'removed' : 'active',
        ]);

        $server->update([
            'firewall_status' => 'ok',
            'firewall_message' => null,
            'firewall_synced_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $rule = FirewallRule::withTrashed()->find($this->firewallRuleId);
        if (! $rule || $rule->trashed()) {
            return;
        }

        if ($rule->status === 'failed' && filled($rule->status_message)) {
            return;
        }

        $rule->update([
            'status' => 'failed',
            'status_message' => $this->shortMessage($e->getMessage()),
        ]);
        SecurityIncident::where('firewall_rule_id', $rule->id)->update(['mitigation_status' => 'failed']);
    }

    private function markUfwMissing(FirewallRule $rule, $server, string $message): void
    {
        $short = $this->shortMessage($message);

        if (! $rule->trashed()) {
            $rule->update([
                'status' => 'failed',
                'status_message' => $short,
            ]);
        }

        $server->update([
            'firewall_status' => 'missing_ufw',
            'firewall_message' => $short,
        ]);
    }

    private function shortMessage(string $message): string
    {
        $message = trim($message);

        if (str_contains($message, 'UFW_NOT_INSTALLED') || str_contains(strtolower($message), 'ufw is not installed')) {
            return 'UFW is not installed on this server. Install and enable UFW before applying firewall rules.';
        }

        return Str::limit($message, 500);
    }
}
