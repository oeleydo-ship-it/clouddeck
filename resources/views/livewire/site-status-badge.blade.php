@php $tint = ['active' => 'emerald', 'failed' => 'rose'][$site->status] ?? 'amber'; @endphp
<span @if($pending) wire:poll.5s @endif
      @if($reload) x-init="setTimeout(() => window.location.reload(), 900)" @endif
      class="badge {{ $tint === 'emerald' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' : ($tint === 'rose' ? 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300') }} capitalize">
    <span class="badge-dot bg-{{ $tint }}-500 {{ $pending ? 'animate-pulse' : '' }}"></span>{{ $site->status }}
</span>
