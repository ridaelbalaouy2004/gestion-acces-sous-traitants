@extends('layouts.authenticated')

@section('pageTitle', 'Gestion des outillages')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-sm font-medium text-slate-500">Outillages des Prestataires qui vous sont affectés</p>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Gestion des outillages</h2>
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-semibold text-slate-700">
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

        <div class="space-y-4">
            @if ($toolings->isEmpty())
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-14 text-center shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Aucun outillage trouvé</h3>
                    <p class="mt-2 text-sm text-slate-600">Aucun résultat ne correspond à vos filtres.</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($toolings as $tooling)
                        <article
                            data-navigate-to="{{ route('gestionnaire.toolings.show', $tooling) }}"
                            tabindex="0"
                            aria-label="Examiner l’outillage {{ $tooling->designation }}"
                            aria-keyshortcuts="Enter Space"
                            class="group flex min-w-0 cursor-pointer flex-col rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition duration-150 hover:border-slate-300 hover:bg-slate-100/70 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]"
                        >
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <h3 class="min-w-0 break-words text-base font-semibold leading-6 text-slate-900">{{ $tooling->designation }}</h3>
                                <span class="shrink-0">@include('shared.company-toolings.status', ['status' => $tooling->status])</span>
                            </div>
                            <dl class="mt-5 grid min-w-0 gap-x-4 gap-y-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium text-slate-500">Prestataire</dt>
                                    <dd class="mt-1 break-words text-sm font-medium text-slate-800">{{ $tooling->company->prestataire->name }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium text-slate-500">Entreprise</dt>
                                    <dd class="mt-1 break-words text-sm text-slate-700">{{ $tooling->company->name }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium text-slate-500">Catégorie</dt>
                                    <dd class="mt-1 break-words text-sm text-slate-700">{{ $tooling->category }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium text-slate-500">Référence</dt>
                                    <dd class="mt-1 break-words text-sm text-slate-700">{{ $tooling->internal_reference ?: $tooling->serial_number ?: 'Référence non renseignée' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium text-slate-500">Quantité</dt>
                                    <dd class="mt-1 break-words text-sm text-slate-700">{{ $tooling->quantity }}</dd>
                                </div>
                            </dl>
                            <div class="mt-auto flex justify-end border-t border-slate-200 pt-4">
                                <a href="{{ route('gestionnaire.toolings.show', $tooling) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-[#164f43] transition hover:border-[#c7e4d3] hover:bg-[#d9eee2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                                    Examiner
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 shadow-sm">{{ $toolings->links() }}</div>
            @endif
        </div>
    </section>
@endsection
