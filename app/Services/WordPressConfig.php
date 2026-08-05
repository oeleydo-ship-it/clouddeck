<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Str;

final class WordPressConfig
{
    /**
     * WordPress signs cookies with these. Regenerating them on every deployment would log
     * every user out each time the site is released, so they are stored with the site and
     * created once, exactly like the application key on a Laravel site.
     */
    private const SALTS = [
        'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
        'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
    ];

    public function ensureSalts(Site $site): void
    {
        $existing = $site->environmentVariables()->whereIn('key', array_map(fn ($salt) => 'WP_'.$salt, self::SALTS))->pluck('key');

        foreach (self::SALTS as $salt) {
            if ($existing->contains('WP_'.$salt)) {
                continue;
            }

            $site->environmentVariables()->create([
                'key' => 'WP_'.$salt,
                'value' => Str::random(64),
                'is_secret' => true,
            ]);
        }
    }

    /**
     * Built here rather than on the server so the credentials never appear in a shell
     * command, and so the file is identical every deployment.
     */
    public function render(Site $site): string
    {
        $this->ensureSalts($site);
        $env = $site->environmentVariables()->pluck('value', 'key');

        $lines = ['<?php', ''];
        $lines[] = "/* Written by Uplary. Edit the site's environment rather than this file: */";
        $lines[] = '/* it is regenerated on every deployment. */';
        $lines[] = '';

        foreach ([
            'DB_NAME' => $env['DB_DATABASE'] ?? '',
            'DB_USER' => $env['DB_USERNAME'] ?? '',
            'DB_PASSWORD' => $env['DB_PASSWORD'] ?? '',
            'DB_HOST' => ($env['DB_HOST'] ?? '127.0.0.1').':'.($env['DB_PORT'] ?? '3306'),
            'DB_CHARSET' => 'utf8mb4',
        ] as $key => $value) {
            $lines[] = "define('{$key}', ".var_export((string) $value, true).');';
        }

        $lines[] = '';
        foreach (self::SALTS as $salt) {
            $lines[] = "define('{$salt}', ".var_export((string) ($env['WP_'.$salt] ?? ''), true).');';
        }

        $lines[] = '';
        // Behind Nginx terminating TLS, WordPress otherwise builds http:// URLs and loops
        // redirecting when the site is served over https.
        $lines[] = "if ((\$_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') { \$_SERVER['HTTPS'] = 'on'; }";
        $lines[] = '';
        // The scheme follows the request rather than being fixed at deployment. Hard-coding
        // https sent every visitor to a certificate that does not exist until one is issued,
        // which leaves the browser waiting on nothing; hard-coding http would send them back
        // out of TLS the moment a certificate arrives. The host stays fixed, so a forged Host
        // header cannot make WordPress build links to somewhere else.
        $lines[] = "\$clouddeck_scheme = (! empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';";
        $lines[] = "define('WP_HOME', \$clouddeck_scheme . '://' . ".var_export($site->domain, true).');';
        $lines[] = "define('WP_SITEURL', \$clouddeck_scheme . '://' . ".var_export($site->domain, true).');';
        $lines[] = "define('WP_DEBUG', false);";
        // Updating core in place would be overwritten by the next deployment, and could
        // leave the release directory writable by the web server.
        $lines[] = "define('DISALLOW_FILE_MODS', true);";
        $lines[] = "define('AUTOMATIC_UPDATER_DISABLED', true);";
        $lines[] = '';
        $lines[] = "\$table_prefix = 'wp_';";
        $lines[] = '';
        $lines[] = "if (! defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }";
        $lines[] = "require_once ABSPATH . 'wp-settings.php';";

        return implode("\n", $lines)."\n";
    }
}
