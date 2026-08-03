{{-- Polls only while a read is outstanding: a log that has already been fetched is a
     snapshot, and refreshing it on a timer would fight with reading it. --}}
<div @if($running) wire:poll.2s @endif>
    <section class="panel">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-semibold heading">Logs</h2>
                <p class="mt-1 text-sm muted">Read from the server on request. Nothing is stored on it.</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-sm heading">Lines<input type="number" wire:model="lines" min="20" max="2000" step="20" class="field w-28"></label>
                <button wire:click="read" wire:loading.attr="disabled" class="button-primary shrink-0">
                    <span wire:loading.remove wire:target="read">Read log</span>
                    <span wire:loading wire:target="read">Reading…</span>
                </button>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            @foreach($sources as $key => $label)
                <button wire:click="$set('source', '{{ $key }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm transition',
                        'bg-slate-900 font-medium text-white dark:bg-white dark:text-slate-900' => $source === $key,
                        'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300 dark:hover:bg-white/20' => $source !== $key,
                    ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($snapshot)
            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs muted">
                <span>{{ ucfirst($snapshot->status) }}</span>
                @if($snapshot->path)<span class="font-mono">{{ $snapshot->path }}</span>@endif
                <span>Read {{ $snapshot->created_at->diffForHumans() }}</span>
            </div>
        @endif

        <pre class="mt-4 max-h-[32rem] overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-relaxed text-slate-200">@if(! $snapshot)Choose a log and press Read log.@elseif($running)Reading {{ $sources[$snapshot->source] ?? $snapshot->source }} from the server…@elseif($snapshot->status === 'failed')<span class="text-rose-300">{{ $snapshot->output ?: 'The read failed.' }}</span>@else{{ $snapshot->output ?: 'The log is empty.' }}@endif</pre>
    </section>
</div>
