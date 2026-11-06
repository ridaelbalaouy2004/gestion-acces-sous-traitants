@if ($documents->isEmpty())
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7M8.5 15.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <h4 class="mt-4 text-sm font-semibold text-slate-800">{{ $emptyTitle }}</h4>
        <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500">{{ $emptyMessage }}</p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-left">
            <thead class="bg-slate-50/80">
                <tr>
                    <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Document</th>
                    @if ($showPrestataire)
                        <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Prestataire</th>
                    @endif
                    <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                    <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($documents as $document)
                    <tr class="transition hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                            <p class="max-w-64 truncate text-sm font-semibold text-slate-800">{{ $document->title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $document->reference_number ?: 'Référence en attente' }}</p>
                        </td>
                        @if ($showPrestataire)
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $document->prestataire?->name ?? 'Compte indisponible' }}</td>
                        @endif
                        <td class="whitespace-nowrap px-5 py-4">
                            @include('shared.document-status-badge', ['status' => $document->status])
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ ($document->{$dateColumn} ?? $document->submitted_at ?? $document->created_at)?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
