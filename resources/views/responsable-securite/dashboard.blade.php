@extends('layouts.authenticated')

@section('pageTitle', 'Tableau de bord')

@section('content')
    <div class="mb-7">
        <p class="text-sm font-medium text-slate-500">Consultation sécurité</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Bonjour, {{ auth()->user()->name }}</h2>
        <p class="mt-2 text-sm text-slate-600">Consultez les documents validés et les informations de sécurité associées.</p>
    </div>

    <section aria-label="Résumé des validations" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('shared.stat-card', ['label' => 'Documents validés', 'value' => $stats['validated'], 'tone' => 'green', 'icon' => 'check'])
        @if ($memberStats ?? null)
            @include('shared.stat-card', ['label' => 'Membres validés', 'value' => $memberStats['validated'], 'tone' => 'blue', 'icon' => 'users'])
        @endif
    </section>

    <section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-validations-title">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
            <div>
                <h3 id="recent-validations-title" class="text-base font-semibold text-slate-900">Documents récemment validés</h3>
                <p class="mt-1 text-sm text-slate-500">Liste en consultation uniquement.</p>
            </div>
            <a href="{{ route('responsable-securite.documents.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">Voir tous les documents validés</a>
        </div>
        @include('shared.document-list', [
            'documents' => $documents,
            'showPrestataire' => true,
            'dateColumn' => 'validated_at',
            'detailRoute' => 'responsable-securite.documents.show',
            'emptyTitle' => 'Aucun document validé',
            'emptyMessage' => 'Les documents validés seront affichés ici.',
        ])
    </section>
@endsection
