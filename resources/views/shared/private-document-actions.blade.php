@php($fileName = $fileName ?? null)
@php($showUploadedStatus = $showUploadedStatus ?? true)
@php($excelDataUrl = $excelDataUrl ?? null)
@php($excelDataTitle = $excelDataTitle ?? 'Données Excel importées')

<div class="flex flex-wrap items-center gap-2">
    @if ($showUploadedStatus && $downloadUrl)
        <span data-file-uploaded-state class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">
            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4 shrink-0 text-emerald-700" aria-hidden="true">
                <path d="m5 12.5 4.25 4.25L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Fichier déposé</span>
            @if ($fileName)
                <span class="min-w-0 truncate max-w-[200px] sm:max-w-xs block font-normal" title="{{ $fileName }}">{{ $fileName }}</span>
            @endif
        </span>
    @endif

    @if ($previewUrl)
        <a
            href="{{ $previewUrl }}"
            target="_blank"
            rel="noopener"
            class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 transition hover:border-blue-300 hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
        >
            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                <path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/>
            </svg>
            <span>Aperçu</span>
        </a>
    @endif
    @if ($downloadUrl)
        <a
            href="{{ $downloadUrl }}"
            class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
        >
            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                <path d="M12 3.75v11.5m0 0 4-4m-4 4-4-4M4.5 16v3.25A1.25 1.25 0 0 0 5.75 20.5h12.5a1.25 1.25 0 0 0 1.25-1.25V16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>{{ $downloadLabel ?? 'Télécharger' }}</span>
        </a>
    @endif
    @if ($excelDataUrl)
        <button
            type="button"
            data-excel-import-trigger
            data-url="{{ $excelDataUrl }}"
            data-title="{{ $excelDataTitle }}"
            class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
        >
            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13ZM4 9h16M9 9v11m6-11v11" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            </svg>
            <span>Voir les données Excel</span>
        </button>
    @endif
</div>
