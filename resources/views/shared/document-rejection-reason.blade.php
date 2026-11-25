@if ($document->status === 'rejected')
    <div data-document-rejection-reason="{{ $document->getKey() }}" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3.5">
        <p class="text-sm font-semibold text-red-900">Motif de rejet</p>
        <p class="mt-1 break-words whitespace-pre-line text-sm leading-6 text-red-800">{{ filled($document->rejection_reason) ? $document->rejection_reason : 'Aucun motif de rejet renseigné' }}</p>
    </div>
@endif
