@extends('layouts.authenticated')

@section('pageTitle', $company->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-500">Détails de l’entreprise</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $company->name }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('prestataire.companies.edit', $company) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
            @if (in_array($company->status, ['draft', 'rejected'], true))
                <form method="POST" action="{{ route('prestataire.companies.submit', $company) }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Soumettre pour validation</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid gap-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Informations</h3>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activité</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $company->activity ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Affectation</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $company->assignment ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de début du contrat</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $company->contract_start_date?->format('d/m/Y') ?? 'Non renseignée' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de fin du contrat</dt>
                    <dd class="mt-1 text-sm text-slate-800">{{ $company->contract_end_date?->format('d/m/Y') ?? 'Non renseignée' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informations complémentaires</dt>
                    <dd class="mt-1 text-sm text-slate-800 whitespace-pre-line">{{ $company->additional_info ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</dt>
                    <dd class="mt-1 text-sm">
                        @switch($company->status)
                            @case('draft') <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Brouillon</span> @break
                            @case('pending') <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">En attente</span> @break
                            @case('validated') <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900">Validée</span> @break
                            @default <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Rejetée</span>
                        @endswitch
                    </dd>
                </div>
                @if ($company->rejection_reason)
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Motif de rejet</dt>
                        <dd class="mt-1 text-sm text-red-700">{{ $company->rejection_reason }}</dd>
                    </div>
                @endif
            </dl>
        </section>

    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Documents requis</h3>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @foreach ($documentsForDisplay as $document)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $document->displayLabel() }}</p>
                            <p class="mt-1 text-xs text-slate-500">Obligatoire pour la validation de l’entreprise</p>
                            <p class="mt-1 truncate max-w-[200px] sm:max-w-xs block text-xs text-slate-500" title="{{ $document->file_name ?: 'Aucun fichier' }}">{{ $document->file_name ?: 'Aucun fichier' }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide
                            @switch($document->status)
                                @case('pending') bg-amber-100 text-amber-900 @break
                                @case('validated') bg-emerald-100 text-emerald-900 @break
                                @case('rejected') bg-red-100 text-red-800 @break
                                @default bg-slate-200 text-slate-700 @break
                            @endswitch">
                            {{ $document->status }}
                        </span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($document->file_path)
                            @include('shared.private-document-actions', [
                                'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                    ? route('prestataire.companies.documents.preview', [$company, $document])
                                    : null,
                                'downloadUrl' => route('prestataire.companies.documents.download', ['company' => $company, 'document' => $document]),
                                'fileName' => $document->file_name,
                                'excelDataUrl' => in_array($document->getKey(), $excelDataDocumentIds, true)
                                    ? route('prestataire.companies.documents.imported-data', [$company, $document])
                                    : null,
                                'excelDataTitle' => $document->displayLabel(),
                            ])
                        @endif
                    </div>
                    @include('shared.document-rejection-reason', ['document' => $document])
                    @if ($document->file_path && $document->status !== 'validated')
                        <form method="POST" action="{{ route('prestataire.companies.documents.remove', [$company, $document]) }}" data-confirm="Le fichier courant sera supprimé définitivement du stockage privé. Les versions antérieures et l’historique seront conservés. Continuer ?" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-rose-200 bg-rose-50/70 px-3 py-1.5 text-xs font-semibold text-rose-800 transition hover:border-rose-300 hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                    <path d="M3.5 6.5h17M9 6.5V4.75A1.25 1.25 0 0 1 10.25 3.5h3.5A1.25 1.25 0 0 1 15 4.75V6.5m3.75 0-.8 13.05a1.5 1.5 0 0 1-1.5 1.45h-8.9a1.5 1.5 0 0 1-1.5-1.45L5.25 6.5M10 10v7m4-7v7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span>Retirer le fichier courant</span>
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('prestataire.companies.documents.store', ['company' => $company, 'documentType' => $document->type]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <x-file-upload
                            name="document"
                            :accept="in_array($document->type, \App\Models\CompanyDocument::hseTypes(), true) ? '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png' : '.pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx'"
                            :max-bytes="10485760"
                        />
                        @if ($document->type !== \App\Models\CompanyDocument::TYPE_LISTE_PERSONNEL)
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date d’expiration</label>
                                <input type="date" name="expires_at" value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}" class="form-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            </div>
                        @endif
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">Téléverser / remplacer</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Historique des décisions</h3>
        <ol tabindex="0" aria-label="Historique des décisions" class="mt-4 max-h-56 space-y-3 overflow-y-auto overscroll-contain pr-2">
            @forelse ($company->reviewHistory as $entry)
                <li class="border-l border-slate-200 pl-3">
                    <p class="text-sm font-semibold text-slate-800">
                        @switch($entry->action)
                            @case('created') Entreprise créée @break
                            @case('updated') Informations modifiées @break
                            @case('submitted') Entreprise soumise @break
                            @case('validated') Informations validées @break
                            @case('rejected') Informations rejetées @break
                            @case('document_validated') Document validé @break
                            @case('document_rejected') Document rejeté @break
                            @case('document_updated') Document téléversé ou remplacé @break
                            @case('document_removed') Fichier courant retiré @break
                            @default {{ str_replace('_', ' ', $entry->action) }} @break
                        @endswitch
                        @if ($entry->document) · {{ $entry->document->displayLabel() }} @endif
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ $entry->created_at?->format('d/m/Y à H:i') ?? '—' }} @if ($entry->user) par {{ $entry->user->name }} @endif</p>
                    @if ($entry->reason)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $entry->reason }}</p>@endif
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucun historique pour le moment.</li>
            @endforelse
        </ol>
    </section>

    @include('shared.company-document-version-history', [
        'documents' => $company->documents,
        'resource' => $company,
        'previewRoute' => 'prestataire.companies.documents.versions.preview',
        'downloadRoute' => 'prestataire.companies.documents.versions.download',
        'canDownload' => true,
    ])
    @include('shared.company-list-import-modal')
@endsection
