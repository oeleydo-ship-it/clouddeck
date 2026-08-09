@extends('layouts.app')
@section('content')
<div class="app-main">
    <div class="mx-auto max-w-lg py-10 text-center sm:py-16">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-7"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </span>
        <p class="page-eyebrow mt-6">Plan upgrade</p>
        <h1 class="page-title mt-2">{{ $label }} isn’t on your plan</h1>
        <p class="page-subtitle mx-auto mt-3 max-w-md">Your current subscription doesn’t include this module. Subscribe or upgrade to unlock it — quotas and features for each plan are listed on Billing.</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('billing.index') }}" class="button-primary">View plans &amp; upgrade</a>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="button-secondary">Go back</a>
        </div>
    </div>
</div>
@endsection
