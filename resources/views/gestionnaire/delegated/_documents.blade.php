@php
    $previewRoute = match ($kind) {
        'company' => 'gestionnaire.companies.documents.preview',
        'employee' => 'gestionnaire.employees.documents.preview',
        'machinery' => 'gestionnaire.machinery.documents.preview',
        'tooling' => 'gestionnaire.toolings.documents.preview',
    };
    $versionHistory = match ($kind) {
        'company' => [
            'previewRoute' => 'gestionnaire.companies.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.companies.documents.versions.download',
            'permission' => 'entreprises.download',
        ],
        'employee' => [
            'previewRoute' => 'gestionnaire.employees.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.employees.documents.versions.download',
            'permission' => 'employees.download',
        ],
        'machinery' => [
            'previewRoute' => 'gestionnaire.machinery.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.machinery.documents.versions.download',
            'permission' => 'machinery.download',
        ],
        'tooling' => [
            'previewRoute' => 'gestionnaire.toolings.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.toolings.documents.versions.download',
            'permission' => 'toolings.download',
        ],
    };
@endphp

@if ($kind === 'company')
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach ($documents as $document)
            @php
                $hasFile = filled($document->file_path);
                $needsRenewal = $hasFile
                    && $document->type !== \App\Models\CompanyDocument::TYPE_LISTE_PERSONNEL
                    && $document->expires_at !== null
                    && $document->expires_at->lt(today());
                [$fileStatusLabel, $fileStatusClasses] = match (true) {
                    ! $hasFile => ['Manquant', 'bg-rose-100 text-rose-800'],
                    $needsRenewal => ['À renouveler', 'bg-amber-100 text-amber-900'],
                    default => ['Déposé', 'bg-emerald-100 text-emerald-900'],
                };
            @endphp
            <div class="flex min-w-0 items-start gap-2">
                @if ($document->exists && $document->status === \App\Models\CompanyDocument::STATUS_PENDING && auth()->user()->hasPermission('entreprises.validate'))
                    <label class="mt-3 inline-flex min-h-9 shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            name="document_ids[]"
                            value="{{ $document->getKey() }}"
                            form="delegated-company-document-form-{{ $record->id }}"
                            data-delegated-company-document-checkbox
                            class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-700"
                        >
                        Sélectionner
                    </label>
                @endif
            <details name="delegated-company-documents-{{ $record->id }}" class="group/document min-w-0 flex-1 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] marker:hidden">
                    <span class="min-w-0 break-words text-sm font-semibold text-slate-900">{{ $document->displayLabel() }}</span>
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/document:rotate-180" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </summary>
                <div class="space-y-4 border-t border-slate-200 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="mt-1 text-xs text-slate-500">Obligatoire pour la validation de l’entreprise</p>
                        <p class="mt-1 truncate max-w-[200px] sm:max-w-xs block text-xs text-slate-500" title="{{ $document->file_name ?: 'Aucun fichier' }}">{{ $document->file_name ?: 'Aucun fichier' }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $fileStatusClasses }}">{{ $fileStatusLabel }}</span>
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
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($document->file_path)
                        @include('shared.private-document-actions', [
                            'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                ? route($previewRoute, [$record, $document])
                                : null,
                            'downloadUrl' => route($downloadRoute, [$record, $document]),
                            'fileName' => $document->file_name,
                            'excelDataUrl' => $kind === 'company'
                                && in_array($document->getKey(), $excelDataDocumentIds ?? [], true)
                                    ? route('gestionnaire.delegated.companies.documents.imported-data', [$prestataire, $record, $document])
                                    : null,
                            'excelDataTitle' => $document->displayLabel(),
                            'theme' => 'green',
                        ])
                    @endif
                </div>
                @include('shared.document-rejection-reason', ['document' => $document])
                <form method="POST" action="{{ route($uploadRoute, [$prestataire, $record, $document->type]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <x-file-upload
                        name="document"
                        :accept="in_array($document->type, \App\Models\CompanyDocument::hseTypes(), true) ? '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png' : '.pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx'"
                        :max-bytes="10485760"
                    />
                    @if ($document->type !== \App\Models\CompanyDocument::TYPE_LISTE_PERSONNEL)
                        <div>
                            <label for="expires-{{ $record->id }}-{{ $document->type }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date d’expiration</label>
                            <input id="expires-{{ $record->id }}-{{ $document->type }}" type="date" name="expires_at" value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}" class="form-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                        </div>
                    @endif
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">Téléverser / remplacer</button>
                </form>
                </div>
            </details>
            </div>
        @endforeach
    </div>
    @if (auth()->user()->hasPermission('entreprises.validate'))
        <form
            id="delegated-company-document-form-{{ $record->id }}"
            method="POST"
            action="{{ route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $record]) }}"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
        >
            @csrf
            <p class="text-sm text-slate-600">Sélectionnez les documents en attente que vous avez déposés pour ce Prestataire.</p>
            <button
                type="submit"
                data-delegated-company-document-submit
                disabled
                class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#123e36] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600"
            >
                Valider tout (0)
            </button>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('delegated-company-document-form-{{ $record->id }}');
                const submitButton = form?.querySelector('[data-delegated-company-document-submit]');

                if (!form || !submitButton) {
                    return;
                }

                const checkboxes = Array.from(document.querySelectorAll(`[form="${form.id}"][data-delegated-company-document-checkbox]`));
                const updateSelection = () => {
                    const count = checkboxes.filter((checkbox) => checkbox.checked).length;
                    submitButton.disabled = count === 0;
                    submitButton.textContent = `Valider tout (${count})`;
                };

                checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
                updateSelection();
            });
        </script>
    @endif
    @include('shared.company-document-version-history', [
        'documents' => $documents,
        'resource' => $record,
        'previewRoute' => $versionHistory['previewRoute'],
        'downloadRoute' => $versionHistory['downloadRoute'],
        'canDownload' => auth()->user()->hasPermission($versionHistory['permission']),
        'theme' => 'green',
    ])
