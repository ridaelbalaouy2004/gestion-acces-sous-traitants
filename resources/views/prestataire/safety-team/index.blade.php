@extends('layouts.authenticated')

@section('pageTitle', 'Mon équipe de sécurité')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Gestion de votre équipe</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Mon équipe de sécurité</h2>
            <p class="mt-2 text-sm text-slate-600">Gérez les membres associés à vos dossiers. Les membres de l’équipe n’ont pas de compte utilisateur.</p>
        </div>
        <a href="{{ route('prestataire.safety-team.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
            Ajouter un membre
        </a>
    </div>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Recherche des membres">
        <form method="GET" action="{{ route('prestataire.safety-team') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
            <div>
                <label for="member-search" class="mb-1.5 block text-sm font-medium text-slate-700">Rechercher un membre par nom ou CIN</label>
                <input id="member-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Nom ou CIN" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Rechercher</button>
            <a href="{{ route('prestataire.safety-team') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Effacer</a>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Membres de l’équipe de sécurité">
        @if ($members->isEmpty())
            <div class="px-6 py-12 text-center">
                <h3 class="text-sm font-semibold text-slate-800">{{ ($filters['search'] ?? '') !== '' ? 'Aucun membre trouvé' : 'Aucun membre enregistré' }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ ($filters['search'] ?? '') !== '' ? 'Essayez une autre recherche.' : 'Ajoutez un membre pour commencer à gérer votre équipe.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Membre</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">CIN / Fonction</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Coordonnées</th>
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Document associé</th>
                            <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($members as $member)
                            <tr>
                                <td class="px-5 py-4 sm:px-6"><p class="text-sm font-semibold text-slate-800">{{ $member->name }}</p></td>
                                <td class="px-5 py-4 text-sm text-slate-600"><p>{{ $member->cin }}</p><p class="mt-1 text-xs">{{ $member->position }}</p></td>
                                <td class="px-5 py-4 text-sm text-slate-600"><p>{{ $member->email ?: 'E-mail non renseigné' }}</p><p class="mt-1">{{ $member->phone ?: 'Téléphone non renseigné' }}</p></td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('prestataire.documents.show', $member->document) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">{{ $member->document->title }}</a>
                                    @if ($member->document->reference_number)
                                        <p class="mt-1 text-xs text-slate-500">{{ $member->document->reference_number }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-slate-500">Statut : {{ $member->document->status }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($member->document->status !== \App\Models\Document::STATUS_VALIDATED)
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('prestataire.safety-team.edit', $member) }}" class="rounded-lg px-2.5 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">Modifier</a>
                                            <form method="POST" action="{{ route('prestataire.safety-team.destroy', $member) }}" data-confirm="Confirmer la suppression de ce membre de l’équipe de sécurité ?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg px-2.5 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Supprimer</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs font-medium text-slate-500">Dossier validé — lecture seule</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">{{ $members->links() }}</div>
        @endif
    </section>
@endsection
