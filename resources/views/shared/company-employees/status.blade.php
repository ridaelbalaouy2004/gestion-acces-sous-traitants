@php
    [$statusLabel, $statusClasses] = match ($status) {
        'draft' => ['Brouillon', 'bg-slate-100 text-slate-700'],
        'pending' => ['En attente', 'bg-orange-100 text-orange-900'],
        'validated' => ['Validé', 'bg-emerald-100 text-emerald-900'],
        'rejected' => ['Rejeté', 'bg-red-100 text-red-900'],
        default => ['Inconnu', 'bg-slate-100 text-slate-700'],
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
