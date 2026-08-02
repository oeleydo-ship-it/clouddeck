@extends('layouts.app')
@section('content')
@php use App\Enums\DeploymentStatus; @endphp
<div class="mx-auto max-w-6xl px-5 py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a class="text-sm text-cyan-600 dark:text-cyan-300" href="{{ route('sites.show',$deployment->site) }}">← {{ $deployment->site->domain }}</a>
            <h1 class="mt-2 text-3xl font-semibold">Deployment</h1>
            <p class="mt-2 font-mono text-sm text-slate-500 dark:text-slate-400">{{ $deployment->id }} · {{ $deployment->release ?? 'release pending' }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if(in_array($deployment->status, [DeploymentStatus::Pending, DeploymentStatus::Running], true))
                {{-- A deployment that never reaches a worker would otherwise block this site
                     from ever deploying again. --}}
                <form method="POST" action="{{ route('deployments.cancel',$deployment) }}" onsubmit="return confirm('Cancel this deployment? The site keeps running its current release.')">@csrf
                    <button class="button-secondary !text-rose-600 dark:!text-rose-300">Cancel deployment</button>
                </form>
            @else
                <form method="POST" action="{{ route('deployments.retry',$deployment) }}">@csrf
                    <button class="button-primary">Deploy again</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="mt-8">@livewire('deployment-log-stream',['deployment'=>$deployment])</div>
</div>
@endsection
