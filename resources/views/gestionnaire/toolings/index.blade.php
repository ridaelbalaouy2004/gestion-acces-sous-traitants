@extends('layouts.authenticated')

@section('pageTitle', 'Gestion des outillages')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-sm font-medium text-slate-500">Outillages des Prestataires qui vous sont affectés</p>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Gestion des outillages</h2>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-800">
                    {{ $toolingCount }} {{ $toolingCount === 1 ? 'outillage' : 'outillages' }}
                </span>
            </div>
        </div>

        <form method="GET" action="{{ route('gestionnaire.toolings.index') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
            <div>
                <label for="prestataire_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Prestataire</label>
                <select id="prestataire_id" name="prestataire_id" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Tous les Prestataires affectés</option>
                    @foreach ($prestataires as $prestataire)
                        <option value="{{ $prestataire->id }}" @selected((string) ($filters['prestataire_id'] ?? '') === (string) $prestataire->id)>{{ $prestataire->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="company_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Entreprise</label>
                <select id="company_id" name="company_id" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Toutes les entreprises affectées</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">Statut</label>
                <select id="status" name="status" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Tous les statuts</option>
                    @foreach (['pending' => 'En attente', 'validated' => 'Validé', 'rejected' => 'Rejeté'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category" class="mb-1.5 block text-sm font-semibold text-slate-700">Catégorie</label>
                <select id="category" name="category" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">Recherche</label>
                <input id="search" name="search" type="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Désignation, référence, série" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <button type="submit" class="filter-action inline-flex min-h-10 items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold text-white">Filtrer</button>
                <a href="{{ route('gestionnaire.toolings.index') }}" class="filter-reset inline-flex min-h-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-semibold">Effacer</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if ($toolings->isEmpty())
                <div class="px-6 py-14 text-center">
                    <h3 class="text-lg font-semibold text-slate-900">Aucun outillage trouvé</h3>
                    <p class="mt-2 text-sm text-slate-600">Aucun résultat ne correspond à vos filtres.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Outillage</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataire / entreprise</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Catégorie / référence</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Quantité</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($toolings as $tooling)
                                <tr
                                    data-navigate-to="{{ route('gestionnaire.toolings.show', $tooling) }}"
                                    tabindex="0"
                                    aria-label="Examiner l’outillage {{ $tooling->designation }}"
                                    aria-keyshortcuts="Enter Space"
                                    class="cursor-pointer transition-colors hover:bg-slate-50/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-700"
                                >
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900">{{ $tooling->designation }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700"><span class="block font-medium">{{ $tooling->company->prestataire->name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $tooling->company->name }}</span></td>
                                    <td class="px-5 py-4 text-sm text-slate-700"><span class="block">{{ $tooling->category }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $tooling->internal_reference ?: $tooling->serial_number ?: 'Référence non renseignée' }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $tooling->quantity }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">@include('shared.company-toolings.status', ['status' => $tooling->status])</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('gestionnaire.toolings.show', $tooling) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Examiner</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $toolings->links() }}</div>
            @endif
        </div>
    </section>
@endsection
