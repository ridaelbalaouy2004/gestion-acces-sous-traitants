@php
    $tones = [
        'blue' => ['icon' => 'bg-blue-50 text-blue-700', 'label' => 'text-blue-700'],
        'orange' => ['icon' => 'bg-orange-50 text-orange-700', 'label' => 'text-orange-700'],
        'amber' => ['icon' => 'bg-amber-50 text-amber-700', 'label' => 'text-amber-700'],
        'green' => ['icon' => 'bg-emerald-50 text-emerald-700', 'label' => 'text-emerald-700'],
        'red' => ['icon' => 'bg-red-50 text-red-700', 'label' => 'text-red-700'],
        'slate' => ['icon' => 'bg-slate-100 text-slate-600', 'label' => 'text-slate-600'],
    ];
    $toneClasses = $tones[$tone] ?? $tones['slate'];
@endphp

<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($value, 0, ',', ' ') }}</p>
        </div>
        <span class="{{ $toneClasses['icon'] }} flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" aria-hidden="true">
            @switch($icon)
                @case('documents')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7M8.5 15.5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    @break
                @case('clock')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @break
                @case('check')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="m8.5 12 2.25 2.25 4.75-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @break
                @case('x')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="m9 9 6 6m0-6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    @break
                @case('users')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8m1 2.2a5 5 0 0 1 3.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    @break
                @case('building')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M4 20V5.5A1.5 1.5 0 0 1 5.5 4H15v16M15 9h3.5A1.5 1.5 0 0 1 20 10.5V20M2.5 20h19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 8h3m-3 4h3m-3 4h3m7-3h.01m0 3h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    @break
                @case('truck')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="18" r="1.75" stroke="currentColor" stroke-width="1.7"/><circle cx="17.5" cy="18" r="1.75" stroke="currentColor" stroke-width="1.7"/></svg>
                    @break
                @case('tool')
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M14.5 6.5a5 5 0 0 0 6 6L12 21l-5-5 8.5-8.5ZM5 4l4 4M3 8l5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @break
            @endswitch
        </span>
    </div>
    <p class="mt-4 text-xs font-medium {{ $toneClasses['label'] }}">Vue actualisée à l’ouverture</p>
</article>
