@extends('layouts.authenticated')

@section('pageTitle', 'Examen du document')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route($document->status === 'pending' ? 'gestionnaire.documents.pending' : 'gestionnaire.documents.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour aux documents</a>
            <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $document->title }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $document->reference_number ?: 'Référence en attente' }}</p>
        </div>
        @include('shared.document-status-badge', ['status' => $document->status])
    </div>

    @if ($document->status === 'pending')
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900" role="status">
            <span class="mt-0.5 font-bold" aria-hidden="true">!</span>
            <p>
                @if ($document->revisions->isNotEmpty())
                    Nouvelle révision demandée — {{ $document->revisions->first()->typeLabel() }}. Consultez le motif et l’historique avant de poursuivre.
                @else
                    Ce document attend votre décision. Vérifiez les informations et la pièce jointe avant de poursuivre.
                @endif
            </p>
        </div>
    @elseif ($document->status === 'rejected')
        <section class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="rejection-reason-title">
            <h3 id="rejection-reason-title" class="text-sm font-semibold text-red-900">Motif du rejet</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $document->rejection_comment ?: 'Aucun motif détaillé n’a été communiqué.' }}</p>
        </section>
    @endif

    @if ($document->revisions->isNotEmpty())
        @php($latestRevision = $document->revisions->first())
        <section class="mb-6 rounded-2xl border border-orange-200 bg-orange-50 p-5 shadow-sm" aria-labelledby="revision-history-title">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-900">Nouvelle révision demandée</span>
                <span class="rounded-full border border-orange-200 bg-white px-3 py-1 text-xs font-semibold text-orange-900">{{ $latestRevision->typeLabel() }}</span>
            </div>
            <h3 id="revision-history-title" class="mt-3 text-base font-semibold text-orange-950">Motif de la nouvelle révision</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-orange-900">{{ $latestRevision->reason }}</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800">Modification le</dt>
                    <dd class="mt-1 text-orange-950">{{ $latestRevision->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800">Prestataire</dt>
                    <dd class="mt-1 text-orange-950">{{ $latestRevision->user?->name ?? 'Compte indisponible' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800">Membre concerné</dt>
                    <dd class="mt-1 text-orange-950">{{ $latestRevision->member_name ?: '—' }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-sm font-medium text-orange-950">{{ $latestRevision->description }}</p>

            <div class="mt-5 border-t border-orange-200 pt-4">
                <h4 class="text-sm font-semibold text-orange-950">Historique des modifications</h4>
                @if ($document->revisions->count() > 1)
                    <ol class="mt-3 space-y-3">
                        @foreach ($document->revisions->skip(1) as $revision)
                            <li class="rounded-xl border border-orange-200 bg-white p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-slate-900">{{ $revision->typeLabel() }}</span>
                                    <time class="text-xs text-slate-500" datetime="{{ $revision->created_at?->toIso8601String() }}">
                                        {{ $revision->created_at?->format('d/m/Y à H:i') }}
                                    </time>
                                </div>
                                <p class="mt-2 text-sm text-slate-700">{{ $revision->description }}</p>
                                <p class="mt-1 text-sm text-slate-600"><span class="font-semibold">Prestataire :</span> {{ $revision->user?->name ?? 'Compte indisponible' }}</p>
                                <p class="mt-1 text-sm text-slate-600"><span class="font-semibold">Membre concerné :</span> {{ $revision->member_name ?: '—' }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600"><span class="font-semibold">Motif :</span> {{ $revision->reason }}</p>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="mt-2 text-sm text-orange-900">Aucune modification précédente.</p>
                @endif
            </div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.8fr)]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="document-information-title">
                <h3 id="document-information-title" class="text-base font-semibold text-slate-900">Informations du document</h3>
                <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->company_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Référence</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->reference_number ?: 'Référence en attente' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de début</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->start_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de fin</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->end_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de transmission</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ ($document->submitted_at ?? $document->created_at)?->format('d/m/Y à H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statut actuel</dt>
                        <dd class="mt-1.5">@include('shared.document-status-badge', ['status' => $document->status])</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                        <dd class="mt-1.5 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $document->description ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="prestataire-information-title">
                <h3 id="prestataire-information-title" class="text-base font-semibold text-slate-900">Prestataire</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nom</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->prestataire?->name ?? 'Compte indisponible' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Adresse e-mail</dt>
                        <dd class="mt-1.5 break-all text-sm text-slate-800">{{ $document->prestataire?->email ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="safety-team-title">
                <h3 id="safety-team-title" class="text-base font-semibold text-slate-900">Équipe de sécurité associée</h3>
                @if ($document->safetyTeamMembers->isEmpty())
                    <p class="mt-3 text-sm text-slate-600">Aucun membre n’est associé à ce dossier.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Membre</th>
                                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">CIN / Fonction</th>
                                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Coordonnées</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($document->safetyTeamMembers as $member)
                                    <tr>
                                        <td class="px-3 py-3 text-sm font-medium text-slate-800">{{ $member->name }}</td>
                                        <td class="px-3 py-3 text-sm text-slate-600">{{ $member->cin }}<br>{{ $member->position }}</td>
                                        <td class="px-3 py-3 text-sm text-slate-600">{{ $member->email ?: '—' }}<br>{{ $member->phone ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if (is_array($document->custom_fields) && count($document->custom_fields) > 0)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="custom-information-title">
                    <h3 id="custom-information-title" class="text-base font-semibold text-slate-900">Informations complémentaires</h3>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($document->custom_fields as $name => $value)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $name }}</dt>
                                <dd class="mt-1 text-sm text-slate-800">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="attachment-title">
                <h3 id="attachment-title" class="text-base font-semibold text-slate-900">Pièce jointe</h3>
                @if ($document->attachment_path)
                    <p class="mt-2 text-sm leading-6 text-slate-600">Le fichier est conservé dans un espace privé et son accès est réservé aux rôles autorisés.</p>
                    <a href="{{ route('gestionnaire.documents.download', $document) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                        Télécharger la pièce jointe
                    </a>
                @else
                    <p class="mt-2 text-sm leading-6 text-slate-600">Aucun fichier n’a été joint à ce document.</p>
                @endif
            </section>

            @if ($document->status === 'pending')
                <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="review-actions-title">
                    <div>
                        <h3 id="review-actions-title" class="text-base font-semibold text-slate-900">Décision</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">La décision sera enregistrée et communiquée au prestataire.</p>
                    </div>
                    <form method="POST" action="{{ route('gestionnaire.documents.approve', $document) }}" data-confirm="Confirmer la validation de ce document ?">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                            Valider le document
                        </button>
                    </form>
                    <form method="POST" action="{{ route('gestionnaire.documents.reject', $document) }}" data-confirm="Confirmer le rejet de ce document ? Cette décision sera transmise au prestataire.">
                        @csrf
                        <div>
                            <label for="rejection-reason" class="mb-1.5 block text-sm font-medium text-slate-700">Motif obligatoire du rejet</label>
                            <textarea
                                id="rejection-reason"
                                name="reason"
                                rows="5"
                                minlength="10"
                                maxlength="2000"
                                required
                                aria-describedby="rejection-reason-help"
                                class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            >{{ old('reason') }}</textarea>
                            <p id="rejection-reason-help" class="mt-1 text-xs text-slate-500">De 10 à 2 000 caractères.</p>
                            @error('reason')
                                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-800 transition hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                            Rejeter le document
                        </button>
                    </form>
                </section>
            @elseif ($document->status === 'validated')
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5" aria-label="Informations de validation">
                    <h3 class="text-sm font-semibold text-emerald-900">Document validé</h3>
                    <p class="mt-2 text-sm text-emerald-800">
                        {{ $document->validated_at?->format('d/m/Y à H:i') ?? 'Date non disponible' }}
                        @if ($document->validatedBy)
                            par {{ $document->validatedBy->name }}
                        @endif
                    </p>
                </section>
            @endif
        </aside>
    </div>
@endsection
