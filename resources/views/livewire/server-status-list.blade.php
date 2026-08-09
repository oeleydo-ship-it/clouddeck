{{-- Broadcasts drive the updates; the poll is a fallback so a dropped WebSocket still --}}
{{-- finishes the story instead of freezing the bar mid-provision. --}}
<div @if($active) wire:poll.5s @endif>
    @php
        $tints = [
            'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
            'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
            'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
        ];
    @endphp
    @forelse($servers as $server)
        @php $tint = match($server->status->value) { 'ready','active' => 'emerald', 'failed' => 'rose', default => 'amber' }; @endphp
        <div class="data-row grid items-center gap-4 lg:grid-cols-[1.4fr_1.4fr_1fr_190px_140px]" wire:key="server-{{ $server->id }}">
            <a href="{{ route('servers.manage',$server) }}" class="flex min-w-0 items-center gap-3">
                <span class="stat-icon shrink-0 {{ $tint === 'emerald' ? 'bg-blue-50 text-[#0058bc] dark:bg-blue-400/10 dark:text-blue-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/></svg>
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold heading">{{ $server->name }}</span>
                    <span class="mt-0.5 block truncate font-mono text-xs muted">{{ $server->public_ip ?? $server->hostname }}</span>
                    @if($server->team)<span class="badge badge-neutral mt-1">{{ $server->team->name }}</span>@endif
                </span>
            </a>
            <div class="min-w-0 text-sm">
                <p class="truncate muted">{{ $server->region }} / {{ $server->size }}</p>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    <span class="badge badge-neutral !text-[10px] uppercase tracking-wide">{{ $server->sites->count() }} {{ Str::plural('site', $server->sites->count()) }}</span>
                    {{-- PHP lives on the site, not the host, so this only claims a version
                         when the sites on this server agree on one. --}}
                    @php $phpVersions = $server->sites->pluck('php_version')->unique(); @endphp
                    @if($phpVersions->count() === 1)<span class="badge badge-neutral !text-[10px] uppercase tracking-wide">PHP {{ $phpVersions->first() }}</span>@endif
                </div>
            </div>
            @php
                $resources = [
                    ['label' => 'CPU', 'value' => $server->latestMetric?->cpu_percent],
                    ['label' => 'RAM', 'value' => $server->latestMetric?->memory_percent],
                    ['label' => 'Disk', 'value' => $server->latestMetric?->disk_percent],
                ];
            @endphp
            <div class="space-y-2">
                @foreach($resources as $resource)
                    <div>
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-semibold muted">{{ $resource['label'] }}</span>
                            <span class="tnum {{ $resource['value'] === null ? 'muted' : 'font-semibold heading' }}">{{ $resource['value'] === null ? '—' : $resource['value'].'%' }}</span>
                        </div>
                        <div class="meter mt-1"><span class="meter-fill {{ ($resource['value'] ?? 0) >= 90 ? '!bg-rose-500' : '' }}" style="width:{{ min(100, max(0, (float) ($resource['value'] ?? 0))) }}%"></span></div>
                    </div>
                @endforeach
            </div>
            <div>
                <div class="flex items-center justify-between text-xs"><span class="badge {{ $tints[$tint] }} capitalize"><span class="badge-dot bg-{{ $tint }}-500"></span>{{ $server->status->value }}</span><span class="muted">{{ $server->progress }}%</span></div>
                <div class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full transition-all duration-500 {{ $tint === 'rose' ? 'bg-rose-500' : 'bg-gradient-to-r from-cyan-400 to-blue-500' }}" style="width:{{ $server->progress }}%"></div></div>
                @if($server->current_step && ! in_array($server->status->value, ['ready','failed'], true))<p class="mt-1 truncate text-xs muted">{{ $server->current_step }}</p>@endif
                @if($server->status->value === 'failed' && $server->failure_reason)<p class="mt-1 truncate text-xs text-rose-600 dark:text-rose-300" title="{{ $server->failure_reason }}">{{ Str::limit($server->failure_reason, 60) }}</p>@endif
            </div>
            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('servers.manage',$server) }}" title="Manage server" class="icon-button !border-cyan-200 !bg-cyan-50 !text-cyan-600 dark:!border-cyan-400/20 dark:!bg-cyan-400/10 dark:!text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></a>
                <button type="button" wire:click="refresh" title="Refresh" class="icon-button"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/></svg></button>
                <a href="{{ route('servers.manage',$server) }}#danger-zone" title="Delete server" class="icon-button hover:!border-rose-200 hover:!text-rose-600 dark:hover:!border-rose-400/30 dark:hover:!text-rose-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z"/></svg></a>
            </div>
        </div>
    @empty
        <div class="px-6 py-16 text-center">
            <p class="font-medium heading">No servers yet</p>
            <p class="mt-1 text-sm muted">Provision a new host, or import a Droplet you already run.</p>
            <div class="mt-5 inline-flex justify-center">
                @include('servers.partials.provision-menu', ['buttonClass' => 'button-primary'])
            </div>
        </div>
    @endforelse
</div>
