@extends('layouts.authenticated')

@section('pageTitle', 'Fiche engin')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('prestataire.machinery.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à mes engins</a>
                <p class="mt-5 text-sm font-medium text-slate-500">{{ $machinery->company->name }} · {{ $machinery->category }}</p>
                <div class="mt-2 flex items-center gap-4">
                    @include('shared.company-machinery.avatar', [
                        'machinery' => $machinery,
                        'photoUrl' => route('prestataire.machinery.photo', $machinery),
                        'sizeClass' => 'size-20',
                        'fallbackOnError' => true,
                    ])
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $machinery->designation }}</h2>
                        <div class="mt-3">@include('shared.company-machinery.status', ['status' => $machinery->status])</div>
                    </div>
                </div>
            </div>
            @if (in_array($machinery->status, ['draft', 'rejected'], true))
                <a href="{{ route('prestataire.machinery.edit', $machinery) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier les informations</a>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
        @endif
        @if ($machinery->rejection_reason)
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-red-900">Motif du rejet de l’engin</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $machinery->rejection_reason }}</p>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Informations de l’engin</h3>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Affectation</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->assignment ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Marque / modèle</dt><dd class="mt-1.5 text-sm text-slate-800">{{ collect([$machinery->brand, $machinery->model])->filter()->join(' ') ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Numéro de série</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->serial_number ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Immatriculation</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->registration_number ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mise en service</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->commissioned_at?->format('d/m/Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dernière soumission</dt><dd class="mt-1.5 text-sm text-slate-800">{{ $machinery->submitted_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt><dd class="mt-1.5 whitespace-pre-line text-sm text-slate-800">{{ $machinery->description ?: '—' }}</dd></div>
            </dl>
            @if (in_array($machinery->status, ['draft', 'rejected'], true))
                <form method="POST" action="{{ route('prestataire.machinery.submit', $machinery) }}" class="mt-6 border-t border-slate-100 pt-5">
                    @csrf
                    <p class="mb-3 text-sm text-slate-600">Complétez la désignation et la catégorie, puis téléversez les quatre documents obligatoires avant la soumission.</p>
                    @if ($missingRequiredDocuments !== [])
                        <p class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                            Soumission impossible. Documents obligatoires manquants ou indisponibles : {{ implode(', ', $missingRequiredDocuments) }}.
                        </p>
                    @endif
                    @error('machinery')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
                    <button type="submit" @disabled($missingRequiredDocuments !== []) class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $machinery->status === 'rejected' ? 'Renvoyer après correction' : 'Soumettre à validation' }}</button>
                </form>
            @endif
        </section>

        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Documents de l’engin</h3>
                <p class="mt-1 text-sm text-slate-600">Assurance, carte grise, visite technique et certificat réglementaire sont obligatoires pour soumettre l’engin. Chaque document conserve son statut de validation indépendant.</p>
            </div>
            @foreach ($machinery->documents as $document)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="font-semibold text-slate-900">{{ $document->label }}</h4>
                            @if ($document->file_path)
                                <div class="mt-2">
                                    @include('shared.private-document-actions', [
                                        'previewUrl' => in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                            ? route('prestataire.machinery.documents.preview', [$machinery, $document])
                                            : null,
                                        'downloadUrl' => route('prestataire.machinery.documents.download', [$machinery, $document]),
                                        'downloadLabel' => 'Télécharger le document',
                                        'fileName' => $document->file_name,
                                    ])
                                </div>
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
                    @include('shared.document-rejection-reason', ['document' => $document])
                    @if ($document->file_path && $document->status !== 'validated')
                        <form method="POST" action="{{ route('prestataire.machinery.documents.remove', [$machinery, $document]) }}" data-confirm="Le fichier courant sera supprimé définitivement du stockage privé. Les versions antérieures et l’historique seront conservés. Continuer ?" class="mt-4">
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
                    <form method="POST" action="{{ route('prestataire.machinery.documents.store', [$machinery, $document->type]) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="document-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $document->file_path ? 'Remplacer le fichier' : 'Téléverser le fichier' }}</label>
                                <x-file-upload
                                    id="document-{{ $document->type }}"
                                    name="document"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    :max-bytes="10485760"
                                    required
                                />
                                <p class="mt-1 text-xs text-slate-500">PDF, JPG ou PNG · 10 Mo maximum.</p>
                            </div>
                            <div>
                                <label for="issued-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’émission</label>
                                <input id="issued-{{ $document->type }}" name="issued_at" type="date" value="{{ old('issued_at', $document->issued_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            </div>
                            <div>
                                <label for="expires-{{ $document->type }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’expiration</label>
                                <input id="expires-{{ $document->type }}" name="expires_at" type="date" value="{{ old('expires_at', $document->expires_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                            </div>
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100">Enregistrer le document</button>
                    </form>
                    @error('document')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    @error('issued_at')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    @error('expires_at')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-slate-900">Historique de l’engin</h3>
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
            'previewRoute' => 'prestataire.machinery.documents.versions.preview',
            'downloadRoute' => 'prestataire.machinery.documents.versions.download',
            'canDownload' => true,
        ])
    </section>
@endsection
