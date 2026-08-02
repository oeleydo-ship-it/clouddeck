{{-- Reverb pushes each log line as it happens. The poll is the fallback for when it
     cannot: no WebSocket server running, a blocked connection, or a dropped socket. Without
     it this page sat on "Waiting for a deployment worker" through an entire deployment and
     only told the truth when someone reloaded. It stops once the deployment settles. --}}
<div @if($active) wire:poll.2s @endif>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-medium capitalize {{ $deployment->status->value === 'successful' ? 'bg-emerald-50 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-300' : ($deployment->status->value === 'failed' ? 'bg-rose-50 dark:bg-rose-400/10 text-rose-600 dark:text-rose-300' : 'bg-cyan-50 dark:bg-cyan-400/10 text-cyan-600 dark:text-cyan-300') }}">{{ str_replace('_',' ',$deployment->status->value) }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $deployment->progress }}%</span>
            @if($active)<span class="flex items-center gap-1.5 text-xs text-cyan-600 dark:text-cyan-300"><span class="size-1.5 animate-pulse rounded-full bg-cyan-500"></span>Live</span>@endif
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">Exit {{ $deployment->exit_code ?? '—' }} · {{ $deployment->duration_for_humans ?? 'running' }}</p>
    </div>
    @if($active)
        <div class="mb-3 h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-blue-500 transition-all duration-500" style="width:{{ max(2, $deployment->progress) }}%"></div></div>
    @endif
    <div class="h-[34rem] overflow-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-900 dark:bg-black/60 p-5 font-mono text-xs leading-6" x-data x-init="$el.scrollTop=$el.scrollHeight" x-effect="$el.scrollTop=$el.scrollHeight">
        @forelse($logs as $log)<div class="grid grid-cols-[70px_1fr] gap-3 {{ $log->level === 'error' ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300' }}"><span class="select-none text-slate-600">{{ $log->created_at->format('H:i:s') }}</span><pre class="whitespace-pre-wrap break-words">{{ $log->output }}</pre></div>@empty<p class="text-slate-500 dark:text-slate-400">{{ $active ? 'Waiting for a deployment worker…' : 'This deployment recorded no output.' }}</p>@endforelse
    </div>
</div>
