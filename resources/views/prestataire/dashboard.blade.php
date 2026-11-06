@extends('layouts.authenticated')

@section('pageTitle', 'Tableau de bord')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Votre espace de travail</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Bonjour, {{ auth()->user()->name }}</h2>
            <p class="mt-2 text-sm text-slate-600">Suivez l’avancement de vos documents d’accès.</p>
        </div>
        <a href="{{ route('prestataire.documents.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
            <span aria-hidden="true">+</span>
            Nouveau document
        </a>
        <a href="{{ route('prestataire.safety-team') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-800 shadow-sm transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
            Mon équipe de sécurité
        </a>
    </div>

    <section aria-label="Résumé de mes documents" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('shared.stat-card', ['label' => 'Total des documents', 'value' => $stats['total'], 'tone' => 'blue', 'icon' => 'documents'])
        @include('shared.stat-card', ['label' => 'En attente', 'value' => $stats['pending'], 'tone' => 'orange', 'icon' => 'clock'])
        @include('shared.stat-card', ['label' => 'Validés', 'value' => $stats['validated'], 'tone' => 'green', 'icon' => 'check'])
        @include('shared.stat-card', ['label' => 'Rejetés', 'value' => $stats['rejected'], 'tone' => 'red', 'icon' => 'x'])
    </section>

    <section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-documents-title">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
            <div>
                <h3 id="recent-documents-title" class="text-base font-semibold text-slate-900">Documents récents</h3>
                <p class="mt-1 text-sm text-slate-500">Les derniers documents associés à votre compte.</p>
            </div>
            <a href="{{ route('prestataire.documents.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">Voir mes documents</a>
        </div>
        @include('shared.document-list', [
            'documents' => $documents,
            'showPrestataire' => false,
            'dateColumn' => 'created_at',
            'emptyTitle' => 'Aucun document pour le moment',
            'emptyMessage' => 'Les documents que vous transmettrez apparaîtront ici.',
        ])
    </section>
@endsection
