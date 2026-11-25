@extends('layouts.authenticated')

@section('pageTitle', 'Examen d’un employé')

@section('content')
    <section class="space-y-6">
        <div>
            <a href="{{ route('gestionnaire.employees.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à la liste</a>
            <p class="mt-5 text-sm font-medium text-slate-500">{{ $employee->company->prestataire->name }} · {{ $employee->company->name }}</p>
            <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: 'Employé à compléter')
                <div class="flex items-center gap-4">
                    @include('shared.company-employees.avatar', [
                        'employee' => $employee,
                        'employeeName' => $employeeName,
                        'photoUrl' => route('gestionnaire.employees.photo', $employee),
                        'sizeClass' => 'size-20',
                    ])
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $employeeName }}</h2>
                </div>
                @include('shared.company-employees.status', ['status' => $employee->status])
            </div>
        </div>
        @if ($employee->company->status !== 'validated')
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4" role="status">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-amber-200 px-2.5 py-1 text-xs font-semibold text-amber-950">Entreprise non validée</span>
                    <p class="text-sm text-amber-950">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                </div>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Informations de l’employé</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        @if (auth()->user()->hasPermission('employees.edit'))
                            Vous pouvez compléter les champs manquants sans changer l’entreprise associée ni le statut.
                        @else
                            Les informations sont consultables en lecture seule.
                        @endif
                    </p>
                </div>
            </div>
            @if (auth()->user()->hasPermission('employees.edit'))
                <form method="POST" action="{{ route('gestionnaire.employees.update', $employee) }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="first_name" class="mb-1.5 block text-sm font-semibold text-slate-700">Prénom</label>
                        <input id="first_name" name="first_name" type="text" maxlength="100" value="{{ old('first_name', $employee->first_name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="last_name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom</label>
                        <input id="last_name" name="last_name" type="text" maxlength="100" value="{{ old('last_name', $employee->last_name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="cin" class="mb-1.5 block text-sm font-semibold text-slate-700">CIN</label>
                        <input id="cin" name="cin" type="text" maxlength="50" value="{{ old('cin', $employee->cin) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="position" class="mb-1.5 block text-sm font-semibold text-slate-700">Fonction</label>
                        <input id="position" name="position" type="text" maxlength="255" value="{{ old('position', $employee->position) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="locker_number" class="mb-1.5 block text-sm font-semibold text-slate-700">Numéro de cadenas</label>
                        <input id="locker_number" name="locker_number" type="text" maxlength="50" value="{{ old('locker_number', $employee->locker_number) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    </div>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Enregistrer les informations</button>
                </form>
            @else
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prénom</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->first_name ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nom</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->last_name ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">CIN</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->cin ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fonction</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->position ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Numéro de cadenas</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $employee->locker_number ?: '—' }}</dd></div>
                </dl>
            @endif

            @if ($employee->rejection_reason)
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4">
                    <h4 class="text-sm font-semibold text-red-900">Motif du rejet de l’employé</h4>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $employee->rejection_reason }}</p>
                </div>
            @endif

            @if ($employee->status === 'pending' && (auth()->user()->hasPermission('employees.validate') || auth()->user()->hasPermission('employees.reject')))
                <div class="mt-6 flex flex-col gap-5 border-t border-slate-100 pt-5">
                    @if ($approvalBlockers !== [])
                        <div id="employee-approval-blockers" class="rounded-xl border border-amber-300 bg-amber-50 p-4" role="alert">
                            <h4 class="text-sm font-semibold text-amber-950">Approbation finale impossible</h4>
                            <p class="mt-1 text-sm text-amber-900">Tous les documents obligatoires et tout document facultatif déposé doivent être validés et non expirés.</p>
                            <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-amber-950">
                                @foreach ($approvalBlockers as $blocker)
                                    <li><span class="font-semibold">{{ $blocker['label'] }}</span> — {{ $blocker['reason'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($employee->company->status === 'validated')
                        @if (auth()->user()->hasPermission('employees.validate'))
                            <form method="POST" action="{{ route('gestionnaire.employees.approve', $employee) }}">
                                @csrf
                                @if ($approvalBlockers === [])
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Valider l’employé</button>
                                @else
                                    <button type="button" disabled aria-describedby="employee-approval-blockers" class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">Valider l’employé</button>
                                @endif
                                <p class="mt-2 text-xs text-slate-500">La validation ne modifie pas les statuts des documents ni celui de l’entreprise.</p>
                            </form>
                        @endif
                        @if (auth()->user()->hasPermission('employees.reject'))
                            <form method="POST" action="{{ route('gestionnaire.employees.reject', $employee) }}" class="max-w-2xl">
                                @csrf
                                <label for="employee-rejection-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Motif du rejet <span class="text-red-600" aria-hidden="true">*</span></label>
                                <textarea id="employee-rejection-reason" name="reason" rows="4" minlength="10" maxlength="2000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('reason') }}</textarea>
                                <p class="mt-1.5 text-xs text-slate-500">Obligatoire, entre 10 et 2 000 caractères.</p>
                                @error('reason')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                                <button type="submit" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">Rejeter l’employé</button>
                            </form>
                        @endif
                    @else
                        <div class="flex flex-wrap gap-3">
                            @if (auth()->user()->hasPermission('employees.validate'))
                                <button type="button" disabled class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">Valider l’employé</button>
                            @endif
                            @if (auth()->user()->hasPermission('employees.reject'))
                                <button type="button" disabled class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">Rejeter l’employé</button>
                            @endif
                        </div>
                        <p class="text-sm text-amber-900">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                    @endif
                </div>
            @endif
        </section>

        <section class="space-y-4" data-bulk-document-approval>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Documents de l’employé</h3>
                <p class="mt-1 text-sm text-slate-600">Chaque décision ne concerne que le document sélectionné.</p>
            </div>
            @foreach ($documentsForDisplay as $document)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            @if (
                                $document->status === 'pending'
                                && $employee->company->status === 'validated'
                                && auth()->user()->hasPermission('employees.validate')
                            )
                                <label class="mb-2 inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                                    <input
                                        type="checkbox"
                                        name="document_ids[]"
                                        value="{{ $document->getKey() }}"
                                        form="bulk-document-approval-form"
                                        data-bulk-document-checkbox
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-700"
                                    >
                                    Sélectionner
                                </label>
                            @endif
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
                            @if (auth()->user()->hasPermission('employees.download'))
                                @include('shared.private-document-actions', [
                                    'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                        ? route('gestionnaire.employees.documents.preview', [$employee, $document])
                                        : null,
                                    'downloadUrl' => route('gestionnaire.employees.documents.download', [$employee, $document]),
                                    'downloadLabel' => 'Télécharger le document',
                                    'fileName' => $document->file_name,
                                ])
                            @else
                                <span class="truncate max-w-[200px] sm:max-w-xs block font-medium text-slate-700" title="{{ $document->file_name ?: 'Document enregistré' }}">{{ $document->file_name ?: 'Document enregistré' }}</span>
                            @endif
                            @if ($document->expires_at)<span class="text-slate-600">Expire le {{ $document->expires_at->format('d/m/Y') }}</span>@endif
                        </div>
                    @endif
                    @if ($document->rejection_reason)
                        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3.5">
                            <p class="text-sm font-semibold text-red-900">Motif du rejet</p>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-red-800">{{ $document->rejection_reason }}</p>
                        </div>
                    @endif
                    @if ($document->status === 'pending' && (auth()->user()->hasPermission('employees.validate') || auth()->user()->hasPermission('employees.reject')))
                        <div class="mt-5 flex flex-col gap-5 border-t border-slate-100 pt-5">
                            @if ($employee->company->status === 'validated' && auth()->user()->hasPermission('employees.validate'))
                                <form method="POST" action="{{ route('gestionnaire.employees.documents.approve', [$employee, $document]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Valider ce document</button>
                                </form>
                            @elseif ($employee->company->status !== 'validated' && auth()->user()->hasPermission('employees.validate'))
                                <button type="button" disabled class="inline-flex min-h-10 cursor-not-allowed items-center justify-center rounded-lg bg-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-600">Valider ce document</button>
                                <p class="text-sm text-amber-900">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                            @endif
                            @if (auth()->user()->hasPermission('employees.reject'))
                                <form method="POST" action="{{ route('gestionnaire.employees.documents.reject', [$employee, $document]) }}" class="max-w-2xl">
                                    @csrf
                                    <label for="document-reason-{{ $document->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Motif du rejet <span class="text-red-600" aria-hidden="true">*</span></label>
                                    <textarea id="document-reason-{{ $document->id }}" name="reason" rows="3" minlength="10" maxlength="2000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('reason') }}</textarea>
                                    <button type="submit" class="mt-3 inline-flex min-h-10 items-center justify-center rounded-lg bg-red-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-red-800">Rejeter ce document</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach

            @if ($employee->company->status === 'validated' && auth()->user()->hasPermission('employees.validate'))
                @include('shared.bulk-document-approval', [
                    'action' => route('gestionnaire.employees.documents.approve-selected', $employee),
                    'description' => 'Sélectionnez uniquement les documents en attente de cet employé.',
                ])
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Historique des modifications et décisions</h3>
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
            'previewRoute' => 'gestionnaire.employees.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.employees.documents.versions.download',
            'canDownload' => auth()->user()->hasPermission('employees.download'),
        ])

        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Formations</h3>
                <p class="mt-1 text-sm text-slate-600">Informations complémentaires, indépendantes des documents HSE obligatoires.</p>
            </div>
            @forelse ($employee->trainings as $training)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-base font-semibold text-slate-900">{{ $training->title }}</h4>
                            @if ($training->isExpired())
                                <span class="mt-2 inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Validité expirée</span>
                            @endif
                        </div>
                        @if ($training->file_path && auth()->user()->hasPermission('employees.download'))
                            @include('shared.private-document-actions', [
                                'previewUrl' => in_array($training->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                    ? route('gestionnaire.employees.trainings.preview', [$employee, $training])
                                    : null,
                                'downloadUrl' => route('gestionnaire.employees.trainings.download', [$employee, $training]),
                                'downloadLabel' => 'Télécharger le justificatif',
                                'fileName' => $training->file_name,
                            ])
                        @elseif ($training->file_path)
                            <span class="truncate max-w-[200px] sm:max-w-xs block text-sm text-slate-600" title="{{ $training->file_name }}">{{ $training->file_name }}</span>
                        @endif
                    </div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Organisme</dt><dd class="mt-1 text-sm text-slate-800">{{ $training->provider ?: 'Non renseigné' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date de formation</dt><dd class="mt-1 text-sm text-slate-800">{{ $training->training_date?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date d’expiration / validité</dt><dd class="mt-1 text-sm text-slate-800">{{ $training->expires_at?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
                        @if ($training->description)
                            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description ou remarques</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $training->description }}</dd></div>
                        @endif
                    </dl>
                    @if ($training->versions->count() > 1 && auth()->user()->hasPermission('employees.download'))
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <h5 class="text-sm font-semibold text-slate-800">Justificatifs précédents</h5>
                            <ul class="mt-2 space-y-2">
                                @foreach ($training->versions->where('is_current', false) as $version)
                                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span class="truncate max-w-[200px] sm:max-w-xs block text-slate-600" title="{{ $version->file_name }}">{{ $version->file_name }} · {{ $version->uploaded_at?->format('d/m/Y') ?? '—' }}</span>
                                        <a href="{{ route('gestionnaire.employees.trainings.versions.download', [$employee, $training, $version]) }}" class="font-semibold text-blue-700 hover:text-blue-900">Télécharger</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">
                    <p class="text-sm text-slate-600">Aucune formation enregistrée pour cet employé.</p>
                </div>
            @endforelse
        </section>
    </section>
@endsection
