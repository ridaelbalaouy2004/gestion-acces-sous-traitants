<dialog id="{{ $dialogId }}" class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-950/50" aria-labelledby="{{ $dialogId }}-title">
    <div class="p-5 sm:p-6">
        <h3 id="{{ $dialogId }}-title" class="text-lg font-semibold text-slate-900">Confirmation d’envoi</h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">Voulez-vous vraiment envoyer ce Guide au Prestataire sélectionné ?</p>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-guide-email-cancel class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Annuler
            </button>
            <button type="button" data-guide-email-confirm class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                Confirmer et envoyer
            </button>
        </div>
    </div>
</dialog>
