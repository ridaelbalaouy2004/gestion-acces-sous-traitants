@php
    $statuses = [
        'pending' => ['label' => 'En attente', 'class' => 'bg-orange-50 text-orange-800 ring-orange-600/20'],
        'validated' => ['label' => 'Validé', 'class' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20'],
        'rejected' => ['label' => 'Rejeté', 'class' => 'bg-red-50 text-red-800 ring-red-600/20'],
    ];
    $badge = $statuses[$status] ?? ['label' => 'Inconnu', 'class' => 'bg-slate-100 text-slate-700 ring-slate-600/20'];
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge['class'] }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $badge['label'] }}
</span>
