@extends('layouts.authenticated')

@section('pageTitle', 'Gestion des Prestataires')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Comptes utilisateurs</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Prestataires</h2>
            <p class="mt-2 text-sm text-slate-600">Créez et gérez les comptes Prestataires.</p>
        </div>
        @if (auth()->user()->hasPermission('prestataires.create'))
            <a href="{{ route('gestionnaire.prestataires.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                Ajouter un Prestataire
            </a>
        @endif
    </div>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Filtres des Prestataires">
        <form method="GET" action="{{ route('gestionnaire.prestataires.index') }}" class="grid gap-3 md:grid-cols-4 md:items-end">
            <div class="md:col-span-2">
                <label for="prestataire-search" class="mb-1.5 block text-sm font-medium text-slate-700">Recherche</label>
                <input id="prestataire-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="Nom ou adresse e-mail">
            </div>
            <div>
                <label for="prestataire-status" class="mb-1.5 block text-sm font-medium text-slate-700">Statut du compte</label>
                <select id="prestataire-status" name="status" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Actif</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Désactivé</option>
                </select>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <button type="submit" class="filter-action inline-flex min-h-10 items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold text-white">Filtrer</button>
                <a href="{{ route('gestionnaire.prestataires.index') }}" class="filter-reset inline-flex min-h-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-semibold">Effacer</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Liste des Prestataires">
        @if ($prestataires->isEmpty())
            <div class="px-6 py-12 text-center">
                <h3 class="text-sm font-semibold text-slate-800">Aucun Prestataire trouvé</h3>
                <p class="mt-1 text-sm text-slate-500">Essayez de modifier les filtres ou créez un nouveau compte.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Nom complet</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Adresse e-mail</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Création du compte</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Mot de passe</th>
                            <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($prestataires as $prestataire)
                            <tr
                                data-navigate-to="{{ route('gestionnaire.prestataires.show', $prestataire) }}"
                                tabindex="0"
                                aria-label="Consulter le Prestataire {{ $prestataire->name }}"
                                aria-keyshortcuts="Enter Space"
                                class="cursor-pointer transition-colors hover:bg-blue-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-700"
                            >
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-800">{{ $prestataire->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $prestataire->email }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $prestataire->created_at?->format('d/m/Y à H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    @if ($prestataire->is_active)
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900">Actif</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Désactivé</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    @if ($prestataire->must_change_password)
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Temporaire</span>
                                    @else
                                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">Modifié</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    <div class="inline-flex items-center gap-1">
                                        @if (auth()->user()->hasPermission('prestataires.view'))
                                            <a href="{{ route('gestionnaire.prestataires.show', $prestataire) }}" class="rounded-lg px-2.5 py-2 font-semibold text-blue-700 hover:bg-blue-50">Consulter</a>
                                        @endif
                                        @if (auth()->user()->hasPermission('prestataires.update'))
                                            <a href="{{ route('gestionnaire.prestataires.edit', $prestataire) }}" class="rounded-lg px-2.5 py-2 font-semibold text-slate-700 hover:bg-slate-100">Modifier</a>
                                        @endif
                                        @if (auth()->user()->hasPermission('prestataires.toggle_status'))
                                            <form method="POST" action="{{ route('gestionnaire.prestataires.toggle-status', $prestataire) }}" data-confirm="Confirmer le changement de statut de ce compte ?">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-lg px-2.5 py-2 font-semibold {{ $prestataire->is_active ? 'text-red-700 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50' }}">
                                                    {{ $prestataire->is_active ? 'Désactiver' : 'Réactiver' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $prestataires->links() }}
            </div>
        @endif
    </section>
@endsection
