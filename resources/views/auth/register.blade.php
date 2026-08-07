@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-md px-5 py-16">
    <form method="POST" class="rounded-3xl border border-slate-200 bg-white p-8 dark:border-white/10 dark:bg-white/[.04]">
        @csrf
        <h1 class="text-2xl font-semibold">Create your account</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Deploy and operate your servers from one place.</p>
        @if($errors->any())
            <p class="mt-4 text-sm text-rose-600 dark:text-rose-300">{{ $errors->first() }}</p>
        @endif
        @foreach([['name','Name','text'],['email','Email','email'],['password','Password','password'],['password_confirmation','Confirm password','password']] as [$name,$label,$type])
            <label class="mt-4 block text-sm">{{ $label }}<input name="{{ $name }}" type="{{ $type }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900"></label>
        @endforeach
        <button class="button-primary mt-6 w-full">Create account</button>
        @include('auth.partials.google-button')
        <p class="mt-5 text-center text-sm text-slate-500 dark:text-slate-400">Already have an account? <a href="{{ route('login') }}" class="text-cyan-600 dark:text-cyan-300">Sign in</a></p>
    </form>
</div>
@endsection
