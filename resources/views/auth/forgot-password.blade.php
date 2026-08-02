@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-md px-5 py-20">
    <form method="POST" class="panel p-8">
        @csrf
        <h1 class="text-2xl font-semibold">Reset your password</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">We will email you a secure, expiring reset link.</p>

        {{-- The controller answers with a status on success; without this the form looked
             like it had done nothing at all. --}}
        @if(session('status'))
            <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @error('email')<p class="mt-4 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror

        <label class="mt-6 block text-sm">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        <button class="button-primary mt-6 w-full">Email reset link</button>
        <p class="mt-5 text-center text-sm text-slate-500 dark:text-slate-400"><a href="{{ route('login') }}" class="text-cyan-600 dark:text-cyan-300">Back to sign in</a></p>
    </form>
</div>
@endsection
