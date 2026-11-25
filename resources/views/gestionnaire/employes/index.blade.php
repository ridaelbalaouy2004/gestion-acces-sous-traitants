@extends('layouts.authenticated')

@section('pageTitle', 'Employés Gestionnaire')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Administration</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Employés Gestionnaire</h2>
            <p class="mt-2 text-sm text-slate-600">Gérez les comptes, permissions et affectations de Prestataires.</p>
        </div>
        <a href="{{ route('gestionnaire.employes.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
            Ajouter un Gestionnaire
        </a>
    </div>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Filtres des Gestionnaires">
        <form method="GET" action="{{ route('gestionnaire.employes.index') }}" class="grid gap-3 md:grid-cols-4 md:items-end">
            <div class="md:col-span-2">
                <label for="manager-search" class="mb-1.5 block text-sm font-medium text-slate-700">Recherche</label>
                <input id="manager-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="Nom ou adresse e-mail">
            </div>
            <div>
                <label for="manager-status" class="mb-1.5 block text-sm font-medium text-slate-700">Statut</label>
                <select id="manager-status" name="status" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Actif</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Désactivé</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="filter-action inline-flex min-h-10 items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold text-white">Filtrer</button>
                <a href="{{ route('gestionnaire.employes.index') }}" class="filter-reset inline-flex min-h-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-semibold">Effacer</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Liste des Gestionnaires">
        @if ($managers->isEmpty())
            <div class="px-6 py-12 text-center">
                <h3 class="text-sm font-semibold text-slate-800">Aucun employé Gestionnaire trouvé</h3>
                <p class="mt-1 text-sm text-slate-500">Créez un compte Gestionnaire ou modifiez les filtres.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Nom</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Adresse e-mail</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Création</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataires</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Permissions</th>
                            <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($managers as $manager)
                            <tr
                                data-navigate-to="{{ route('gestionnaire.employes.show', $manager) }}"
                                tabindex="0"
                                aria-label="Consulter le Gestionnaire {{ $manager->name }}"
                                aria-keyshortcuts="Enter Space"
                                class="cursor-pointer transition-colors hover:bg-blue-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-700"
                            >
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-800">{{ $manager->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $manager->email }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $manager->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-700' }}">{{ $manager->is_active ? 'Actif' : 'Désactivé' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $manager->created_at?->format('d/m/Y à H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $manager->assigned_prestataires_count }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $manager->permissions_count }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    <a href="{{ route('gestionnaire.employes.show', $manager) }}" class="rounded-lg px-2.5 py-2 font-semibold text-blue-700 hover:bg-blue-50">Consulter</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $managers->links() }}</div>
        @endif
    </section>
@endsection
