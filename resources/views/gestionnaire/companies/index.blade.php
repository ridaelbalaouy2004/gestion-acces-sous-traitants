@extends('layouts.authenticated')

@section('pageTitle', 'Gestion des entreprises')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Validation des sociétés</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Entreprises</h2>
        </div>
    </div>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('gestionnaire.companies.index') }}" class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="company-search" class="mb-1.5 block text-sm font-medium text-slate-700">Recherche</label>
                <input id="company-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="Nom ou prestataire">
            </div>
            <div>
                <label for="company-status" class="mb-1.5 block text-sm font-medium text-slate-700">Statut</label>
                <select id="company-status" name="status" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Tous</option>
                    <option value="pending" @selected(($filters['status'] ?? null) === 'pending')>En attente</option>
                    <option value="validated" @selected(($filters['status'] ?? null) === 'validated')>Validée</option>
                    <option value="rejected" @selected(($filters['status'] ?? null) === 'rejected')>Rejetée</option>
                </select>
            </div>
            <div>
                <label for="company-prestataire" class="mb-1.5 block text-sm font-medium text-slate-700">Prestataire</label>
                <select id="company-prestataire" name="prestataire_id" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Tous</option>
                    @foreach ($prestataires as $prestataire)
                        <option value="{{ $prestataire->id }}" @selected(($filters['prestataire_id'] ?? null) == $prestataire->id)>{{ $prestataire->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-2 md:col-span-4">
                <button type="submit" class="filter-action inline-flex min-h-10 items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold text-white">
                    Filtrer
                </button>
                <a href="{{ route('gestionnaire.companies.index') }}" class="filter-reset inline-flex min-h-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-semibold transition">
                    Réinitialiser
                </a>
            </div>
        </form>
    </section>

    <p class="mb-3 text-sm text-slate-600" aria-live="polite">{{ $companies->total() }} résultat(s)</p>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @if ($companies->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-slate-500">Aucune entreprise trouvée.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataire</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($companies as $company)
                        <tr
                            data-navigate-to="{{ route('gestionnaire.companies.show', $company) }}"
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
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $company->prestataire?->name ?? '—' }}</td>
                            <td class="px-5 py-4 text-sm">
                                @switch($company->status)
                                    @case('pending') <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">En attente</span> @break
                                    @case('validated') <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900">Validée</span> @break
                                    @default <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Rejetée</span>
                                @endswitch
                            </td>
                            <td class="px-5 py-4 text-right text-sm">
                                <a href="{{ route('gestionnaire.companies.show', $company) }}" class="rounded font-semibold text-blue-700 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">Consulter</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                </table>
            </div>
            @if ($companies->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $companies->links() }}</div>
            @endif
        @endif
    </div>
@endsection
