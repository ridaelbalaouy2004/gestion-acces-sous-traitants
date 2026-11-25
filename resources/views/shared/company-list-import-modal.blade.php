<dialog
    data-excel-import-modal
    aria-labelledby="excel-import-modal-title"
    class="max-h-[90vh] w-[min(96vw,80rem)] max-w-none overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/60"
>
    <div class="flex max-h-[90vh] flex-col">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 id="excel-import-modal-title" data-excel-import-modal-title class="text-lg font-semibold text-slate-900">Données Excel importées</h2>
                <p class="mt-1 text-sm text-slate-600">Valeurs actuelles enregistrées dans le dossier.</p>
            </div>
            <button
                type="button"
                data-excel-import-close
                class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Fermer
            </button>
        </header>
        <div data-excel-import-modal-content class="min-h-32 overflow-auto p-5 sm:p-6">
            <p class="text-sm text-slate-600">Les données importées apparaîtront ici.</p>
        </div>
    </div>
</dialog>
