@extends('layouts.authenticated')

@section('pageTitle', 'Tableau de bord')

@section('content')
    <div class="mb-7">
        <p class="text-sm font-medium text-slate-500">Vue d’ensemble</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Bonjour, {{ auth()->user()->name }}</h2>
        <p class="mt-2 text-sm text-slate-600">Suivez le volume de travail, les demandes en attente et les actions prioritaires sur les Prestataires affectés.</p>
    </div>

    @php
        $cards = [
            ['label' => 'Entreprises suivies', 'value' => $stats['companies'] ?? 0, 'tone' => 'blue', 'icon' => 'building'],
            ['label' => 'Employés permanents', 'value' => $stats['employees'] ?? 0, 'tone' => 'green', 'icon' => 'users'],
            ['label' => 'Engins suivis', 'value' => $stats['machinery'] ?? 0, 'tone' => 'amber', 'icon' => 'truck'],
            ['label' => 'Outillages suivis', 'value' => $stats['toolings'] ?? 0, 'tone' => 'blue', 'icon' => 'tool'],
        ];
    @endphp

    <section aria-label="Résumé des données" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            @include('shared.stat-card', ['label' => $card['label'], 'value' => $card['value'], 'tone' => $card['tone'], 'icon' => $card['icon']])
        @endforeach
    </section>

    <section class="mt-7 grid gap-4 xl:grid-cols-[1.3fr_0.7fr]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Éléments à traiter</h3>
                    <p class="mt-1 text-sm text-slate-500">Demandes et validations en attente sur les Prestataires accessibles.</p>
                </div>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ $stats['pending_total'] ?? 0 }} en attente</span>
            </div>

            @php
                $itemsToReview = $itemsToReview ?? collect();
            @endphp
            @if ($itemsToReview->isEmpty())
                <div class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                    Aucun élément à traiter pour le moment.
                </div>
            @else
                <ul class="mt-5 space-y-3">
                    @foreach ($itemsToReview as $item)
                        <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $item['title'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['label'] }}</p>
                            </div>
                            <a href="{{ route($item['route'], $item['route_parameters']) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-blue-700 px-3 text-xs font-semibold text-white hover:bg-blue-800">Examiner</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Flux de travail</h3>
            <div class="mt-5 space-y-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprises</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['pending_companies'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employés</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['pending_employees'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Engins</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['pending_machinery'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Outillages</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['pending_toolings'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </section>

    @if (! empty($recentNotifications))
        <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="recent-activity-title">
            <div class="mb-4">
                <h3 id="recent-activity-title" class="text-base font-semibold text-slate-900">Activités récentes</h3>
                <p class="mt-1 text-sm text-slate-500">Dernières notifications et événements visibles pour votre périmètre.</p>
            </div>
            <ul class="space-y-3">
                @foreach ($recentNotifications as $notification)
                    @php
                        $data = $notification->data ?? [];
                        $event = $data['event'] ?? 'notification';
                        $message = match ($event) {
                            'document_validated' => 'Document validé',
                            'employee_submitted' => 'Employé soumis',
                            'employee_validated' => 'Employé validé',
                            'employee_rejected' => 'Employé rejeté',
                            'machinery_submitted' => 'Engin soumis',
                            'machinery_validated' => 'Engin validé',
                            'tooling_submitted' => 'Outillage soumis',
                            default => 'Événement de validation',
                        };
                    @endphp
                    <li class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $message }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $notification->created_at?->diffForHumans() ?? 'À l’instant' }}</p>
                        </div>
                        <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Voir</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (count($shortcuts ?? []) > 0)
        <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="shortcuts-title">
            <div class="mb-4">
                <h3 id="shortcuts-title" class="text-base font-semibold text-slate-900">Accès rapides</h3>
                <p class="mt-1 text-sm text-slate-500">Accédez directement aux principaux espaces de suivi.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($shortcuts as $shortcut)
                    <a href="{{ route($shortcut['route']) }}" class="shortcut-card flex min-h-16 items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                        <span>{{ $shortcut['label'] }}</span>
                        <span class="text-blue-700" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
