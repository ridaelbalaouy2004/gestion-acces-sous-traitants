@if ($documents->contains(fn ($document) => $document->versions->isNotEmpty()))
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="text-base font-semibold text-slate-900">Historique des versions des documents</h3>
        <div tabindex="0" aria-label="Historique des versions de documents" class="mt-4 max-h-72 space-y-3 overflow-y-auto overscroll-contain pr-2">
            @foreach ($documents->filter(fn ($document) => $document->versions->isNotEmpty()) as $document)
                <details class="rounded-xl border border-slate-200 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-800">{{ method_exists($document, 'displayLabel') ? $document->displayLabel() : $document->label }} ({{ $document->versions->count() }})</summary>
                    <ol class="mt-3 space-y-2">
                        @foreach ($document->versions->sortByDesc('version') as $version)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block truncate max-w-[200px] sm:max-w-xs font-medium text-slate-800" title="{{ $version->file_name }}">Version {{ $version->version }} · {{ $version->file_name }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">
                                        {{ $version->uploaded_at?->format('d/m/Y à H:i') ?? '—' }}
                                        @if ($version->uploadedBy) · par {{ $version->uploadedBy->name }} @endif
                                    </span>
                                    @if ($version->notes)
                                        <span class="mt-1 block whitespace-pre-line text-xs text-slate-600">{{ $version->notes }}</span>
                                    @endif
                                </span>
                                @if ($canDownload && filled($version->file_path))
                                    @include('shared.private-document-actions', [
                                        'previewUrl' => in_array($version->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)
                                            ? route($previewRoute, [$resource, $document, $version])
                                            : null,
                                        'downloadUrl' => route($downloadRoute, [$resource, $document, $version]),
                                        'fileName' => $version->file_name,
                                        'showUploadedStatus' => false,
                                    ])
                                @elseif (blank($version->file_path))
                                    <span class="shrink-0 text-xs font-medium text-slate-500">Fichier retiré du stockage</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endforeach
        </div>
    </section>
@endif