@elseif ($kind === 'employee')
    <section class="space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Documents de l’employé</h3>
                <p class="mt-1 text-sm text-slate-600">Le statut de chaque document est indépendant de celui de l’employé.</p>
            </div>
            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $documents->count() }}</span>
        </div>
        @foreach ($documents as $document)
            <details name="delegated-employee-documents-{{ $record->id }}" class="group/document overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] sm:px-6 marker:hidden">
                    <span class="min-w-0 break-words font-semibold text-slate-900">{{ $document->displayLabel() }}</span>
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/document:rotate-180" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </summary>
                <div class="space-y-4 border-t border-slate-100 p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="mt-1 text-sm text-slate-500">
                            @if (in_array($document->type, \App\Models\CompanyEmployeeDocument::optionalTypes(), true))
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Facultatif</span>
                                <span class="ml-2">Si téléversé : validation et validité requises</span>
                            @elseif (in_array($document->type, \App\Models\CompanyEmployeeDocument::requiredTypes(), true))
                                Obligatoire pour la soumission et la validation
                            @else
                                Facultatif
                            @endif
                        </p>
                    </div>
                    @include('shared.company-employees.status', ['status' => $document->status])
                </div>
                @if ($document->file_path)
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        @include('shared.private-document-actions', [
                            'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                ? route($previewRoute, [$record, $document])
                                : null,
                            'downloadUrl' => route($downloadRoute, [$record, $document]),
                            'downloadLabel' => 'Télécharger le document',
                            'fileName' => $document->file_name,
                            'theme' => 'green',
                        ])
                        @if ($document->expires_at)
                            <span class="text-slate-600">Expire le {{ $document->expires_at->format('d/m/Y') }}</span>
                        @endif
                    </div>
                @endif
                @include('shared.document-rejection-reason', ['document' => $document])
                <form method="POST" action="{{ route($uploadRoute, [$prestataire, $record, $document->type]) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="document-{{ $record->id }}-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $document->file_path ? 'Remplacer le fichier' : 'Téléverser le fichier' }}</label>
                            <x-file-upload
                                id="document-{{ $record->id }}-{{ $document->type }}"
                                name="document"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                :max-bytes="10485760"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">PDF, JPG, JPEG ou PNG · 10 Mo maximum.</p>
                            @error('document')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="expires-{{ $record->id }}-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’expiration <span class="font-normal text-slate-500">(si applicable)</span></label>
                            <input id="expires-{{ $record->id }}-{{ $document->type }}" name="expires_at" type="date" value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            @error('expires_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#164f43] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">Enregistrer le document</button>
                </form>
                </div>
            </details>
        @endforeach
        @include('shared.company-document-version-history', [
            'documents' => $documents,
            'resource' => $record,
            'previewRoute' => $versionHistory['previewRoute'],
            'downloadRoute' => $versionHistory['downloadRoute'],
            'canDownload' => auth()->user()->hasPermission($versionHistory['permission']),
            'theme' => 'green',
        ])
    </section>
@elseif (in_array($kind, ['machinery', 'tooling'], true))
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            @if ($kind === 'machinery')
                <h3 class="text-lg font-bold text-slate-900">Documents de l’engin</h3>
            @else
                <h3 class="text-lg font-bold text-slate-900">Documents de l’outillage</h3>
            @endif
            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $documents->count() }}</span>
        </div>
        @if ($kind === 'machinery')
            <p class="text-sm text-slate-600">Ces pièces sont facultatives. Chaque statut évolue indépendamment de l’engin et des autres documents.</p>
        @else
            <p class="text-sm text-slate-600">Fiche technique, justificatif d’acquisition, certificats et autres pièces facultatives. Remplacer un fichier conserve ses versions antérieures.</p>
        @endif
        @forelse ($documents as $document)
            <details name="delegated-{{ $kind }}-documents-{{ $record->id }}" class="group/document overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] sm:px-6 marker:hidden">
                    <span class="min-w-0 break-words font-semibold text-slate-900">{{ $document->label }}</span>
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/document:rotate-180" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </summary>
                <div class="space-y-4 border-t border-slate-100 p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        @if ($document->file_path)
                            <div class="mt-2">
                                @include('shared.private-document-actions', [
                                    'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                        ? route($previewRoute, [$record, $document])
                                        : null,
                                    'downloadUrl' => route($downloadRoute, [$record, $document]),
                                    'downloadLabel' => 'Télécharger le document',
                                    'fileName' => $document->file_name,
                                    'theme' => 'green',
                                ])
                            </div>
                        @else
                            <p class="mt-1 text-sm text-slate-500">Aucun fichier enregistré.</p>
                        @endif
                    </div>
                    @if ($kind === 'machinery')
                        @include('shared.company-machinery.status', ['status' => $document->status])
                    @else
                        @include('shared.company-toolings.status', ['status' => $document->status])
                    @endif
                </div>
                @if ($document->issued_at || $document->expires_at)
                    <p class="mt-3 text-sm text-slate-600">
                        @if ($document->issued_at)Émis le {{ $document->issued_at->format('d/m/Y') }}@endif
                        @if ($document->issued_at && $document->expires_at) · @endif
                        @if ($document->expires_at)Expire le {{ $document->expires_at->format('d/m/Y') }}@endif
                    </p>
                @endif
                @include('shared.document-rejection-reason', ['document' => $document])
                <form method="POST" action="{{ route($uploadRoute, [$prestataire, $record, $document->type]) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="document-{{ $record->id }}-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $document->file_path ? 'Remplacer le fichier' : 'Téléverser le fichier' }}</label>
                            <x-file-upload
                                id="document-{{ $record->id }}-{{ $document->type }}"
                                name="document"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                :max-bytes="10485760"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">PDF, JPG ou PNG · 10 Mo maximum.</p>
                            @error('document')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="issued-{{ $record->id }}-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’émission</label>
                            <input id="issued-{{ $record->id }}-{{ $document->type }}" name="issued_at" type="date" value="{{ old('issued_at', $document->issued_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            @error('issued_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="expires-{{ $record->id }}-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’expiration</label>
                            <input id="expires-{{ $record->id }}-{{ $document->type }}" name="expires_at" type="date" value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            @error('expires_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#164f43] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">Enregistrer le document</button>
                </form>
                </div>
            </details>
        @empty
            <p class="text-sm text-slate-500">Aucun document n’est défini pour ce dossier.</p>
        @endforelse
        @include('shared.company-document-version-history', [
            'documents' => $documents,
            'resource' => $record,
            'previewRoute' => $versionHistory['previewRoute'],
            'downloadRoute' => $versionHistory['downloadRoute'],
            'canDownload' => auth()->user()->hasPermission($versionHistory['permission']),
            'theme' => 'green',
        ])

        @if ($kind === 'tooling')
            <article class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 shadow-sm sm:p-6">
                <h4 class="font-semibold text-slate-900">Ajouter un autre document</h4>
                <form method="POST" action="{{ route('gestionnaire.delegated.toolings.documents.custom.store', [$prestataire, $record]) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="custom-document-label-{{ $record->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom du document <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="custom-document-label-{{ $record->id }}" name="label" type="text" maxlength="100" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                        @error('label')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="custom-document-file-{{ $record->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Fichier <span class="text-red-600" aria-hidden="true">*</span></label>
                        <x-file-upload
                            id="custom-document-file-{{ $record->id }}"
                            name="document"
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            :max-bytes="10485760"
                            required
                        />
                        <p class="mt-1 text-xs text-slate-500">PDF, JPG ou PNG · 10 Mo maximum.</p>
                        @error('document')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#164f43] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43] sm:col-span-2 sm:justify-self-start">Ajouter le document</button>
                </form>
            </article>
        @endif
    </section>
@endif
