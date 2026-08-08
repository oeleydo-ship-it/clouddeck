@extends('layouts.app')
@section('content')
@php
    use App\Enums\DeploymentStatus;
    $active = in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Running], true);
@endphp
<div class="app-main !max-w-6xl"
     x-data="{ active: {{ $active ? 'true' : 'false' }} }"
     @deployment-settled.window="active = false">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a class="page-eyebrow" href="{{ route('sites.show',$deployment->site) }}">← {{ $deployment->site->domain }}</a>
            <h1 class="mt-2 text-3xl font-semibold">Deployment</h1>
            <p class="mt-2 font-mono text-sm text-slate-500 dark:text-slate-400">{{ $deployment->id }} · {{ $deployment->release ?? 'release pending' }}</p>
        </div>

        {{-- Actions also live inside the Livewire stream so they update over Reverb/poll;
             this header copy is the SSR fallback for the first paint. --}}
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('deployments.cancel',$deployment) }}" x-show="active" @if(! $active) style="display: none" @endif onsubmit="return confirm('Cancel this deployment? The site keeps running its current release.')">@csrf
                <button class="button-secondary !text-rose-600 dark:!text-rose-300">Cancel deployment</button>
            </form>
            <form method="POST" action="{{ route('deployments.retry',$deployment) }}" x-show="!active" @if($active) style="display: none" @endif>@csrf
                <button class="button-primary">Deploy again</button>
            </form>
        </div>
    </div>

    {{-- Session flash for "Deployment queued." is shown by the layout; hide it once the
         run settles so a successful deploy does not keep advertising a queue state. --}}
    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="mt-8">@livewire('deployment-log-stream',['deployment'=>$deployment])</div>
</div>
@endsection
