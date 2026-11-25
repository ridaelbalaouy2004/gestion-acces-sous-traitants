@props(['maxBytes' => 10485760])

@php($maxSizeInMegabytes = (int) round($maxBytes / 1024 / 1024))

<div data-file-dropzone data-max-bytes="{{ $maxBytes }}" class="file-dropzone space-y-2">
    <label
        data-file-dropzone-target
        class="file-dropzone-target flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/70 px-4 py-6 text-center transition hover:border-blue-400 hover:bg-blue-50"
    >
        <input type="file" data-file-dropzone-input {{ $attributes->merge(['class' => 'sr-only']) }}>
        <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8 text-blue-700" aria-hidden="true">
            <path d="M12 16V4m0 0 4 4m-4-4L8 8M5 14.5v3A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5v-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="space-y-1">
            <span data-file-dropzone-prompt class="block text-sm font-semibold text-slate-800">Glissez-déposez votre fichier ici</span>
            <span class="block text-sm text-blue-700">ou cliquez pour parcourir</span>
        </span>
        <span class="text-xs text-slate-500">Taille maximale par fichier : {{ $maxSizeInMegabytes }} Mo.</span>
    </label>

    <div data-file-dropzone-selection hidden class="flex flex-col gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3 sm:flex-row sm:items-start sm:justify-between">
        <ul data-file-dropzone-files class="min-w-0 flex-1 space-y-2"></ul>
        <button type="button" data-file-dropzone-remove class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800">
            Retirer la sélection
        </button>
    </div>

    <div data-file-upload-progress-state hidden role="status" aria-live="polite" class="space-y-2 rounded-xl border border-blue-100 bg-blue-50/70 p-3">
        <div class="flex items-center gap-2 text-sm font-semibold text-blue-900">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="opacity-90"/>
            </svg>
            <span data-file-upload-progress-message></span>
        </div>
        <progress data-file-upload-progress max="100" aria-label="Progression indéterminée de l’envoi" class="h-2 w-full accent-blue-700"></progress>
    </div>

    <p data-file-dropzone-error hidden role="alert" class="text-sm font-medium text-red-700"></p>
</div>
