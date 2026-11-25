@extends('layouts.authenticated')

@section('pageTitle', 'Mes engins')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">Parc matériel de vos entreprises</p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Mes engins</h2>
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-800">
                        {{ $machineryCount }} {{ $machineryCount === 1 ? 'engin' : 'engins' }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-600">Chaque engin reste associé à l’entreprise qui le possède.</p>
            </div>
            @if ($companies->isNotEmpty())
                <a href="{{ route('prestataire.machinery.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">Ajouter un engin</a>
            @endif
        </div>

        <form method="GET" action="{{ route('prestataire.machinery.index') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
            <div>
                <label for="company_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Entreprise</label>
                <select id="company_id" name="company_id" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Toutes les entreprises</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">Statut</label>
                <select id="status" name="status" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Tous les statuts</option>
                    @foreach (['draft' => 'Brouillon', 'pending' => 'En attente', 'validated' => 'Validé', 'rejected' => 'Rejeté'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category" class="mb-1.5 block text-sm font-semibold text-slate-700">Catégorie</label>
                <select id="category" name="category" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">Recherche</label>
                <input id="search" name="search" type="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Désignation, série, immatriculation" class="form-control filter-control min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <button type="submit" class="filter-action inline-flex min-h-10 items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold text-white">Filtrer</button>
                <a href="{{ route('prestataire.machinery.index') }}" class="filter-reset inline-flex min-h-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-semibold">Effacer</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if ($machineryItems->isEmpty())
                <div class="px-6 py-14 text-center">
                    <h3 class="text-lg font-semibold text-slate-900">Aucun engin trouvé</h3>
                    <p class="mt-2 text-sm text-slate-600">Ajoutez un engin ou modifiez vos filtres.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Engin</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Catégorie</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">N° de série</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Statut</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($machineryItems as $machinery)
                                <tr
                                    data-navigate-to="{{ route('prestataire.machinery.show', $machinery) }}"
                                    tabindex="0"
                                    aria-label="Consulter l’engin {{ $machinery->designation }}"
                                    aria-keyshortcuts="Enter Space"
                                    class="cursor-pointer transition-colors hover:bg-slate-50/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-700"
                                >
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-900">
                                        <div class="flex min-w-0 items-center gap-3">
                                            @include('shared.company-machinery.avatar', [
                                                'machinery' => $machinery,
                                                'photoUrl' => route('prestataire.machinery.photo', $machinery),
                                                'sizeClass' => 'size-11',
                                                'fallbackOnError' => true,
                                            ])
                                            <span class="shrink-0 whitespace-nowrap">{{ $machinery->designation }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $machinery->company->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $machinery->category }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $machinery->serial_number ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">@include('shared.company-machinery.status', ['status' => $machinery->status])</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('prestataire.machinery.show', $machinery) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Consulter</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $machineryItems->links() }}</div>
            @endif
        </div>
    </section>
@endsection
