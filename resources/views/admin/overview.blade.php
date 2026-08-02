@extends('layouts.admin')
@section('admin-title', 'Overview')
@section('admin-description', 'Customer access, plans, entitlements, billing review, and immutable audit history.')
@section('admin')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Users', $stats['users'], 'admin.users'],
            ['Suspended', $stats['suspended'], 'admin.users'],
            ['Active subscriptions', $stats['subscriptions'], 'admin.plans'],
            ['Billing requests', $stats['billing_requests'], 'admin.billing'],
        ] as [$label, $value, $route])
            <a href="{{ route($route) }}" class="panel p-5 transition hover:border-amber-300 dark:hover:border-amber-400/30">
                <p class="text-sm muted">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold heading">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <section class="panel mt-6">
        <h2 class="font-semibold heading">Recent administrative activity</h2>
        <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
            @forelse($auditLogs->take(10) as $log)
                <div class="grid gap-2 py-3 text-sm sm:grid-cols-[200px_1fr_160px]">
                    <span class="truncate heading">{{ $log->actor?->email ?? 'system' }}</span>
                    <span class="truncate muted">{{ $log->action }}@if($log->auditable_type) · {{ class_basename($log->auditable_type) }}@endif</span>
                    <span class="text-right muted">{{ $log->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm muted">Nothing recorded yet.</p>
            @endforelse
        </div>
        <a href="{{ route('admin.audit') }}" class="mt-4 inline-block text-sm font-medium text-cyan-600 dark:text-cyan-300">View the full audit trail →</a>
    </section>
@endsection
