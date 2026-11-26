@extends('layouts.authenticated')

@section('pageTitle', 'Gestion déléguée des Prestataires')

@section('content')
    @php
        $statusBadges = [
            [
                'label' => 'En attente',
                'key' => 'pending_companies_count',
                'class' => 'border-amber-200 bg-amber-50 text-amber-800',
            ],
            [
                'label' => 'Validées',
                'key' => 'validated_companies_count',
                'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            ],
            [
                'label' => 'Rejetées',
                'key' => 'rejected_companies_count',
                'class' => 'border-rose-200 bg-rose-50 text-rose-800',
            ],
            [
                'label' => 'Brouillons',
                'key' => 'draft_companies_count',
                'class' => 'border-slate-200 bg-slate-100 text-slate-700',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-7">
        <header class="overflow-hidden rounded-3xl border border-[#d9eee2] bg-[#fffefa] shadow-sm">
            <div class="p-5 sm:p-7 lg:p-8">
                <nav aria-label="Fil d’Ariane" class="mb-6">
                    <ol class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <li>
                            <a href="{{ route('gestionnaire.dashboard') }}" class="rounded font-medium hover:text-[#164f43] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                                Tableau de bord
                            </a>
                        </li>
                        <li aria-hidden="true">/</li>
                        <li aria-current="page" class="font-semibold text-[#164f43]">Gestion déléguée</li>
                    </ol>
                </nav>

                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-[#c7e4d3] bg-[#d9eee2] px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#123e36]">
                            <span class="h-2 w-2 rounded-full bg-[#164f43]" aria-hidden="true"></span>
                            Espace Gestionnaire principal
                        </span>
                        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl lg:text-4xl">
                            Gestion déléguée des Prestataires
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Gérer les dossiers pour le compte d’un Prestataire auquel vous avez accès. Les entreprises et documents restent sa propriété et suivent le workflow habituel de validation.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8m1 2.2a5 5 0 0 1 3.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>
                            <span class="block text-xs font-medium text-slate-500">Prestataires accessibles</span>
                            <span class="mt-0.5 block text-lg font-bold leading-5 text-[#123e36]">{{ number_format($prestataires->total(), 0, ',', ' ') }}</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="h-1 bg-[#164f43]" aria-hidden="true"></div>
        </header>

        <section aria-label="Statistiques des dossiers délégués" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @include('shared.stat-card', [
                'label' => 'Dossiers délégués',
                'value' => $companyStats['total'],
                'tone' => 'green',
                'icon' => 'documents',
            ])
            @include('shared.stat-card', [
                'label' => 'En attente',
                'value' => $companyStats['pending'],
                'tone' => 'orange',
                'icon' => 'clock',
            ])
            @include('shared.stat-card', [
                'label' => 'Validés',
                'value' => $companyStats['validated'],
                'tone' => 'green',
                'icon' => 'check',
            ])
            @include('shared.stat-card', [
                'label' => 'Rejetés',
                'value' => $companyStats['rejected'],
                'tone' => 'red',
                'icon' => 'x',
            ])
        </section>

        <section class="rounded-2xl border border-slate-200 bg-[#fffefa] p-5 shadow-sm sm:p-6" aria-labelledby="filters-title">
            <div class="mb-5">
                <h2 id="filters-title" class="text-base font-semibold text-slate-900">Rechercher et filtrer</h2>
                <p class="mt-1 text-sm text-slate-500">La recherche couvre le Prestataire, son adresse e-mail et le nom de ses entreprises.</p>
            </div>
            <form method="GET" action="{{ route('gestionnaire.delegated.index') }}" class="space-y-5">
                <div class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <div class="min-w-0 xl:col-span-2">
                        <label for="delegated-search" class="mb-1.5 block text-sm font-medium text-slate-700">Prestataire ou entreprise</label>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" fill="none" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true">
                                <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/>
                                <path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <input
                                id="delegated-search"
                                name="search"
                                type="search"
                                value="{{ $filters['search'] ?? '' }}"
                                maxlength="120"
                                placeholder="Nom, e-mail ou entreprise"
                                class="form-control min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white py-2 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400"
                            >
                        </div>
                    </div>
                    <div>
                        <label for="delegated-status" class="mb-1.5 block text-sm font-medium text-slate-700">Statut</label>
                        <select id="delegated-status" name="status" class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                            <option value="">Tous les statuts</option>
                            <option value="{{ \App\Models\Company::STATUS_DRAFT }}" @selected(($filters['status'] ?? '') === \App\Models\Company::STATUS_DRAFT)>Brouillon</option>
                            <option value="{{ \App\Models\Company::STATUS_PENDING }}" @selected(($filters['status'] ?? '') === \App\Models\Company::STATUS_PENDING)>En attente</option>
                            <option value="{{ \App\Models\Company::STATUS_VALIDATED }}" @selected(($filters['status'] ?? '') === \App\Models\Company::STATUS_VALIDATED)>Validé</option>
                            <option value="{{ \App\Models\Company::STATUS_REJECTED }}" @selected(($filters['status'] ?? '') === \App\Models\Company::STATUS_REJECTED)>Rejeté</option>
                        </select>
                    </div>
                    <div>
                        <label for="delegated-date-field" class="mb-1.5 block text-sm font-medium text-slate-700">Date à filtrer</label>
                        <select id="delegated-date-field" name="date_field" class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                            <option value="created_at" @selected(($filters['date_field'] ?? 'created_at') === 'created_at')>Création</option>
                            <option value="submitted_at" @selected(($filters['date_field'] ?? '') === 'submitted_at')>Soumission</option>
                        </select>
                    </div>
                    <div>
                        <label for="delegated-date-from" class="mb-1.5 block text-sm font-medium text-slate-700">Du</label>
                        <input id="delegated-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="form-control min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                    </div>
                    <div>
                        <label for="delegated-date-to" class="mb-1.5 block text-sm font-medium text-slate-700">Au</label>
                        <input id="delegated-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="form-control min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800">
                    </div>
                </div>
                <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-slate-500">Les filtres de statut et de date s’appliquent aux entreprises du Prestataire.</p>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        @if ($hasActiveFilters)
                            <a href="{{ route('gestionnaire.delegated.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-[#9bcbb0] hover:bg-[#f3faf5] hover:text-[#123e36]">
                                Réinitialiser
                            </a>
                        @endif
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#164f43] px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/>
                                <path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            Appliquer les filtres
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section id="prestataires" class="overflow-hidden rounded-2xl border border-slate-200 bg-[#fffefa] shadow-sm" aria-labelledby="prestataires-title">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <h2 id="prestataires-title" class="text-lg font-semibold text-slate-950">Prestataires accessibles</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($prestataires->total() > 0)
                            {{ number_format($prestataires->firstItem(), 0, ',', ' ') }}–{{ number_format($prestataires->lastItem(), 0, ',', ' ') }} sur {{ number_format($prestataires->total(), 0, ',', ' ') }} résultat(s)
                        @else
                            Aucun résultat
                        @endif
                    </p>
                </div>
                <p class="text-xs font-medium text-slate-500">Les brouillons sont visibles uniquement dans cet espace délégué.</p>
            </div>

            @if ($prestataires->isEmpty())
                <div class="px-5 py-12 text-center sm:px-8">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7">
                            <circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/>
                            <path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </span>
                    @if ($hasActiveFilters)
                        <h3 class="mt-4 text-base font-semibold text-slate-900">Aucun dossier ne correspond à ces filtres</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500">Modifiez la recherche ou réinitialisez les filtres pour afficher les Prestataires accessibles.</p>
                        <a href="{{ route('gestionnaire.delegated.index') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#164f43] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#123e36]">
                            Effacer les filtres
                        </a>
                    @else
                        <h3 class="mt-4 text-base font-semibold text-slate-900">Aucun Prestataire accessible pour le moment</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500">Les comptes qui vous sont accessibles apparaîtront ici pour que vous puissiez préparer leurs dossiers.</p>
                    @endif
                </div>
            @else
                <div class="hidden grid-cols-[minmax(0,1.8fr)_minmax(14rem,1fr)_auto] items-center gap-4 border-b border-slate-100 bg-slate-50/80 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid">
                    <span>Prestataire</span>
                    <span>Entreprises et statuts</span>
                    <span class="text-right">Action</span>
                </div>
                <ul class="space-y-3 p-3 md:space-y-0 md:p-0">
                    @foreach ($prestataires as $prestataire)
                        <li class="min-w-0">
                            <article class="grid min-w-0 gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#b8d9c6] hover:shadow-md md:grid-cols-[minmax(0,1.8fr)_minmax(14rem,1fr)_auto] md:items-center md:rounded-none md:border-0 md:px-6 md:py-5 md:shadow-none md:even:bg-[#fbfcf9] md:hover:bg-[#f3faf5] md:hover:shadow-none">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#d9eee2] text-sm font-bold text-[#164f43]" aria-hidden="true">
                                        {{ mb_strtoupper(mb_substr($prestataire->name, 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="truncate font-semibold text-slate-900" title="{{ $prestataire->name }}">{{ $prestataire->name }}</h3>
                                        <p class="mt-1 truncate text-sm text-slate-500" title="{{ $prestataire->email }}">{{ $prestataire->email }}</p>
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-[#c7e4d3] bg-[#f3faf5] px-2.5 py-1 text-sm font-semibold text-[#123e36]">
                                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                                <path d="M4 20V5.5A1.5 1.5 0 0 1 5.5 4H15v16M15 9h3.5A1.5 1.5 0 0 1 20 10.5V20M2.5 20h19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M8 8h3m-3 4h3m-3 4h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                            </svg>
                                            {{ number_format($prestataire->companies_count, 0, ',', ' ') }} entreprise(s)
                                        </span>
                                        @if ($prestataire->latest_company_created_at)
                                            <span class="text-xs text-slate-500">Dernière création · {{ \Illuminate\Support\Carbon::parse($prestataire->latest_company_created_at)->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Répartition des entreprises par statut">
                                        @foreach ($statusBadges as $statusBadge)
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium {{ $statusBadge['class'] }}">
                                                {{ $statusBadge['label'] }}
                                                <span class="font-bold">{{ number_format($prestataire->{$statusBadge['key']}, 0, ',', ' ') }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <a href="{{ route('gestionnaire.delegated.show', $prestataire) }}" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#164f43] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43] md:w-auto">
                                    Gérer ses dossiers
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M4 12h15m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            </article>
                        </li>
                    @endforeach
                </ul>

                @if ($prestataires->hasPages())
                    <div class="border-t border-slate-200 px-4 py-4 sm:px-6">
                        {{ $prestataires->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
