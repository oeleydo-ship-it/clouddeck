@extends('layouts.app')
@section('content')
<div class="app-main">
    <div class="min-w-0 max-w-[960px]">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="page-title">@yield('admin-title')</h1>
                <p class="page-subtitle !mt-1">@yield('admin-description')</p>
            </div>
            @yield('admin-actions')
        </div>
        @if($errors->any())
            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
                <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="mt-4">@yield('admin')</div>
    </div>
</div>
@endsection
