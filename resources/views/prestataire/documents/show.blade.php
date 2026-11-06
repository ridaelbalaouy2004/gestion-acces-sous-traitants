@extends('layouts.authenticated')

@section('pageTitle', 'Détail du document')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('prestataire.documents.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à mes documents</a>
            <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $document->title }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $document->reference_number ?: 'Référence en attente' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @include('shared.document-status-badge', ['status' => $document->status])
            @if ($document->status === 'rejected')
                <a href="{{ route('prestataire.documents.edit', $document) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">
                    Corriger le document
                </a>
            @endif
        </div>
    </div>

    @if ($document->status === 'rejected')
        <section class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="rejection-reason-title">
            <h3 id="rejection-reason-title" class="text-sm font-semibold text-red-900">Motif du rejet</h3>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $document->rejection_comment ?: 'Aucun motif détaillé n’a été communiqué.' }}</p>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.8fr)]">
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
                    <dd class="mt-1.5 text-sm text-slate-800">{{ ($document->submitted_at ?? $document->created_at)?->format('d/m/Y \à H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</dt>
                    <dd class="mt-1.5">@include('shared.document-status-badge', ['status' => $document->status])</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1.5 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $document->description ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="attachment-title">
                <h3 id="attachment-title" class="text-base font-semibold text-slate-900">Fichier joint</h3>
                @if ($document->attachment_path)
                    <p class="mt-2 text-sm leading-6 text-slate-600">Le fichier est conservé dans un espace privé et accessible uniquement depuis votre compte.</p>
                    <a href="{{ route('prestataire.documents.download', $document) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100">
                        Télécharger le fichier
                    </a>
                @else
                    <p class="mt-2 text-sm leading-6 text-slate-600">Aucun fichier n’a été joint à ce document.</p>
                @endif
            </section>

            @if (is_array($document->custom_fields) && count($document->custom_fields) > 0)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="custom-information-title">
                    <h3 id="custom-information-title" class="text-base font-semibold text-slate-900">Informations complémentaires</h3>
                    <dl class="mt-4 space-y-4">
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
    </div>
@endsection
