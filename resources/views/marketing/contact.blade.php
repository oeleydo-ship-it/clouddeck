@extends('layouts.marketing')
@section('marketing')
@php $support = app(\App\Services\SystemSettings::class)->get('support_email'); @endphp
<section class="mx-auto max-w-2xl px-5 py-20">
    <h1 class="text-4xl font-semibold heading">Contact us</h1>
    <p class="mt-5 text-lg muted">Questions about deploying, pricing, or migrating an existing server — send them here.</p>
    @if($support)<p class="mt-2 text-sm muted">Or email <a class="text-cyan-600 dark:text-cyan-300" href="mailto:{{ $support }}">{{ $support }}</a>.</p>@endif

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contact.submit') }}" class="panel mt-8">@csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm heading">Your name<input class="field" name="name" value="{{ old('name') }}" required maxlength="120"></label>
            <label class="text-sm heading">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required></label>
        </div>
        <label class="mt-4 block text-sm heading">Subject<input class="field" name="subject" value="{{ old('subject') }}" maxlength="160" placeholder="Optional"></label>
        <label class="mt-4 block text-sm heading">Message<textarea class="field min-h-40" name="body" required maxlength="5000">{{ old('body') }}</textarea></label>
        <button class="button-primary mt-5">Send message</button>
    </form>
</section>
@endsection
