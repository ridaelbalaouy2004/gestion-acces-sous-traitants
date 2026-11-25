@extends('layouts.authenticated')

@section('pageTitle', 'Examen d’un engin')

@section('content')
    <section class="space-y-6">
        <div>
            <a href="{{ route('gestionnaire.machinery.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à la liste</a>
            <p class="mt-5 text-sm font-medium text-slate-500">{{ $machinery->company->prestataire->name }} · {{ $machinery->company->name }}</p>
            <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    @include('shared.company-machinery.avatar', [
                        'machinery' => $machinery,
                        'photoUrl' => route('gestionnaire.machinery.photo', $machinery),
                        'sizeClass' => 'size-20',
                        'fallbackOnError' => true,
                    ])
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $machinery->designation }}</h2>
                </div>
                @include('shared.company-machinery.status', ['status' => $machinery->status])
            </div>
        </div>
        @if ($machinery->company->status !== 'validated')
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4" role="status">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-amber-200 px-2.5 py-1 text-xs font-semibold text-amber-950">Entreprise non validée</span>
                    <p class="text-sm text-amber-950">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                </div>
            </div>
        @endif
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Informations de l’engin</h3>
                <p class="mt-1 text-sm text-slate-600">Les corrections ne modifient ni l’entreprise associée ni le statut de validation.</p>
            </div>
            @if (auth()->user()->hasPermission('machinery.update'))
                <form method="POST" action="{{ route('gestionnaire.machinery.update', $machinery) }}" class="mt-5 space-y-5">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            'designation' => ['Désignation', 150],
                            'category' => ['Catégorie', 100],
                            'assignment' => ['Affectation', 255],
                            'brand' => ['Marque', 100],
                            'model' => ['Modèle', 100],
                            'serial_number' => ['Numéro de série', 100],
                            'registration_number' => ['Immatriculation', 100],
                        ] as $field => [$label, $maximum])
                            <div>
                                <label for="{{ $field }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}{{ in_array($field, ['designation', 'category'], true) ? ' *' : '' }}</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="{{ $maximum }}" value="{{ old($field, $machinery->{$field}) }}" @required(in_array($field, ['designation', 'category'], true)) class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                                @error($field)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <div>
                            <label for="commissioned_at" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de mise en service</label>
                            <input id="commissioned_at" name="commissioned_at" type="date" value="{{ old('commissioned_at', $machinery->commissioned_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            @error('commissioned_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description</label>
                        <textarea id="description" name="description" rows="3" maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('description', $machinery->description) }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Enregistrer les informations</button>
                </form>
            @else
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catégorie</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->category }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Affectation</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->assignment ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Marque / modèle</dt><dd class="mt-1.5 text-sm text-slate-800">{{ collect([$machinery->brand, $machinery->model])->filter()->join(' ') ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Numéro de série</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->serial_number ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Immatriculation</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->registration_number ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mise en service</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->commissioned_at?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt><dd class="mt-1.5 whitespace-pre-line text-sm text-slate-800">{{ $machinery->description ?: '—' }}</dd></div>
                </dl>
            @endif

            @if ($machinery->rejection_reason)
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4">
                    <h4 class="text-sm font-semibold text-red-900">Motif du rejet de l’engin</h4>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $machinery->rejection_reason }}</p>
                </div>
            @endif

            @if ($machinery->status === 'pending')
                <div class="mt-6 flex flex-col gap-5 border-t border-slate-100 pt-5">
                    @if (auth()->user()->hasPermission('machinery.approve'))
                        @if ($machinery->company->status === 'validated')
                            <form method="POST" action="{{ route('gestionnaire.machinery.approve', $machinery) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Valider l’engin</button>
                                <p class="mt-2 text-xs text-slate-500">Cette décision ne modifie aucun document ni le statut de l’entreprise.</p>
                            </form>
                        @else
                            <button type="button" disabled class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">Valider l’engin</button>
                            <p class="text-sm text-amber-900">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                        @endif
                    @endif
                    @if (auth()->user()->hasPermission('machinery.reject'))
                        <form method="POST" action="{{ route('gestionnaire.machinery.reject', $machinery) }}" class="max-w-2xl">
                            @csrf
                            <label for="machinery-rejection-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Motif du rejet <span class="text-red-600" aria-hidden="true">*</span></label>
                            <textarea id="machinery-rejection-reason" name="reason" rows="4" minlength="10" maxlength="2000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('reason') }}</textarea>
                            @error('reason')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                            <button type="submit" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">Rejeter l’engin</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        <section class="space-y-4" data-bulk-document-approval>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Documents de l’engin</h3>
                <p class="mt-1 text-sm text-slate-600">Chaque document est examiné indépendamment.</p>
            </div>
            @foreach ($machinery->documents as $document)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            @if (
                                $document->status === 'pending'
                                && $machinery->company->status === 'validated'
                                && auth()->user()->hasPermission('machinery.approve')
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
                            <h4 class="font-semibold text-slate-900">{{ $document->label }}</h4>
                            @if ($document->file_path)
                                @if (auth()->user()->hasPermission('machinery.download'))
                                    <div class="mt-2">
                                        @include('shared.private-document-actions', [
                                            'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                                ? route('gestionnaire.machinery.documents.preview', [$machinery, $document])
                                                : null,
                                            'downloadUrl' => route('gestionnaire.machinery.documents.download', [$machinery, $document]),
                                            'downloadLabel' => 'Télécharger le document',
                                            'fileName' => $document->file_name,
                                        ])
                                    </div>
                                @else
                                    <span class="mt-2 truncate max-w-[200px] sm:max-w-xs block text-sm text-slate-700" title="{{ $document->file_name ?: 'Document enregistré' }}">{{ $document->file_name ?: 'Document enregistré' }}</span>
                                @endif
                            @else
                                <p class="mt-1 text-sm text-slate-500">Aucun fichier enregistré.</p>
                            @endif
                        </div>
                        @include('shared.company-machinery.status', ['status' => $document->status])
                    </div>
                    @if ($document->issued_at || $document->expires_at)
                        <p class="mt-3 text-sm text-slate-600">
                            @if ($document->issued_at)Émis le {{ $document->issued_at->format('d/m/Y') }}@endif
                            @if ($document->issued_at && $document->expires_at) · @endif
                            @if ($document->expires_at)Expire le {{ $document->expires_at->format('d/m/Y') }}@endif
                        </p>
                    @endif
                    @if ($document->rejection_reason)
                        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3.5">
                            <p class="text-sm font-semibold text-red-900">Motif du rejet</p>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-red-800">{{ $document->rejection_reason }}</p>
                        </div>
                    @endif
                    @if ($document->status === 'pending')
                        <div class="mt-5 flex flex-col gap-5 border-t border-slate-100 pt-5">
                            @if (auth()->user()->hasPermission('machinery.approve'))
                                @if ($machinery->company->status === 'validated')
                                    <form method="POST" action="{{ route('gestionnaire.machinery.documents.approve', [$machinery, $document]) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Valider ce document</button>
                                    </form>
                                @else
                                    <button type="button" disabled class="inline-flex min-h-10 cursor-not-allowed items-center justify-center rounded-lg bg-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-600">Valider ce document</button>
                                    <p class="text-sm text-amber-900">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                                @endif
                            @endif
                            @if (auth()->user()->hasPermission('machinery.reject'))
                                <form method="POST" action="{{ route('gestionnaire.machinery.documents.reject', [$machinery, $document]) }}" class="max-w-2xl">
                                    @csrf
                                    <label for="document-reason-{{ $document->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Motif du rejet <span class="text-red-600" aria-hidden="true">*</span></label>
                                    <textarea id="document-reason-{{ $document->id }}" name="reason" rows="3" minlength="10" maxlength="2000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('reason') }}</textarea>
                                    @error('reason')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                                    <button type="submit" class="mt-3 inline-flex min-h-10 items-center justify-center rounded-lg bg-red-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-red-800">Rejeter ce document</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach

            @if ($machinery->company->status === 'validated' && auth()->user()->hasPermission('machinery.approve'))
                @include('shared.bulk-document-approval', [
                    'action' => route('gestionnaire.machinery.documents.approve-selected', $machinery),
                    'description' => 'Sélectionnez uniquement les documents en attente de cet engin.',
                ])
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Historique des modifications et décisions</h3>
            @if ($machinery->history->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Aucun événement enregistré.</p>
            @else
                <ol tabindex="0" aria-label="Historique de l’engin" class="mt-4 max-h-56 space-y-3 overflow-y-auto overscroll-contain pr-2">
                    @foreach ($machinery->history as $event)
                        <li class="border-l-2 border-blue-200 pl-4">
                            <p class="text-sm font-semibold text-slate-800">{{ str_replace('_', ' ', ucfirst($event->action)) }}{{ $event->document ? ' · '.$event->document->label : '' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $event->created_at?->format('d/m/Y à H:i') }} · {{ $event->user?->name ?? 'Compte supprimé' }}</p>
                            @if ($event->reason)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $event->reason }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        @include('shared.company-document-version-history', [
            'documents' => $machinery->documents,
            'resource' => $machinery,
            'previewRoute' => 'gestionnaire.machinery.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.machinery.documents.versions.download',
            'canDownload' => auth()->user()->hasPermission('machinery.download'),
        ])
    </section>
@endsection
