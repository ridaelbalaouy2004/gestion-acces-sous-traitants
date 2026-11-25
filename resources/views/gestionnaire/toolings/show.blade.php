@extends('layouts.authenticated')

@section('pageTitle', 'Examen d’un outillage')

@section('content')
    <section class="space-y-6">
        <div>
            <a href="{{ route('gestionnaire.toolings.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à la liste</a>
            <p class="mt-5 text-sm font-medium text-slate-500">{{ $tooling->company->prestataire->name }} · {{ $tooling->company->name }}</p>
            <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    @include('shared.company-toolings.avatar', [
                        'tooling' => $tooling,
                        'photoUrl' => route('gestionnaire.toolings.photo', $tooling),
                        'sizeClass' => 'size-20',
                        'fallbackOnError' => true,
                    ])
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $tooling->designation }}</h2>
                </div>
                @include('shared.company-toolings.status', ['status' => $tooling->status])
            </div>
        </div>
        @if ($tooling->company->status !== 'validated')
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
                <h3 class="text-base font-semibold text-slate-900">Informations de l’outillage</h3>
                <p class="mt-1 text-sm text-slate-600">Les corrections ne modifient ni l’entreprise associée ni le statut de validation.</p>
            </div>
            @if (auth()->user()->hasPermission('toolings.update'))
                <form method="POST" action="{{ route('gestionnaire.toolings.update', $tooling) }}" class="mt-5 space-y-5">
                    @csrf
                    @method('PUT')
                    @include('shared.company-toolings.fields', ['tooling' => $tooling])
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Enregistrer les informations</button>
                </form>
            @else
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catégorie</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $tooling->category }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Référence interne</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $tooling->internal_reference ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Marque / modèle</dt><dd class="mt-1.5 text-sm text-slate-800">{{ collect([$tooling->brand, $tooling->model])->filter()->join(' ') ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Numéro de série</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $tooling->serial_number ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quantité</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $tooling->quantity }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">État physique</dt><dd class="mt-1.5 text-sm text-slate-800">{{ ['new' => 'Neuf', 'good' => 'Bon', 'average' => 'Moyen', 'damaged' => 'À réparer'][$tooling->physical_condition] ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date d’acquisition</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $tooling->acquired_at?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Affectation / emplacement</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $tooling->location ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt><dd class="mt-1.5 whitespace-pre-line text-sm text-slate-800">{{ $tooling->description ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observations</dt><dd class="mt-1.5 whitespace-pre-line text-sm text-slate-800">{{ $tooling->observations ?: '—' }}</dd></div>
                </dl>
            @endif

            @if ($tooling->rejection_reason)
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4">
                    <h4 class="text-sm font-semibold text-red-900">Motif du rejet de l’outillage</h4>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $tooling->rejection_reason }}</p>
                </div>
            @endif

            @if ($tooling->status === 'pending')
                <div class="mt-6 flex flex-col gap-5 border-t border-slate-100 pt-5">
                    @if (auth()->user()->hasPermission('toolings.approve'))
                        @if ($tooling->company->status === 'validated')
                            <form method="POST" action="{{ route('gestionnaire.toolings.approve', $tooling) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Valider l’outillage</button>
                                <p class="mt-2 text-xs text-slate-500">Cette décision ne modifie aucun document ni le statut de l’entreprise.</p>
                            </form>
                        @else
                            <button type="button" disabled class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600">Valider l’outillage</button>
                            <p class="text-sm text-amber-900">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                        @endif
                    @endif
                    @if (auth()->user()->hasPermission('toolings.reject'))
                        <form method="POST" action="{{ route('gestionnaire.toolings.reject', $tooling) }}" class="max-w-2xl">
                            @csrf
                            <label for="tooling-rejection-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Motif du rejet <span class="text-red-600" aria-hidden="true">*</span></label>
                            <textarea id="tooling-rejection-reason" name="reason" rows="4" minlength="10" maxlength="2000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('reason') }}</textarea>
                            @error('reason')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                            <button type="submit" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">Rejeter l’outillage</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        <section class="space-y-4" data-bulk-document-approval>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Documents de l’outillage</h3>
                <p class="mt-1 text-sm text-slate-600">Chaque document est examiné indépendamment.</p>
            </div>
            @foreach ($tooling->documents as $document)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            @if (
                                $document->status === 'pending'
                                && $tooling->company->status === 'validated'
                                && auth()->user()->hasPermission('toolings.approve')
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
                                @if (auth()->user()->hasPermission('toolings.download'))
                                    <div class="mt-2">
                                        @include('shared.private-document-actions', [
                                            'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                                ? route('gestionnaire.toolings.documents.preview', [$tooling, $document])
                                                : null,
                                            'downloadUrl' => route('gestionnaire.toolings.documents.download', [$tooling, $document]),
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
                        @include('shared.company-toolings.status', ['status' => $document->status])
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
                            @if (auth()->user()->hasPermission('toolings.approve'))
                                @if ($tooling->company->status === 'validated')
                                    <form method="POST" action="{{ route('gestionnaire.toolings.documents.approve', [$tooling, $document]) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Valider ce document</button>
                                    </form>
                                @else
                                    <button type="button" disabled class="inline-flex min-h-10 cursor-not-allowed items-center justify-center rounded-lg bg-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-600">Valider ce document</button>
                                    <p class="text-sm text-amber-900">La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.</p>
                                @endif
                            @endif
                            @if (auth()->user()->hasPermission('toolings.reject'))
                                <form method="POST" action="{{ route('gestionnaire.toolings.documents.reject', [$tooling, $document]) }}" class="max-w-2xl">
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

            @if ($tooling->company->status === 'validated' && auth()->user()->hasPermission('toolings.approve'))
                @include('shared.bulk-document-approval', [
                    'action' => route('gestionnaire.toolings.documents.approve-selected', $tooling),
                    'description' => 'Sélectionnez uniquement les documents en attente de cet outillage.',
                ])
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Historique des modifications et décisions</h3>
            @if ($tooling->history->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Aucun événement enregistré.</p>
            @else
                <ol tabindex="0" aria-label="Historique de l’outillage" class="mt-4 max-h-56 space-y-3 overflow-y-auto overscroll-contain pr-2">
                    @foreach ($tooling->history as $event)
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
            'documents' => $tooling->documents,
            'resource' => $tooling,
            'previewRoute' => 'gestionnaire.toolings.documents.versions.preview',
            'downloadRoute' => 'gestionnaire.toolings.documents.versions.download',
            'canDownload' => auth()->user()->hasPermission('toolings.download'),
        ])
    </section>
@endsection
