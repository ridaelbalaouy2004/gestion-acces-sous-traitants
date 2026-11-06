@extends('layouts.authenticated')

@section('pageTitle', 'Documents validés')

@section('content')
    <div class="mb-7">
        <p class="text-sm font-medium text-slate-500">Consultation sécurité</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Documents validés</h2>
        <p class="mt-2 text-sm text-slate-600">Consultez les documents approuvés par un gestionnaire.</p>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Documents validés">
        <form method="GET" action="{{ route('responsable-securite.documents.index') }}" class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:px-6">
            <div class="min-w-0 flex-1">
                <label for="document-search" class="mb-1.5 block text-sm font-medium text-slate-700">Rechercher un document</label>
                <input
                    id="document-search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Titre, référence, entreprise ou prestataire"
                    class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900"
                >
            </div>
            <div class="sm:w-56">
                <label for="document-sort" class="mb-1.5 block text-sm font-medium text-slate-700">Date de validation</label>
                <select id="document-sort" name="sort" class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récent d’abord</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Plus ancien d’abord</option>
                </select>
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                Rechercher
            </button>
        </form>

        @if ($documents->isEmpty())
            <div class="flex flex-col items-center px-6 py-12 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7M8.5 15.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                <h3 class="mt-4 text-sm font-semibold text-slate-800">Aucun document validé</h3>
                <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500">Les documents approuvés apparaîtront ici.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Référence</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Titre</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataire</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Membres validés</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date de transmission</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date de validation</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Validé par</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($documents as $document)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">{{ $document->reference_number ?: '—' }}</td>
                                <td class="px-5 py-4 text-sm font-semibold text-slate-800">{{ $document->title }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $document->company_name ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $document->prestataire?->name ?? 'Compte indisponible' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ $document->safetyTeamMembers->pluck('name')->join(', ') ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ ($document->submitted_at ?? $document->created_at)?->format('d/m/Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ $document->validated_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $document->validatedBy?->name ?? 'Compte indisponible' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <a href="{{ route('responsable-securite.documents.show', $document) }}" class="font-semibold text-blue-700 hover:text-blue-900">Consulter</a>
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
