@extends('layouts.authenticated')

@section('pageTitle', 'Tableau de bord')

@section('content')
    <div class="mb-7">
        <p class="text-sm font-medium text-slate-500">Vue d’ensemble</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Bonjour, {{ auth()->user()->name }}</h2>
        <p class="mt-2 text-sm text-slate-600">Retrouvez les demandes d’accès et leur état de traitement.</p>
    </div>

    <section aria-label="Résumé des documents" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('shared.stat-card', ['label' => 'Total des documents', 'value' => $stats['total'], 'tone' => 'blue', 'icon' => 'documents'])
        @include('shared.stat-card', ['label' => 'En attente', 'value' => $stats['pending'], 'tone' => 'orange', 'icon' => 'clock'])
        @include('shared.stat-card', ['label' => 'Validés', 'value' => $stats['validated'], 'tone' => 'green', 'icon' => 'check'])
        @include('shared.stat-card', ['label' => 'Rejetés', 'value' => $stats['rejected'], 'tone' => 'red', 'icon' => 'x'])
    </section>

    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="shortcuts-title">
        <div class="mb-4">
            <h3 id="shortcuts-title" class="text-base font-semibold text-slate-900">Accès rapides</h3>
            <p class="mt-1 text-sm text-slate-500">Accédez directement aux principaux espaces de suivi.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['label' => 'Tous les documents', 'route' => 'gestionnaire.documents.index', 'tone' => 'blue'],
                ['label' => 'À examiner', 'route' => 'gestionnaire.documents.pending', 'tone' => 'orange'],
                ['label' => 'Documents validés', 'route' => 'gestionnaire.documents.validated', 'tone' => 'green'],
            ] as $shortcut)
                <a href="{{ route($shortcut['route']) }}" class="shortcut-card flex min-h-16 items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                    <span>{{ $shortcut['label'] }}</span>
                    <span class="text-blue-700" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-activity-title">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <h3 id="recent-activity-title" class="text-base font-semibold text-slate-900">Soumissions récentes</h3>
            <p class="mt-1 text-sm text-slate-500">Derniers documents transmis pour examen.</p>
        </div>
        @include('shared.document-list', [
            'documents' => $documents,
            'showPrestataire' => true,
            'dateColumn' => 'submitted_at',
            'emptyTitle' => 'Aucune soumission récente',
            'emptyMessage' => 'Les nouveaux documents transmis apparaîtront ici.',
        ])
    </section>
@endsection
