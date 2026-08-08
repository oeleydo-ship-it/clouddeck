@extends('layouts.app')
@section('content')
{{-- Cancel / Deploy again live only in the Livewire log stream so they stay in sync with
     Reverb/poll status and are not painted twice (header + stream). --}}
<div class="app-main !max-w-6xl">
    <div>
        <a class="page-eyebrow" href="{{ route('sites.show',$deployment->site) }}">← {{ $deployment->site->domain }}</a>
        <h1 class="mt-2 text-3xl font-semibold">Deployment</h1>
        <p class="mt-2 font-mono text-sm text-slate-500 dark:text-slate-400">{{ $deployment->id }} · {{ $deployment->release ?? 'release pending' }}</p>
    </div>

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="mt-8">
        @livewire('deployment-log-stream', ['deployment' => $deployment], key('deployment-log-'.$deployment->id))
    </div>
</div>
@endsection
