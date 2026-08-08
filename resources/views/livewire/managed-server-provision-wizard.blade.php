<div class="app-main !max-w-4xl">
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <div class="flex items-center gap-2">
                <p class="page-eyebrow">Managed infrastructure</p>
                <span class="badge bg-cyan-50 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">Platform hosted</span>
            </div>
            <h1 class="page-title">Provision a managed server</h1>
            <p class="mt-2 max-w-xl text-sm muted">{{ $platform }} creates and bills this VPS for you — no cloud provider account required. Bring-your-own servers stay on Providers / Add existing.</p>
        </div>
        <span class="shrink-0 text-sm font-medium text-slate-500 dark:text-slate-400">Step {{ $step }} of 4</span>
    </div>

    <div class="mb-8 grid grid-cols-4 gap-2">@for($i=1;$i<=4;$i++)<div class="h-1.5 rounded-full transition-colors {{ $i <= $step ? 'bg-cyan-400' : 'bg-slate-100 dark:bg-white/10' }}"></div>@endfor</div>

    @if($catalogError)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
            <span>{{ $catalogError }}</span>
        </div>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:border-white/10 dark:bg-white/[.04] sm:p-8" wire:loading.class="opacity-60">
        @if($step === 1)
            <div class="flex items-center gap-3">
                <span class="stat-icon bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6 6h.01M6 18h.01"/></svg></span>
                <div>
                    <h2 class="text-xl font-semibold heading">Server configuration</h2>
                    <p class="mt-0.5 text-sm muted">Sizes and regions come from the platform cloud account.</p>
                </div>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <label class="text-sm heading">Region
                    <select wire:model="region" class="field">
                        <option value="">Select region</option>
                        @foreach($regions as $item)<option value="{{ $item['slug'] }}">{{ $item['name'] }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm heading">Size
                    <select wire:model="size" class="field">
                        <option value="">Select size</option>
                        @foreach($sizes as $item)<option value="{{ $item['slug'] }}">{{ $item['vcpus'] }} vCPU · {{ round($item['memory']/1024,1) }} GB · ${{ number_format(app(\App\Services\SystemSettings::class)->managedServerPrice($item), 2) }}/mo</option>@endforeach
                    </select>
                </label>
                <label class="text-sm heading sm:col-span-2">Ubuntu image
                    <select wire:model="image" class="field">
                        @foreach($images as $item)<option value="{{ $item['slug'] }}">{{ $item['name'] }}</option>@endforeach
                    </select>
                </label>
            </div>
        @elseif($step === 2)
            <div class="flex items-center gap-3">
                <span class="stat-icon bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M15 7a4 4 0 1 1-4 4M2.5 21.5 8 16m-2-2 5.5-5.5"/></svg></span>
                <div>
                    <h2 class="text-xl font-semibold heading">SSH key</h2>
                    <p class="mt-0.5 text-sm muted">A managed key is created for you so provisioning workers can bootstrap the host.</p>
                </div>
            </div>
            <div class="mt-6 grid gap-3">
                @forelse($keys as $key)
                    <label class="flex cursor-pointer items-center gap-4 rounded-2xl border p-4 transition-colors {{ $sshKeyId === $key->id ? 'border-cyan-400 bg-cyan-50 dark:bg-cyan-400/10' : 'border-slate-200 hover:border-slate-300 dark:border-white/10 dark:hover:border-white/20' }}">
                        <input type="radio" wire:model="sshKeyId" value="{{ $key->id }}">
                        <span><b class="heading">{{ $key->name }}</b><small class="block font-mono text-slate-500 dark:text-slate-400">{{ $key->fingerprint }}</small></span>
                    </label>
                @empty
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">Generate a key under <a class="underline" href="{{ route('ssh-keys') }}">SSH keys</a>.</p>
                @endforelse
            </div>
        @elseif($step === 3)
            <div class="flex items-center gap-3">
                <span class="stat-icon bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5ZM4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3ZM8 7h.01M8 18h.01"/></svg></span>
                <div>
                    <h2 class="text-xl font-semibold heading">Identify your server</h2>
                    <p class="mt-0.5 text-sm muted">Used across the dashboard and in DNS suggestions.</p>
                </div>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <label class="text-sm heading">Display name<input wire:model="name" class="field" placeholder="Production API"></label>
                <label class="text-sm heading">Hostname<input wire:model="hostname" class="field" placeholder="app-server-01"></label>
            </div>
        @else
            <div class="flex items-center gap-3">
                <span class="stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M20 6 9 17l-5-5"/></svg></span>
                <div>
                    <h2 class="text-xl font-semibold heading">Review and deploy</h2>
                    <p class="mt-0.5 text-sm muted">Double-check the specs below, then deploy.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                <dl class="grid gap-4 rounded-2xl border border-slate-200 p-5 dark:border-white/10 sm:grid-cols-2 lg:col-span-2">
                    @foreach([
                        'Name' => $name,
                        'Hostname' => $hostname,
                        'Region' => $selectedRegion['name'] ?? $region,
                        'Size' => $selectedSize ? $selectedSize['vcpus'].' vCPU · '.round($selectedSize['memory']/1024,1).' GB · '.round(($selectedSize['disk'] ?? 0)).' GB disk' : $size,
                        'Image' => $selectedImage['name'] ?? $image,
                        'SSH key' => $selectedKey->name ?? '—',
                    ] as $label => $value)
                        <div><dt class="text-xs uppercase tracking-wide muted">{{ $label }}</dt><dd class="mt-1 font-medium heading">{{ $value }}</dd></div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide muted">Type</dt>
                        <dd class="mt-1"><span class="badge bg-cyan-50 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">Managed (platform cloud)</span></dd>
                    </div>
                </dl>

                <div class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 dark:border-cyan-400/20 dark:bg-cyan-400/[.06]">
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Monthly price</p>
                    <p class="mt-2 text-2xl font-semibold heading">${{ number_format($customerPrice ?? 0, 2) }}<span class="text-sm font-normal muted">/mo</span></p>
                    <p class="mt-1 text-xs muted">Priced for this configuration by {{ $platform }}.</p>
                    <ul class="mt-4 space-y-1.5 text-xs muted">
                        <li class="flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3 shrink-0 text-emerald-500"><path d="M20 6 9 17l-5-5"/></svg>No cloud account setup</li>
                        <li class="flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3 shrink-0 text-emerald-500"><path d="M20 6 9 17l-5-5"/></svg>Counts toward managed servers, not BYOS</li>
                        <li class="flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="size-3 shrink-0 text-emerald-500"><path d="M20 6 9 17l-5-5"/></svg>Provisions in the background</li>
                    </ul>
                </div>
            </div>
        @endif

        @foreach(['region','size','image','sshKeyId','name','hostname','quota'] as $field)
            @error($field)<p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
        @endforeach

        <div class="mt-8 flex justify-between">
            @if($step>1)<button type="button" wire:click="back" class="button-secondary">Back</button>@else<span></span>@endif
            @if($step<4)
                <button type="button" wire:click="next" wire:loading.attr="disabled" class="button-primary" @disabled($catalogError !== '')>Continue</button>
            @else
                <button type="button" wire:click="deploy" wire:loading.attr="disabled" class="button-primary" @disabled($catalogError !== '')>Deploy managed server</button>
            @endif
        </div>
    </section>
</div>
