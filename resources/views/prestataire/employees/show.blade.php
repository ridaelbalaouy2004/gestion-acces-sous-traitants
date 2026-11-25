@extends('layouts.authenticated')

@section('pageTitle', 'Fiche employé')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('prestataire.employees.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à mon équipe</a>
                <p class="mt-5 text-sm font-medium text-slate-500">{{ $employee->company->name }}</p>
                @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: 'Employé à compléter')
                <div class="mt-2 flex items-center gap-4">
                    @include('shared.company-employees.avatar', [
                        'employee' => $employee,
                        'employeeName' => $employeeName,
                        'photoUrl' => route('prestataire.employees.photo', $employee),
                        'sizeClass' => 'size-20',
                    ])
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $employeeName }}</h2>
                        <div class="mt-3">@include('shared.company-employees.status', ['status' => $employee->status])</div>
                    </div>
                </div>
            </div>
            @if (in_array($employee->status, ['draft', 'rejected'], true))
                <a href="{{ route('prestataire.employees.edit', $employee) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier les informations</a>
            @endif
        </div>

        @if ($employee->rejection_reason)
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-red-900">Motif du rejet de l’employé</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $employee->rejection_reason }}</p>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Informations de l’employé</h3>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prénom</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->first_name ?: 'À compléter' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nom</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->last_name ?: 'À compléter' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">CIN</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->cin ?: 'À compléter' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fonction</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->position ?: 'À compléter' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Numéro de cadenas</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->locker_number ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dernière soumission</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->submitted_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            </dl>
            @if (in_array($employee->status, ['draft', 'rejected'], true))
                <form method="POST" action="{{ route('prestataire.employees.submit', $employee) }}" class="mt-6 border-t border-slate-100 pt-5">
                    @csrf
                    <p class="mb-3 text-sm text-slate-600">Avant l’envoi, complétez les informations obligatoires et téléversez les documents requis. Chaque document sera examiné séparément.</p>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">{{ $employee->status === 'rejected' ? 'Renvoyer après correction' : 'Soumettre à validation' }}</button>
                </form>
            @endif
        </section>

        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Documents de l’employé</h3>
                <p class="mt-1 text-sm text-slate-600">Le statut de chaque document est indépendant de celui de l’employé.</p>
            </div>
            @foreach ($documentsForDisplay as $document)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="font-semibold text-slate-900">{{ $document->displayLabel() }}</h4>
                            <p class="mt-1 text-sm text-slate-500">
                                @if (in_array($document->type, $optionalDocumentTypes, true))
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Facultatif</span>
                                    <span class="ml-2">Si téléversé : validation et validité requises</span>
                                @elseif (in_array($document->type, $requiredDocumentTypes, true))
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
                                    ? route('prestataire.employees.documents.preview', [$employee, $document])
                                    : null,
                                'downloadUrl' => route('prestataire.employees.documents.download', [$employee, $document]),
                                'downloadLabel' => 'Télécharger le document',
                                'fileName' => $document->file_name,
                            ])
                            @if ($document->expires_at)
                                <span class="text-slate-600">Expire le {{ $document->expires_at->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    @endif

                    @include('shared.document-rejection-reason', ['document' => $document])

                    @if ($document->file_path && $document->status !== 'validated')
                        <form method="POST" action="{{ route('prestataire.employees.documents.remove', [$employee, $document]) }}" data-confirm="Le fichier courant sera supprimé définitivement du stockage privé. Les versions antérieures et l’historique seront conservés. Continuer ?" class="mt-4">
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

                    <form method="POST" action="{{ route('prestataire.employees.documents.store', [$employee, $document->type]) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="document-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $document->file_path ? 'Remplacer le fichier' : 'Téléverser le fichier' }}</label>
                                <x-file-upload
                                    id="document-{{ $document->type }}"
                                    name="document"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    :max-bytes="10485760"
                                    required
                                />
                                <p class="mt-1 text-xs text-slate-500">PDF, JPG, JPEG ou PNG · 10 Mo maximum.</p>
                                @error('document')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="expires-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’expiration <span class="font-normal text-slate-500">(si applicable)</span></label>
                                <input id="expires-{{ $document->type }}" name="expires_at" type="date" value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                                @error('expires_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100">Enregistrer le document</button>
                    </form>
                </article>
            @endforeach
        </section>

        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Formations</h3>
                    <p class="mt-1 text-sm text-slate-600">Les formations complètent la fiche de l’employé sans remplacer ses documents obligatoires.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('prestataire.employees.trainings.store', $employee) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                @csrf
                <h4 class="text-base font-semibold text-slate-900">Ajouter une formation</h4>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="training-title" class="mb-1.5 block text-sm font-semibold text-slate-700">Intitulé de la formation <span class="text-red-600">*</span></label>
                        <input id="training-title" name="title" type="text" maxlength="255" value="{{ old('title') }}" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                        @error('title')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="training-provider" class="mb-1.5 block text-sm font-semibold text-slate-700">Organisme de formation</label>
                        <input id="training-provider" name="provider" type="text" maxlength="255" value="{{ old('provider') }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                        @error('provider')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="training-date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de formation</label>
                        <input id="training-date" name="training_date" type="date" value="{{ old('training_date') }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                        @error('training_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="training-expires-at" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’expiration / validité</label>
                        <input id="training-expires-at" name="expires_at" type="date" value="{{ old('expires_at') }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                        @error('expires_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="training-description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description ou remarques</label>
                        <textarea id="training-description" name="description" rows="3" maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('description') }}</textarea>
                        @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="training-certificate" class="mb-1.5 block text-sm font-semibold text-slate-700">Justificatif (facultatif)</label>
                        <x-file-upload
                            id="training-certificate"
                            name="certificate"
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            :max-bytes="10485760"
                        />
                        <p class="mt-1 text-xs text-slate-500">PDF, JPG, JPEG ou PNG · 10 Mo maximum.</p>
                        @error('certificate')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button type="submit" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Ajouter une formation</button>
            </form>

            <div id="employee-training-cards" class="grid min-w-0 grid-cols-1 gap-4 min-[480px]:grid-cols-2 md:grid-cols-3 2xl:grid-cols-4">
                @forelse ($employee->trainings as $training)
                    @php($isEditingTraining = (string) old('_training_edit_id') === (string) $training->id)
                    <article id="employee-training-card-{{ $training->id }}" data-training-card class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div data-training-summary>
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                <div>
                                    <h4 class="break-words text-base font-semibold text-slate-900">{{ $training->title }}</h4>
                                    @if ($training->isExpired())
                                        <span class="mt-2 inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Validité expirée</span>
                                    @endif
                                </div>
                                <div class="flex flex-col items-start gap-2 xl:items-end">
                                    @if ($training->file_path)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Certificat fourni</span>
                                        @include('shared.private-document-actions', [
                                            'previewUrl' => in_array($training->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                                ? route('prestataire.employees.trainings.preview', [$employee, $training])
                                                : null,
                                            'downloadUrl' => route('prestataire.employees.trainings.download', [$employee, $training]),
                                            'downloadLabel' => 'Télécharger le justificatif',
                                            'fileName' => $training->file_name,
                                        ])
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Aucun certificat fourni</span>
                                    @endif
                                </div>
                            </div>
                            <dl class="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
                                <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Organisme</dt><dd class="mt-1 break-words text-sm text-slate-800">{{ $training->provider ?: 'Non renseigné' }}</dd></div>
                                <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de formation</dt><dd class="mt-1 text-sm text-slate-800">{{ $training->training_date?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
                                <div class="min-w-0"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date d’expiration / validité</dt><dd class="mt-1 text-sm text-slate-800">{{ $training->expires_at?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
                                @if ($training->description)
                                    <div class="min-w-0 xl:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description ou remarques</dt><dd class="mt-1 whitespace-pre-line break-words text-sm text-slate-800">{{ $training->description }}</dd></div>
                                @endif
                            </dl>

                            @if ($training->versions->count() > 1)
                                <div class="mt-4 border-t border-slate-100 pt-4">
                                    <h5 class="text-sm font-semibold text-slate-800">Justificatifs précédents</h5>
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($training->versions->where('is_current', false) as $version)
                                            <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                <span class="truncate max-w-[200px] sm:max-w-xs block text-slate-600" title="{{ $version->file_name }}">{{ $version->file_name }} · {{ $version->uploaded_at?->format('d/m/Y') ?? '—' }}</span>
                                                <a href="{{ route('prestataire.employees.trainings.versions.download', [$employee, $training, $version]) }}" class="font-semibold text-blue-700 hover:text-blue-900">Télécharger</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                                <button
                                    type="button"
                                    aria-controls="training-edit-form-{{ $training->id }}"
                                    aria-expanded="{{ $isEditingTraining ? 'true' : 'false' }}"
                                    data-training-edit-open
                                    class="inline-flex min-h-10 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100"
                                >
                                    Modifier
                                </button>
                                <form method="POST" action="{{ route('prestataire.employees.trainings.destroy', [$employee, $training]) }}" data-confirm="Supprimer cette formation ? Elle sera retirée de la fiche mais son historique sera conservé.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-100">Supprimer</button>
                                </form>
                            </div>
                        </div>

                        <form
                            id="training-edit-form-{{ $training->id }}"
                            @if (! $isEditingTraining) hidden @endif
                            data-training-edit-form
                            method="POST"
                            action="{{ route('prestataire.employees.trainings.update', [$employee, $training]) }}"
                            enctype="multipart/form-data"
                            class="mt-5 grid min-w-0 gap-4 border-t border-slate-100 pt-5 xl:grid-cols-2"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_training_edit_id" value="{{ $training->id }}">
                            <div class="xl:col-span-2">
                                <h5 class="text-sm font-semibold text-slate-800">Modifier la formation</h5>
                            </div>
                            <div>
                                <label for="training-title-{{ $training->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Intitulé de la formation <span class="text-red-600">*</span></label>
                                <input id="training-title-{{ $training->id }}" name="title" type="text" maxlength="255" value="{{ $isEditingTraining ? old('title', $training->title) : $training->title }}" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                                @error('title')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="training-provider-{{ $training->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Organisme de formation</label>
                                <input id="training-provider-{{ $training->id }}" name="provider" type="text" maxlength="255" value="{{ $isEditingTraining ? old('provider', $training->provider) : $training->provider }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                                @error('provider')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="training-date-{{ $training->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de formation</label>
                                <input id="training-date-{{ $training->id }}" name="training_date" type="date" value="{{ $isEditingTraining ? old('training_date', $training->training_date?->format('Y-m-d')) : $training->training_date?->format('Y-m-d') }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                                @error('training_date')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="training-expires-at-{{ $training->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’expiration / validité</label>
                                <input id="training-expires-at-{{ $training->id }}" name="expires_at" type="date" value="{{ $isEditingTraining ? old('expires_at', $training->expires_at?->format('Y-m-d')) : $training->expires_at?->format('Y-m-d') }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                                @error('expires_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="xl:col-span-2">
                                <label for="training-description-{{ $training->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Description ou remarques</label>
                                <textarea id="training-description-{{ $training->id }}" name="description" rows="3" maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ $isEditingTraining ? old('description', $training->description) : $training->description }}</textarea>
                                @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="xl:col-span-2">
                                <label for="training-certificate-{{ $training->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $training->file_path ? 'Remplacer le justificatif' : 'Ajouter un justificatif' }}</label>
                                <x-file-upload
                                    id="training-certificate-{{ $training->id }}"
                                    name="certificate"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    :max-bytes="10485760"
                                />
                                <p class="mt-1 text-xs text-slate-500">PDF, JPG, JPEG ou PNG · 10 Mo maximum. Laissez vide pour conserver le justificatif actuel.</p>
                                @error('certificate')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex flex-wrap gap-3 xl:col-span-2">
                                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">Enregistrer les modifications</button>
                                <button type="button" data-training-edit-cancel class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center min-[480px]:col-span-2 md:col-span-3 2xl:col-span-4">
                        <p class="text-sm font-semibold text-slate-800">Aucune formation enregistrée pour cet employé.</p>
                        <p class="mt-1 text-sm text-slate-600">Vous pouvez ajouter une formation quand vous le souhaitez.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Historique de la fiche</h3>
            @if ($employee->history->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Aucun événement enregistré.</p>
            @else
                <ol tabindex="0" aria-label="Historique de la fiche employé" class="mt-4 max-h-56 space-y-3 overflow-y-auto overscroll-contain pr-2">
                    @foreach ($employee->history as $event)
                        <li class="border-l-2 border-blue-200 pl-4">
                            <p class="text-sm font-semibold text-slate-800">{{ str_replace('_', ' ', ucfirst($event->action)) }}{{ $event->document ? ' · '.$event->document->displayLabel() : '' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $event->created_at?->format('d/m/Y à H:i') }} · {{ $event->user?->name ?? 'Compte supprimé' }}</p>
                            @if ($event->reason)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $event->reason }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        @include('shared.company-document-version-history', [
            'documents' => $employee->documents,
            'resource' => $employee,
            'previewRoute' => 'prestataire.employees.documents.versions.preview',
            'downloadRoute' => 'prestataire.employees.documents.versions.download',
            'canDownload' => true,
        ])
    </section>
@endsection
