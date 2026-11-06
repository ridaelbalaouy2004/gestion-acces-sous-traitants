@extends('layouts.authenticated')

@section('pageTitle', 'Mes documents')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Votre espace de travail</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Mes documents</h2>
            <p class="mt-2 text-sm text-slate-600">Consultez et suivez les documents transmis pour vos accès.</p>
        </div>
        <a href="{{ route('prestataire.documents.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
            <span aria-hidden="true">+</span>
            Nouveau document
        </a>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="documents-list-title">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <h3 id="documents-list-title" class="text-base font-semibold text-slate-900">Tous mes documents</h3>
            <p class="mt-1 text-sm text-slate-500">Les résultats les plus récents apparaissent en premier.</p>

            <form method="GET" action="{{ route('prestataire.documents.index') }}" class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]">
                <label class="sr-only" for="document-search">Rechercher un document</label>
                <input
                    id="document-search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Rechercher par titre, référence ou entreprise"
                    class="form-control min-h-11 rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900 placeholder:text-slate-400"
                >
                <label class="sr-only" for="document-status">Filtrer par statut</label>
                <select id="document-status" name="status" class="form-control min-h-11 rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-700">
                    <option value="">Tous les statuts</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>En attente</option>
                    <option value="validated" @selected(($filters['status'] ?? '') === 'validated')>Validé</option>
                    <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejeté</option>
                </select>
                <button type="submit" class="min-h-11 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
                    Rechercher
                </button>
                @if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '')
                    <a href="{{ route('prestataire.documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Effacer
                    </a>
                @endif
            </form>
        </div>

        @if ($documents->isEmpty())
            <div class="flex flex-col items-center px-6 py-12 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7M8.5 15.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                @if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '')
                    <h4 class="mt-4 text-sm font-semibold text-slate-800">Aucun document ne correspond à votre recherche</h4>
                    <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500">Essayez de modifier les filtres ou effacez-les pour afficher tous vos documents.</p>
                @else
                    <h4 class="mt-4 text-sm font-semibold text-slate-800">Aucun document pour le moment</h4>
                    <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500">Transmettez votre premier document pour commencer le suivi de vos demandes d’accès.</p>
                    <a href="{{ route('prestataire.documents.create') }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                        Créer un document
                    </a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Document</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date de transmission</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                            <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($documents as $document)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-4 sm:px-6">
                                    <a href="{{ route('prestataire.documents.show', $document) }}" class="block max-w-72 truncate text-sm font-semibold text-blue-800 hover:text-blue-950">
                                        {{ $document->title }}
                                    </a>
                                    <p class="mt-1 text-xs text-slate-500">{{ $document->reference_number ?: 'Référence en attente' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                                    {{ ($document->submitted_at ?? $document->created_at)?->format('d/m/Y \à H:i') ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @include('shared.document-status-badge', ['status' => $document->status])
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    <a href="{{ route('prestataire.documents.show', $document) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">
                                        Consulter
                                    </a>
                                    @if ($document->status === 'rejected')
                                        <span class="px-1 text-slate-300" aria-hidden="true">·</span>
                                        <a href="{{ route('prestataire.documents.edit', $document) }}" class="text-sm font-semibold text-red-700 hover:text-red-900">
                                            Corriger
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($documents->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                    {{ $documents->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection
