@extends('layouts.authenticated')

@section('pageTitle', 'Fiche Gestionnaire')

@section('content')
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('gestionnaire.employes.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Retour aux Gestionnaires</a>
            <p class="mt-5 text-sm font-medium text-slate-500">Compte Gestionnaire</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $manager->name }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $manager->email }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('gestionnaire.employes.edit', $manager) }}" class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
            <form method="POST" action="{{ route('gestionnaire.employes.status', $manager) }}" data-confirm="Confirmer le changement de statut de ce Gestionnaire ?">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex min-h-10 items-center rounded-xl border px-4 text-sm font-semibold {{ $manager->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">{{ $manager->is_active ? 'Désactiver' : 'Réactiver' }}</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Permissions</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $manager->permissions->count() }} permission(s) attribuée(s)</p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($manager->permissions as $permission)
                    <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800">{{ $permission->label }}</span>
                @empty
                    <p class="text-sm text-slate-500">Aucune permission n’est attribuée.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('gestionnaire.employes.permissions.update', $manager) }}" class="mt-6 border-t border-slate-100 pt-5">
                @csrf
                @method('PUT')
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($permissionsByModule as $module => $permissions)
                        <input type="hidden" name="permission_modules[]" value="{{ $module }}">
                        <fieldset class="rounded-xl border border-slate-200 p-4">
                            <legend class="px-1 text-sm font-semibold text-slate-800">{{ $moduleLabels[$module] ?? $module }}</legend>
                            <div class="mt-2 space-y-3">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-start gap-3 text-sm text-slate-700">
                                        <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($manager->permissions->contains('id', $permission->id)) class="mt-0.5 rounded border-slate-300 text-blue-700">
                                        <span>{{ $permission->label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <button type="submit" class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">Enregistrer les permissions</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="text-lg font-semibold text-slate-900">Prestataires affectés</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $manager->assignedPrestataires->count() }} Prestataire(s)</p>
            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($manager->assignedPrestataires as $prestataire)
                    <li
                        data-navigate-to="{{ route('gestionnaire.prestataires.show', $prestataire) }}"
                        tabindex="0"
                        aria-label="Consulter le Prestataire {{ $prestataire->name }}"
                        aria-keyshortcuts="Enter Space"
                        class="cursor-pointer py-3 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"
                    >
                        <a href="{{ route('gestionnaire.prestataires.show', $prestataire) }}" class="text-sm font-semibold text-slate-800 hover:text-blue-700">{{ $prestataire->name }}</a>
                        <p class="text-sm text-slate-500">{{ $prestataire->email }}</p>
                    </li>
                @empty
                    <li class="py-3 text-sm text-slate-500">Aucun Prestataire n’est affecté à ce Gestionnaire.</li>
                @endforelse
            </ul>
            <div class="mt-6 border-t border-slate-100 pt-5">
                <form method="GET" action="{{ route('gestionnaire.employes.show', $manager) }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">
                    <div>
                        <label for="assignment-search" class="mb-1.5 block text-sm font-medium text-slate-700">Rechercher un Prestataire</label>
                        <input id="assignment-search" name="assignment_search" type="search" value="{{ $assignmentFilters['assignment_search'] ?? '' }}" maxlength="100" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="Nom ou e-mail">
                    </div>
                    <div>
                        <label for="assignment-status" class="mb-1.5 block text-sm font-medium text-slate-700">Statut</label>
                        <select id="assignment-status" name="assignment_status" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
                            <option value="">Tous les statuts</option>
                            <option value="active" @selected(($assignmentFilters['assignment_status'] ?? null) === 'active')>Actif</option>
                            <option value="inactive" @selected(($assignmentFilters['assignment_status'] ?? null) === 'inactive')>Désactivé</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="filter-action inline-flex min-h-10 flex-1 items-center justify-center rounded-xl px-3 text-sm font-semibold text-white">Filtrer</button>
                        <a href="{{ route('gestionnaire.employes.show', $manager) }}" class="filter-reset inline-flex min-h-10 items-center justify-center rounded-xl border px-3 text-sm font-semibold">Effacer</a>
                    </div>
                </form>
            </div>
            <form method="POST" action="{{ route('gestionnaire.employes.assignments.update', $manager) }}" class="mt-5">
                @csrf
                @method('PUT')
                <fieldset>
                    <legend class="text-sm font-semibold text-slate-800">Modifier les affectations</legend>
                    @if ($prestataires->isEmpty())
                        <p class="mt-3 text-sm text-slate-500">Aucun Prestataire disponible.</p>
                    @else
                        <div class="mt-3 max-h-80 space-y-3 overflow-y-auto">
                            @foreach ($prestataires as $prestataire)
                                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
                                    <input type="checkbox" name="prestataire_ids[]" value="{{ $prestataire->id }}" @checked(in_array($prestataire->id, (array) old('prestataire_ids', $manager->assignedPrestataires->modelKeys()))) class="mt-0.5 rounded border-slate-300 text-blue-700">
                                    <span><span class="block font-semibold text-slate-800">{{ $prestataire->name }}</span><span class="text-slate-500">{{ $prestataire->email }}</span><span class="mt-1 block text-xs {{ $prestataire->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $prestataire->is_active ? 'Actif' : 'Désactivé' }}</span></span>
                                </label>
                            @endforeach
                            @foreach ($manager->assignedPrestataires->whereNotIn('id', $prestataires->pluck('id')) as $prestataire)
                                <input type="hidden" name="prestataire_ids[]" value="{{ $prestataire->id }}">
                            @endforeach
                        </div>
                    @endif
                </fieldset>
                <button type="submit" class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">Enregistrer les affectations</button>
            </form>
            @if ($assignmentHistory->isNotEmpty())
                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h4 class="text-sm font-semibold text-slate-800">Historique récent des affectations</h4>
                    <ul tabindex="0" aria-label="Historique récent des affectations" class="mt-3 max-h-56 space-y-2 overflow-y-auto overscroll-contain pr-2">
                        @foreach ($assignmentHistory as $history)
                            <li class="text-sm text-slate-600">
                                {{ $history->action === 'assigned' ? 'Affecté' : 'Retiré' }} :
                                <span class="font-medium text-slate-800">{{ $history->prestataire_name }}</span>
                                — {{ $history->created_at }} · {{ $history->actor_name }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>

    @if ((int) $principalId === (int) auth()->id())
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-7">
            <h3 class="text-lg font-semibold text-amber-950">Transférer la responsabilité principale</h3>
            <p class="mt-1 text-sm text-amber-900">Le transfert est immédiat. Le Gestionnaire Principal actuel devient un employé et le nouveau Principal pourra à son tour transférer cette responsabilité.</p>
            <form method="POST" action="{{ route('gestionnaire.employes.principal.transfer', $manager) }}" data-confirm="Transférer définitivement la responsabilité de Gestionnaire Principal à ce compte ?">
                @csrf
                <button type="submit" class="mt-4 inline-flex min-h-10 items-center rounded-xl border border-amber-300 px-4 text-sm font-semibold text-amber-950 hover:bg-amber-100">Transférer au Gestionnaire {{ $manager->name }}</button>
            </form>
        </section>
    @endif
@endsection
