@extends('layouts.authenticated')

@section('pageTitle', $company->name)

@section('content')
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="mb-6">
        <p class="text-sm font-medium text-slate-500">Entreprise</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $company->name }}</h2>
        <p class="mt-1 text-sm text-slate-600">Prestataire : {{ $company->prestataire?->name ?? '—' }}</p>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Informations générales de l’entreprise</h3>
                <p class="mt-1 text-sm text-slate-600">La validation des informations ne modifie pas le statut des documents.</p>
            </div>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                @switch($company->status)
                    @case('pending') bg-amber-100 text-amber-900 @break
                    @case('validated') bg-emerald-100 text-emerald-900 @break
                    @case('rejected') bg-red-100 text-red-800 @break
                    @default bg-slate-100 text-slate-700 @break
                @endswitch">
                @switch($company->status)
                    @case('pending') En attente @break
                    @case('validated') Validée @break
                    @case('rejected') Rejetée @break
                    @default Brouillon @break
                @endswitch
            </span>
        </div>

        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nom de l’entreprise</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $company->name }}</dd>
            </div>
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
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataire</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $company->prestataire?->name ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informations complémentaires</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $company->additional_info ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Soumise le</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $company->submitted_at?->format('d/m/Y à H:i') ?? '—' }}</dd>
            </div>
            @if ($company->validated_at)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Décision du Gestionnaire</dt>
                    <dd class="mt-1 text-sm text-slate-800">
                        {{ $company->validated_by ? $company->validatedBy?->name : '—' }}
                        · {{ $company->validated_at->format('d/m/Y à H:i') }}
                    </dd>
                </div>
            @endif
            @if ($company->rejection_reason)
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-red-800">Motif du rejet des informations</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-red-900">{{ $company->rejection_reason }}</dd>
                </div>
            @endif
        </dl>

        @if ($company->status === 'pending' && auth()->user()->hasPermission('entreprises.edit'))
            <form method="POST" action="{{ route('gestionnaire.companies.update', $company) }}" class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <div>
                    <label for="company-name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom de l’entreprise</label>
                    <input id="company-name" name="name" type="text" maxlength="255" required value="{{ old('name', $company->name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company-activity" class="mb-1.5 block text-sm font-semibold text-slate-700">Activité</label>
                    <input id="company-activity" name="activity" type="text" maxlength="255" value="{{ old('activity', $company->activity) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('activity')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company-assignment" class="mb-1.5 block text-sm font-semibold text-slate-700">Affectation</label>
                    <input id="company-assignment" name="assignment" type="text" maxlength="255" value="{{ old('assignment', $company->assignment) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('assignment')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company-contract-start-date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de début du contrat</label>
                    <input id="company-contract-start-date" name="contract_start_date" type="date" value="{{ old('contract_start_date', $company->contract_start_date?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('contract_start_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company-contract-end-date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de fin du contrat</label>
                    <input id="company-contract-end-date" name="contract_end_date" type="date" value="{{ old('contract_end_date', $company->contract_end_date?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('contract_end_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="company-additional-info" class="mb-1.5 block text-sm font-semibold text-slate-700">Informations complémentaires</label>
                    <textarea id="company-additional-info" name="additional_info" rows="3" maxlength="2000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('additional_info', $company->additional_info) }}</textarea>
                    @error('additional_info')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:col-span-2 sm:justify-self-start">Enregistrer les informations</button>
            </form>
        @endif

        @if ($company->status === 'pending' && $approvalBlockers !== [])
            <div id="company-approval-blockers" class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4" role="alert">
                <h4 class="text-sm font-semibold text-amber-950">Approbation finale impossible</h4>
                <p class="mt-1 text-sm text-amber-900">Tous les documents obligatoires et tout document facultatif déposé doivent être validés avant l’approbation de l’entreprise.</p>
                <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-amber-950">
                    @foreach ($approvalBlockers as $blocker)
                        <li><span class="font-semibold">{{ $blocker['label'] }}</span> — {{ $blocker['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($company->status === 'pending' && (auth()->user()->hasPermission('entreprises.validate') || auth()->user()->hasPermission('entreprises.reject')))
            <div class="mt-6 grid gap-4 border-t border-slate-200 pt-5 lg:grid-cols-2">
                @if (auth()->user()->hasPermission('entreprises.validate'))
                    <form method="POST" action="{{ route('gestionnaire.companies.approve', $company) }}" data-confirm="Valider explicitement les informations de cette entreprise ?">
                        @csrf
                        @if ($approvalBlockers === [])
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">
                                Valider les informations
                            </button>
                        @else
                            <button type="button" disabled aria-describedby="company-approval-blockers" class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">
                                Valider les informations
                            </button>
                        @endif
                    </form>
                @endif
                @if (auth()->user()->hasPermission('entreprises.reject'))
                    <form method="POST" action="{{ route('gestionnaire.companies.reject', $company) }}" data-confirm="Rejeter les informations de cette entreprise ?">
                        @csrf
                        <label for="company-rejection-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Motif du rejet des informations</label>
                        <textarea id="company-rejection-reason" name="reason" rows="3" required minlength="10" maxlength="2000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Expliquez les corrections attendues (10 caractères minimum).">{{ old('reason') }}</textarea>
                        <button type="submit" class="mt-2 inline-flex min-h-10 items-center justify-center rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">
                            Rejeter les informations
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Documents de l’entreprise</h3>
            <p class="mt-1 text-sm text-slate-600">Chaque document est examiné séparément, sans modifier les autres documents ni les informations de l’entreprise.</p>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse ($documentsForDisplay as $document)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-900">{{ $document->displayLabel() }}</h4>
                            <p class="mt-1 text-xs text-slate-500">Document obligatoire pour la validation finale</p>
                            <p class="mt-1 truncate max-w-[200px] sm:max-w-xs block text-xs text-slate-600" title="{{ $document->file_name ?: 'Aucun fichier déposé' }}">{{ $document->file_name ?: 'Aucun fichier déposé' }}</p>
                        </div>
                        @if (
                            auth()->user()->hasPermission('entreprises.validate')
                            && $document->getKey()
                            && $document->status === 'pending'
                        )
                            <label class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                                <input
                                    type="checkbox"
                                    name="document_ids[]"
                                    value="{{ $document->getKey() }}"
                                    form="bulk-company-document-form"
                                    class="bulk-company-document-checkbox h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-700"
                                >
                                Sélectionner
                            </label>
                        @endif
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide
                            @switch($document->status)
                                @case('pending') bg-amber-100 text-amber-900 @break
                                @case('validated') bg-emerald-100 text-emerald-900 @break
                                @case('rejected') bg-red-100 text-red-800 @break
                                @default bg-slate-200 text-slate-700 @break
                            @endswitch">
                            @switch($document->status)
                                @case('pending') En attente @break
                                @case('validated') Validé @break
                                @case('rejected') Rejeté @break
                                @default Brouillon @break
                            @endswitch
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        @if ($document->type !== \App\Models\CompanyDocument::TYPE_LISTE_PERSONNEL)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date d’expiration</dt>
                                <dd class="mt-1 text-sm text-slate-800">{{ $document->expires_at?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                        @endif
                        @if ($document->validated_at)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Décision</dt>
                                <dd class="mt-1 text-sm text-slate-800">
                                    {{ $document->validatedBy?->name ?? '—' }}
                                    · {{ $document->validated_at->format('d/m/Y à H:i') }}
                                </dd>
                            </div>
                        @endif
                        @if ($document->rejection_reason)
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-red-800">Motif du rejet</dt>
                                <dd class="mt-1 whitespace-pre-line text-sm text-red-900">{{ $document->rejection_reason }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($document->file_path && auth()->user()->hasPermission('entreprises.download'))
                        <div class="mt-4">
                            @include('shared.private-document-actions', [
                                'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                    ? route('gestionnaire.companies.documents.preview', [$company, $document])
                                    : null,
                                'downloadUrl' => route('gestionnaire.companies.documents.download', [$company, $document]),
                                'downloadLabel' => 'Télécharger le fichier',
                                'fileName' => $document->file_name,
                                'excelDataUrl' => in_array($document->getKey(), $excelDataDocumentIds, true)
                                    ? route('gestionnaire.companies.documents.imported-data', [$company, $document])
                                    : null,
                                'excelDataTitle' => $document->displayLabel(),
                            ])
                        </div>
                    @endif

                    @if ($document->status === 'pending' && (auth()->user()->hasPermission('entreprises.validate') || auth()->user()->hasPermission('entreprises.reject')))
                        <div class="mt-4 grid gap-3 border-t border-slate-200 pt-4">
                            @if (auth()->user()->hasPermission('entreprises.validate'))
                                <form method="POST" action="{{ route('gestionnaire.companies.documents.approve', ['company' => $company, 'document' => $document]) }}" data-confirm="Valider uniquement le document « {{ $document->label }} » ?">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">
                                        Valider ce document
                                    </button>
                                </form>
                            @endif
                            @if (auth()->user()->hasPermission('entreprises.reject'))
                                <form method="POST" action="{{ route('gestionnaire.companies.documents.reject', ['company' => $company, 'document' => $document]) }}" data-confirm="Rejeter uniquement le document « {{ $document->label }} » ?">
                                    @csrf
                                    <label for="document-reason-{{ $document->id }}" class="mb-1.5 block text-xs font-semibold text-slate-700">Motif du rejet (obligatoire)</label>
                                    <textarea id="document-reason-{{ $document->id }}" name="reason" rows="2" required minlength="10" maxlength="2000" class="form-control w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Expliquez les corrections attendues.">{{ old('reason') }}</textarea>
                                    <button type="submit" class="mt-2 inline-flex min-h-9 items-center justify-center rounded-xl bg-red-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700">
                                        Rejeter ce document
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500">Aucun document n’est associé à cette entreprise.</p>
            @endforelse
        </div>

        @if (auth()->user()->hasPermission('entreprises.validate'))
            <form
                id="bulk-company-document-form"
                method="POST"
                action="{{ route('gestionnaire.companies.documents.approve-selected', $company) }}"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
                @csrf
                <p class="text-sm text-slate-600">Sélectionnez uniquement les documents en attente de cette entreprise.</p>
                <button
                    id="validate-selected-documents"
                    type="submit"
                    disabled
                    class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600"
                >
                    Valider la sélection (0)
                </button>
            </form>
        @endif
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Historique des décisions</h3>
        <ol tabindex="0" aria-label="Historique des décisions" class="mt-4 max-h-56 space-y-3 overflow-y-auto overscroll-contain pr-2">
            @forelse ($company->reviewHistory as $entry)
                <li class="border-l-2 border-slate-200 pl-3">
                    <p class="text-sm font-semibold text-slate-800">
                        @switch($entry->action)
                            @case('created') Entreprise créée @break
                            @case('updated') Informations modifiées @break
                            @case('information_updated') Informations complétées ou corrigées @break
                            @case('submitted') Entreprise soumise @break
                            @case('validated') Informations validées @break
                            @case('rejected') Informations rejetées @break
                            @case('document_validated') Document validé @break
                            @case('document_rejected') Document rejeté @break
                            @case('document_updated') Document téléversé ou remplacé @break
                            @case('document_removed') Fichier courant retiré @break
                            @default {{ $entry->action }} @break
                        @endswitch
                        @if ($entry->document)
                            · {{ $entry->document->displayLabel() }}
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ $entry->created_at?->format('d/m/Y à H:i') ?? '—' }} @if ($entry->user) par {{ $entry->user->name }} @endif</p>
                    @if ($entry->reason)
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $entry->reason }}</p>
                    @endif
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucune décision enregistrée pour le moment.</li>
            @endforelse
        </ol>
    </section>

    @include('shared.company-document-version-history', [
        'documents' => $company->documents,
        'resource' => $company,
        'previewRoute' => 'gestionnaire.companies.documents.versions.preview',
        'downloadRoute' => 'gestionnaire.companies.documents.versions.download',
        'canDownload' => auth()->user()->hasPermission('entreprises.download'),
    ])
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkboxes = Array.from(document.querySelectorAll('.bulk-company-document-checkbox'));
            const submitButton = document.getElementById('validate-selected-documents');

            if (!submitButton) {
                return;
            }

            const updateSelection = () => {
                const count = checkboxes.filter((checkbox) => checkbox.checked).length;
                submitButton.disabled = count === 0;
                submitButton.textContent = `Valider la sélection (${count})`;
            };

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
            updateSelection();
        });
    </script>
    @include('shared.company-list-import-modal')
@endsection
