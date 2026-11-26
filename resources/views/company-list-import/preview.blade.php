@extends('layouts.authenticated')

@section('pageTitle', 'Aperçu de l’importation')

@section('content')
    @php
        $isDelegated = isset($delegatedPrestataire);
        $selectedRowStrings = is_array($selectedRows)
            ? array_map('strval', $selectedRows)
            : collect($preview['rows'])
                ->filter(fn (array $row): bool => $row['status'] === 'valid')
                ->pluck('excel_row')
                ->map(fn (int $row): string => (string) $row)
                ->all();
        $newRecordsLabel = match ($preview['type']) {
            \App\Models\CompanyDocument::TYPE_LISTE_PERSONNEL => $preview['valid_rows'].($preview['valid_rows'] === 1 ? ' nouvel employé' : ' nouveaux employés'),
            \App\Models\CompanyDocument::TYPE_LISTE_MATERIEL => $preview['valid_rows'].($preview['valid_rows'] === 1 ? ' nouvel équipement' : ' nouveaux équipements'),
            default => $preview['valid_rows'].' nouveaux enregistrements',
        };
    @endphp

    <div class="mx-auto grid max-w-7xl gap-6">
        @if ($isDelegated)
            @include('gestionnaire.delegated._context', [
                'prestataire' => $delegatedPrestataire,
                'company' => $company,
                'currentLabel' => 'Aperçu de l’importation',
                'backUrl' => $cancelUrl,
                'backLabel' => 'Retour au dossier',
            ])
        @endif
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-500">{{ $company->name }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Aperçu de l’importation</h1>
                <p class="mt-2 text-sm text-slate-600">Le fichier ne crée aucun enregistrement tant que vous n’avez pas confirmé l’importation.</p>
            </div>
            <a href="{{ $cancelUrl }}" @class([
                'inline-flex min-h-10 items-center justify-center rounded-xl border px-4 py-2 text-sm font-semibold transition',
                'border-[#c7e4d3] bg-white text-[#164f43] hover:bg-[#d9eee2]' => $isDelegated,
                'border-slate-300 text-slate-700 hover:bg-slate-50' => ! $isDelegated,
            ])>Annuler</a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-semibold">L’importation n’a pas pu être effectuée.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fichier</p>
                <p class="mt-1 truncate max-w-[200px] sm:max-w-xs block text-sm font-medium text-slate-900" title="{{ $preview['file_name'] }}">{{ $preview['file_name'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destination</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ $preview['destination'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lignes détectées</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ $preview['total_rows'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nouveaux enregistrements</p>
                <p class="mt-1 text-sm font-medium text-emerald-800">{{ $newRecordsLabel }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">À vérifier</p>
                <p class="mt-1 text-sm font-medium text-rose-800">{{ $preview['invalid_rows'] }} ligne(s) invalide(s), {{ $preview['duplicate_rows'] }} doublon(s)</p>
            </div>
        </section>

        @if ($preview['fatal_error'])
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $preview['fatal_error'] }}
            </div>
        @else
            <form method="POST" action="{{ $confirmUrl }}" class="grid gap-6">
                @csrf
                <input type="hidden" name="source_hash" value="{{ $preview['source_hash'] }}">

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Correspondance des colonnes</h2>
                            <p class="mt-1 text-sm text-slate-600">Vérifiez les champs détectés et choisissez manuellement les colonnes qui ne sont pas reconnues.</p>
                        </div>
                        @if ($preview['sheet_name'])
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">Feuille : {{ $preview['sheet_name'] }}</span>
                        @endif
                    </div>

                    @if ($preview['mapping_errors'])
                        <ul class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            @foreach ($preview['mapping_errors'] as $mappingError)
                                <li>{{ $mappingError }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($preview['unrecognized_columns'])
                        <p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                            Colonne non reconnue :
                            @foreach ($preview['unrecognized_columns'] as $column)
                                <span class="font-semibold">{{ $preview['headers'][$column] ?? $column }}</span>@if (! $loop->last), @endif
                            @endforeach
                        </p>
                    @endif

                    @if ($preview['ambiguous_columns'])
                        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                            Certaines colonnes sont ambiguës. Associez-les manuellement avant de confirmer l’importation.
                        </p>
                    @endif

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($preview['definitions'] as $field => $definition)
                            <div>
                                <label for="mapping-{{ $field }}" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ $definition['label'] }} @if ($definition['required'])<span class="text-rose-700">(obligatoire)</span>@endif
                                </label>
                                <select
                                    id="mapping-{{ $field }}"
                                    name="mapping[{{ $field }}]"
                                    class="form-control min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900"
                                >
                                    <option value="">Aucune colonne</option>
                                    @foreach ($preview['headers'] as $column => $header)
                                        <option value="{{ $column }}" @selected(($preview['mappings'][$field] ?? null) === $column)>{{ $column }} — {{ $header }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Données détectées</h2>
                            <p class="mt-1 text-sm text-slate-600">Les doublons et lignes invalides ne seront pas importés.</p>
                        </div>
                        <button
                            type="submit"
                            name="action"
                            value="refresh"
                            @class([
                                'inline-flex min-h-10 items-center justify-center rounded-xl border px-4 py-2 text-sm font-semibold transition',
                                'border-[#c7e4d3] bg-white text-[#164f43] hover:bg-[#d9eee2]' => $isDelegated,
                                'border-slate-300 text-slate-700 hover:bg-slate-50' => ! $isDelegated,
                            ])
                        >
                            Actualiser l’aperçu
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">Importer</th>
                                    <th class="px-4 py-3">Ligne</th>
                                    @foreach ($preview['headers'] as $header)
                                        <th class="max-w-56 px-4 py-3">{{ $header }}</th>
                                    @endforeach
                                    <th class="px-4 py-3">Résultat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($preview['rows'] as $row)
                                    <tr class="{{ $row['status'] === 'invalid' ? 'bg-rose-50/60' : ($row['status'] === 'duplicate' ? 'bg-amber-50/60' : '') }}">
                                        <td class="px-4 py-3">
                                            @if ($row['status'] === 'valid')
                                                <input
                                                    type="checkbox"
                                                    name="selected_rows[]"
                                                    value="{{ $row['excel_row'] }}"
                                                    @checked(in_array((string) $row['excel_row'], $selectedRowStrings, true))
                                                    @class([
                                                        'h-4 w-4 rounded border-slate-300 focus:ring-2',
                                                        'text-[#164f43] focus:ring-[#164f43]' => $isDelegated,
                                                        'text-blue-700 focus:ring-blue-700' => ! $isDelegated,
                                                    ])
                                                    aria-label="Importer la ligne Excel {{ $row['excel_row'] }}"
                                                >
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">Ligne Excel {{ $row['excel_row'] }}</td>
                                        @foreach ($preview['headers'] as $column => $header)
                                            <td class="max-w-56 break-words px-4 py-3 text-slate-700">{{ $row['raw'][$column] ?? '—' }}</td>
                                        @endforeach
                                        <td class="min-w-52 px-4 py-3">
                                            @if ($row['status'] === 'duplicate')
                                                <p class="font-semibold text-amber-800">Doublon</p>
                                                <p class="mt-1 text-xs text-amber-900">{{ $row['duplicate_reason'] }}</p>
                                            @elseif ($row['status'] === 'invalid')
                                                <p class="font-semibold text-rose-800">Ligne invalide</p>
                                                <ul class="mt-1 list-inside list-disc text-xs text-rose-900">
                                                    @foreach ($row['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="font-semibold text-emerald-800">Nouvel enregistrement</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-5">
                        <a href="{{ $cancelUrl }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                        <button
                            type="submit"
                            name="action"
                            value="import"
                            @disabled($preview['valid_rows'] === 0 || $preview['mapping_errors'] !== [])
                            @class([
                                'inline-flex min-h-10 items-center justify-center rounded-xl px-5 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600',
                                'bg-[#164f43] hover:bg-[#123e36]' => $isDelegated,
                                'bg-blue-700 hover:bg-blue-800' => ! $isDelegated,
                            ])
                        >
                            Confirmer et importer les lignes sélectionnées
                        </button>
                    </div>
                </section>
            </form>
        @endif
    </div>
@endsection
