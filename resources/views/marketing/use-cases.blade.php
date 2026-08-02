@extends('layouts.marketing')
@section('marketing')
<section class="mx-auto max-w-4xl px-5 py-20">
    <h1 class="text-4xl font-semibold heading">Use cases</h1>
    <p class="mt-5 text-lg muted">Who runs on this, and what it replaces.</p>

    <div class="mt-12 space-y-6">
        @foreach([
            ['Agencies running client sites', 'Dozens of Laravel applications across a handful of servers, each with its own domain, certificate, database, and deployment branch — separated per client, operated from one place.'],
            ['Solo developers and small teams', 'The parts of DevOps you would otherwise learn under deadline: provisioning, TLS, workers, backups. Bring a provider account and deploy on the same afternoon.'],
            ['SaaS products', 'Zero-downtime releases with instant rollback, queue workers under Supervisor, Horizon for visibility, and Reverb for WebSockets, all kept across deployments.'],
            ['Staging and demo environments', 'The same application on a second server, on its own subdomain and branch, torn down and rebuilt whenever it suits.'],
            ['Taking over an existing server', 'Import a droplet you already run rather than rebuilding it, and manage it from then on.'],
        ] as [$title, $copy])
            <article class="panel"><h2 class="text-xl font-semibold heading">{{ $title }}</h2><p class="mt-3 muted">{{ $copy }}</p></article>
        @endforeach
    </div>
</section>
@endsection
