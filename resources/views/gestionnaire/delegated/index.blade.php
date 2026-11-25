@extends('layouts.authenticated')

@section('pageTitle', 'Gestion déléguée des Prestataires')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <header class="rounded-2xl border border-blue-100 bg-blue-50 p-5 sm:p-7">
            <p class="text-sm font-semibold text-blue-800">Espace Gestionnaire principal</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Gérer les dossiers pour le compte d’un Prestataire</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Sélectionnez un Prestataire accessible pour préparer ses entreprises, employés, engins, outillages et documents.
                Les dossiers restent sa propriété et les dépôts suivent le workflow habituel de validation.
            </p>
        </header>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="prestataires-title">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h3 id="prestataires-title" class="text-base font-semibold text-slate-900">Prestataires accessibles</h3>
                <p class="mt-1 text-sm text-slate-500">Choisissez le compte à accompagner.</p>
            </div>

            @if ($prestataires->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500 sm:px-6">Aucun Prestataire accessible n’est disponible.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($prestataires as $prestataire)
                        <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ $prestataire->name }}</p>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $prestataire->email }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $prestataire->companies_count }} entreprise(s)</p>
                            </div>
                            <a href="{{ route('gestionnaire.delegated.show', $prestataire) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                                Gérer ses dossiers
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-slate-200 px-5 py-4 sm:px-6">
                    {{ $prestataires->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
