@extends('layouts.authenticated')

@section('pageTitle', 'Tableau de bord')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Votre espace de travail</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Bonjour, {{ auth()->user()->name }}</h2>
            <p class="mt-2 text-sm text-slate-600">Suivez vos entreprises et vos employés permanents.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('prestataire.companies.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-800 shadow-sm transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                Mes entreprises
            </a>
            <a href="{{ route('prestataire.employees.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                Mon équipe
            </a>
            <a href="{{ route('prestataire.machinery.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-800 shadow-sm transition hover:bg-blue-50">
                Mes engins
            </a>
            <a href="{{ route('prestataire.toolings.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-800 shadow-sm transition hover:bg-blue-50">
                Mes outillages
            </a>
        </div>
    </div>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="prestataire-required-guide-title">
        <h3 id="prestataire-required-guide-title" class="text-base font-semibold text-slate-900">Guide des documents requis</h3>
        @if ($requiredDocumentsGuideAvailable)
            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="break-all text-sm font-medium text-slate-800">{{ $requiredDocumentsGuide->file_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">Dernière mise à jour : {{ $requiredDocumentsGuide->updated_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                </div>
                <a href="{{ route('prestataire.guides.required-documents.download') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    Télécharger le Guide Word
                </a>
            </div>
        @else
            <p class="mt-2 text-sm text-slate-600">Le Guide des documents requis n'est pas encore disponible.</p>
        @endif
    </section>

    <section aria-label="Résumé de mon espace" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('shared.stat-card', ['label' => 'Mes entreprises', 'value' => $stats['companies'], 'tone' => 'blue', 'icon' => 'building'])
        @include('shared.stat-card', ['label' => 'Employés permanents', 'value' => $stats['employees'], 'tone' => 'green', 'icon' => 'users'])
        @include('shared.stat-card', ['label' => 'Mes engins', 'value' => $stats['machinery'], 'tone' => 'amber', 'icon' => 'truck'])
        @include('shared.stat-card', ['label' => 'Mes outillages', 'value' => $stats['toolings'], 'tone' => 'blue', 'icon' => 'tool'])
    </section>
@endsection
