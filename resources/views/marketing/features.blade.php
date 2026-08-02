@extends('layouts.marketing')
@section('marketing')
<section class="mx-auto max-w-5xl px-5 py-20">
    <h1 class="text-4xl font-semibold heading">Features</h1>
    <p class="mt-5 text-lg muted">Everything needed to run Laravel in production, without assembling it yourself.</p>

    <div class="mt-12 grid gap-4 md:grid-cols-2">
        @foreach([
            ['Server provisioning', 'Create a droplet or import one you already run. Nginx, PHP 8.2 to 8.4, Redis, MySQL or PostgreSQL, and a firewall, installed and configured.'],
            ['Zero-downtime deployments', 'Each release is built in its own directory and switched in with an atomic symlink. Rollback restores the previous release instantly.'],
            ['Automatic certificates', "Let's Encrypt certificates issued per site, renewed before expiry, with HTTPS redirects."],
            ['Queue workers and Horizon', 'Supervisor-managed workers with process counts, retries, timeouts, and memory limits. Horizon and Reverb installed and kept across deployments.'],
            ['Scheduled tasks', 'Cron entries per site or per server, validated before they are written, with their sync status recorded.'],
            ['Managed databases', 'Create databases and users, attach them to a site, and have the connection details written into its environment automatically.'],
            ['Backups and snapshots', 'Scheduled database backups and provider snapshots, with restore.'],
            ['Monitoring and alerts', 'CPU, memory, disk, and load, with alert rules and notification channels.'],
            ['Remote management', 'An allowlisted console, a file manager, and PHP and Nginx settings, without opening SSH.'],
            ['Teams', 'Share servers with a team, with owner, admin, operator, and viewer roles.'],
        ] as [$title, $copy])
            <article class="panel"><h2 class="font-semibold heading">{{ $title }}</h2><p class="mt-2 text-sm muted">{{ $copy }}</p></article>
        @endforeach
    </div>

    <div class="mt-12 text-center">
        <a href="{{ route('register') }}" class="button-primary inline-block">Start deploying</a>
    </div>
</section>
@endsection
