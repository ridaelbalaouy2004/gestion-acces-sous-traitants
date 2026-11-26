@extends('layouts.authenticated')

@section('pageTitle', 'Résultat de l’importation')

@section('content')
    @php($isDelegated = isset($delegatedPrestataire))
    <div class="mx-auto grid max-w-4xl gap-6">
        @if ($isDelegated)
            @include('gestionnaire.delegated._context', [
                'prestataire' => $delegatedPrestataire,
                'company' => $company,
                'currentLabel' => 'Résultat de l’importation',
                'backUrl' => $cancelUrl,
                'backLabel' => 'Retour au dossier',
            ])
        @endif
        <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6">
                        <path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-emerald-800">Importation confirmée</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Résultat de l’importation</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $summary['created_label'] }} dans {{ $summary['destination'] }} depuis
                        <span class="inline-block max-w-full truncate align-bottom" title="{{ $summary['source_file'] }}">« {{ $summary['source_file'] }} »</span>.
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-3">
            <div class="rounded-xl bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Créés</p>
                <p class="mt-1 text-xl font-bold text-emerald-900">{{ $summary['created_count'] }}</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Doublons ignorés</p>
                <p class="mt-1 text-xl font-bold text-amber-900">{{ $summary['duplicate_count'] }} {{ $summary['duplicate_count'] === 1 ? 'doublon ignoré' : 'doublons ignorés' }}</p>
            </div>
            <div class="rounded-xl bg-rose-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-800">Lignes rejetées</p>
                <p class="mt-1 text-xl font-bold text-rose-900">{{ $summary['rejected_count'] }} {{ $summary['rejected_count'] === 1 ? 'ligne rejetée' : 'lignes rejetées' }}</p>
            </div>
        </section>

        @if ($summary['rejected_rows'])
            <section class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Lignes à corriger</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($summary['rejected_rows'] as $row)
                        <li class="rounded-xl bg-rose-50 p-3">
                            <p class="text-sm font-semibold text-rose-900">Ligne Excel {{ $row['excel_row'] }}</p>
                            <ul class="mt-1 list-inside list-disc text-sm text-rose-800">
                                @foreach ($row['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($summary['duplicate_rows'])
            <section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Doublons ignorés</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($summary['duplicate_rows'] as $row)
                        <li class="text-sm text-amber-900">Ligne Excel {{ $row['excel_row'] }} — {{ $row['duplicate_reason'] }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="flex flex-wrap gap-3">
            <a href="{{ $cancelUrl }}" @class([
                'inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                'bg-[#164f43] hover:bg-[#123e36] focus-visible:outline-[#164f43]' => $isDelegated,
                'bg-slate-900 hover:bg-slate-700 focus-visible:outline-slate-700' => ! $isDelegated,
            ])>Retour au dossier</a>
        </div>
    </div>
@endsection
