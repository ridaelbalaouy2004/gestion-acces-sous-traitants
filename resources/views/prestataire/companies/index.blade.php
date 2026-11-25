@extends('layouts.authenticated')

@section('pageTitle', 'Mes entreprises')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Votre portefeuille</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Mes entreprises</h2>
            <p class="mt-2 text-sm text-slate-600">Suivez vos sociétés et les documents associés.</p>
        </div>
        <a href="{{ route('prestataire.companies.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
            <span aria-hidden="true">+</span>
            Ajouter une entreprise
        </a>
    </div>

    @if ($companies->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-600 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Aucune entreprise enregistrée</h3>
            <p class="mt-2 text-sm text-slate-500">Créez votre première société pour gérer ses documents et validations.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-left">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Activité</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($companies as $company)
                        <tr
                            data-navigate-to="{{ route('prestataire.companies.show', $company) }}"
                            tabindex="0"
                            aria-label="Consulter l’entreprise {{ $company->name }}"
                            aria-keyshortcuts="Enter Space"
                            class="cursor-pointer transition-colors hover:bg-blue-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-700"
                        >
                            <td class="px-5 py-4 text-sm">
                                <span class="font-semibold text-slate-800">{{ $company->name }}</span>
                                <span class="mt-1 block text-xs font-medium text-slate-500" aria-label="Documents obligatoires déposés">
                                    {{ $company->required_documents_uploaded_count }}/{{ $requiredDocumentsTotal }} documents
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ $company->activity ?: '—' }}</td>
                            <td class="px-5 py-4 text-sm">
                                @switch($company->status)
                                    @case('draft') <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Brouillon</span> @break
                                    @case('pending') <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">En attente</span> @break
                                    @case('validated') <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900">Validée</span> @break
                                    @default <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Rejetée</span>
                                @endswitch
                            </td>
                            <td class="px-5 py-4 text-right text-sm">
                                <a href="{{ route('prestataire.companies.show', $company) }}" class="font-semibold text-blue-700 hover:text-blue-900">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($companies->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $companies->links() }}</div>
            @endif
        </div>
    @endif
@endsection
