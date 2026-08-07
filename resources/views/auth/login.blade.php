@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-md px-5 py-20">
    <form method="POST" class="rounded-3xl border border-slate-200 bg-white p-8 dark:border-white/10 dark:bg-white/[.04]">
        @csrf
        <h1 class="text-2xl font-semibold">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Sign in to your infrastructure.</p>

        {{-- Finishing a password reset lands back here, and without this the confirmation
             that it worked was thrown away. --}}
        @if(session('status'))
            <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if($errors->any())<p class="mt-4 text-sm text-rose-600 dark:text-rose-300">{{ $errors->first() }}</p>@endif

        <label class="mt-7 block text-sm">Email<input name="email" value="{{ old('email') }}" type="email" required autofocus class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900"></label>
        <label class="mt-4 block text-sm">Password<input name="password" type="password" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900"></label>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <label class="flex gap-2 text-slate-500 dark:text-slate-400"><input type="checkbox" name="remember">Remember me</label>
            <a href="{{ route('password.request') }}" class="text-cyan-600 dark:text-cyan-300">Forgot your password?</a>
        </div>

        <button class="button-primary mt-6 w-full">Sign in</button>
        @include('auth.partials.google-button')
        <p class="mt-5 text-center text-sm text-slate-500 dark:text-slate-400">New here? <a href="{{ route('register') }}" class="text-cyan-600 dark:text-cyan-300">Create account</a></p>
    </form>
</div>
@endsection
