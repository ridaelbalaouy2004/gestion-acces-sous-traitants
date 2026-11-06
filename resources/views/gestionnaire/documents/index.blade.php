@extends('layouts.authenticated')

@section('pageTitle', $pendingOnly ? 'Documents en attente' : 'Tous les documents')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Gestion des documents</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                {{ $pendingOnly ? 'Documents en attente' : 'Tous les documents' }}
            </h2>
            <p class="mt-2 text-sm text-slate-600">
                {{ $pendingOnly ? 'Examinez les demandes qui nécessitent une décision.' : 'Recherchez et consultez les demandes transmises par les prestataires.' }}
            </p>
        </div>
        @if (! $pendingOnly)
            <a href="{{ route('gestionnaire.documents.pending') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-orange-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-700">
                Examiner les demandes en attente
            </a>
        @endif
    </div>

    @if ($pendingOnly)
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900" role="status">
            <span class="mt-0.5 font-bold" aria-hidden="true">!</span>
            <p>Les documents ci-dessous attendent une décision du Gestionnaire. Consultez chaque dossier avant de le valider ou de le rejeter.</p>
        </div>
    @endif

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Filtres des documents">
        <form method="GET" action="{{ route($pendingOnly ? 'gestionnaire.documents.pending' : 'gestionnaire.documents.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1.5fr)_minmax(11rem,0.8fr)_minmax(11rem,0.8fr)_auto] md:items-end">
            <div>
                <label for="document-search" class="mb-1.5 block text-sm font-medium text-slate-700">Rechercher</label>
                <input
                    id="document-search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    maxlength="100"
                    placeholder="Titre, référence, entreprise ou prestataire"
                    class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"
                >
            </div>
            @unless ($pendingOnly)
                <div>
                    <label for="document-status" class="mb-1.5 block text-sm font-medium text-slate-700">Statut</label>
                    <select id="document-status" name="status" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                        <option value="">Tous les statuts</option>
                        <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>En attente</option>
                        <option value="validated" @selected(($filters['status'] ?? '') === 'validated')>Validé</option>
                        <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejeté</option>
                    </select>
                </div>
            @endunless
            <div>
                <label for="document-sort" class="mb-1.5 block text-sm font-medium text-slate-700">Trier par date</label>
                <select id="document-sort" name="sort" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récents</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Plus anciens</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                    Filtrer
                </button>
                <a href="{{ route($pendingOnly ? 'gestionnaire.documents.pending' : 'gestionnaire.documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                    Effacer
                </a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Liste des documents">
        @if ($documents->isEmpty())
            <div class="flex flex-col items-center px-6 py-12 text-center">
                <h3 class="text-sm font-semibold text-slate-800">{{ $pendingOnly ? 'Aucun document en attente' : 'Aucun document trouvé' }}</h3>
                <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500">
                    {{ $pendingOnly ? 'Les nouvelles demandes apparaîtront ici.' : 'Modifiez vos filtres ou attendez la transmission de nouveaux documents.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Référence et titre</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataire</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Transmis le</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($documents as $document)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="max-w-72 px-5 py-4 sm:px-6">
                                    <p class="truncate text-sm font-semibold text-slate-800">{{ $document->title }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $document->reference_number ?: 'Référence en attente' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $document->company_name ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $document->prestataire?->name ?? 'Compte indisponible' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ ($document->submitted_at ?? $document->created_at)?->format('d/m/Y à H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @include('shared.document-status-badge', ['status' => $document->status])
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('gestionnaire.documents.show', $document) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                                        {{ $document->status === 'pending' ? 'Examiner' : 'Consulter' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                {{ $documents->links() }}
            </div>
        @endif
    </section>
@endsection
