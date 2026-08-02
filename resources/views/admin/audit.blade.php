@extends('layouts.admin')
@section('admin-title', 'Audit')
@section('admin-description', 'Immutable record of administrative actions.')
@section('admin')
<section class="panel"><h2 class="font-semibold">Recent administrative audit</h2><div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">@foreach($auditLogs as $log)<div class="grid gap-2 py-3 text-sm sm:grid-cols-[180px_1fr_180px]"><span>{{ $log->actor?->email ?? 'system' }}</span><span>{{ $log->action }} @if($log->auditable_type)/ {{ class_basename($log->auditable_type) }} {{ $log->auditable_id }}@endif</span><span class="text-slate-500 dark:text-slate-400">{{ $log->created_at->diffForHumans() }} / {{ $log->ip_address }}</span></div>@endforeach</div></section>
@endsection
