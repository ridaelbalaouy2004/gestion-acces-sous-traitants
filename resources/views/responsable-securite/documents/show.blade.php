@extends('layouts.authenticated')

@section('pageTitle', 'Détail du document validé')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('responsable-securite.documents.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour aux documents validés</a>
            <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $document->title }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $document->reference_number ?: 'Référence non renseignée' }}</p>
        </div>
        @include('shared.document-status-badge', ['status' => $document->status])
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.8fr)]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="document-information-title">
                <h3 id="document-information-title" class="text-base font-semibold text-slate-900">Informations du document</h3>
                <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Référence</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->reference_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Titre</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->title }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</dt>
                        <dd class="mt-1.5">@include('shared.document-status-badge', ['status' => $document->status])</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de transmission</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ ($document->submitted_at ?? $document->created_at)?->format('d/m/Y à H:i') ?? '—' }}</dd>
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
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de validation</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->validated_at?->format('d/m/Y à H:i') ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                        <dd class="mt-1.5 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $document->description ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="company-information-title">
                <h3 id="company-information-title" class="text-base font-semibold text-slate-900">Entreprise</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nom de l’entreprise</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->company_name ?: '—' }}</dd>
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
                <h3 id="safety-team-title" class="text-base font-semibold text-slate-900">Équipe de sécurité validée</h3>
                @if ($document->safetyTeamMembers->isEmpty())
                    <p class="mt-3 text-sm text-slate-600">Aucun membre n’est associé à ce document validé.</p>
                @else
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($document->safetyTeamMembers as $member)
                            <div class="rounded-xl border border-slate-100 p-4">
                                <dt class="text-sm font-semibold text-slate-900">{{ $member->name }}</dt>
                                <dd class="mt-2 space-y-1 text-sm text-slate-600">
                                    <p>CIN : {{ $member->cin }}</p>
                                    <p>Fonction : {{ $member->position }}</p>
                                    <p>E-mail : {{ $member->email ?: '—' }}</p>
                                    <p>Téléphone : {{ $member->phone ?: '—' }}</p>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="validator-information-title">
                <h3 id="validator-information-title" class="text-base font-semibold text-slate-900">Gestionnaire ayant validé le document</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nom</dt>
                        <dd class="mt-1.5 text-sm text-slate-800">{{ $document->validatedBy?->name ?? 'Compte indisponible' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Adresse e-mail</dt>
                        <dd class="mt-1.5 break-all text-sm text-slate-800">{{ $document->validatedBy?->email ?? '—' }}</dd>
                    </div>
                </dl>
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

        <aside>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="attachment-title">
                <h3 id="attachment-title" class="text-base font-semibold text-slate-900">Pièce jointe</h3>
                @if ($document->attachment_path)
                    <p class="mt-2 break-all text-sm text-slate-600">{{ basename($document->attachment_path) }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Le fichier est conservé dans un espace privé.</p>
                    <a href="{{ route('responsable-securite.documents.download', $document) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100">
                        Télécharger la pièce jointe
                    </a>
                @else
                    <p class="mt-2 text-sm leading-6 text-slate-600">Aucun fichier n’a été joint à ce document.</p>
                @endif
            </section>
        </aside>
    </div>
@endsection
