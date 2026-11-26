@extends('layouts.authenticated')

@section('pageTitle', 'Entreprises de '.$prestataire->name)

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @include('gestionnaire.delegated._context', [
            'prestataire' => $prestataire,
            'currentLabel' => 'Entreprises',
            'backUrl' => route('gestionnaire.delegated.index'),
            'backLabel' => 'Retour à la liste des Prestataires',
        ])

        <header class="flex flex-col gap-5 rounded-3xl border border-[#d9eee2] bg-[#fffefa] p-5 shadow-sm sm:flex-row sm:items-end sm:justify-between sm:p-7">
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-wide text-[#164f43]">Dossier délégué</p>
                <h1 class="mt-2 break-words text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                    Entreprises de {{ $prestataire->name }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    Consultez et préparez les entreprises de ce Prestataire. Les statuts et validations suivent le workflow habituel.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div aria-label="{{ $companies->count() }} entreprises" class="rounded-2xl border border-[#d9eee2] bg-white px-4 py-3">
                    <p class="text-xs font-medium text-slate-500">Entreprises</p>
                    <p class="mt-0.5 text-2xl font-bold leading-7 text-[#123e36]">{{ $companies->count() }}</p>
                </div>
                <a href="{{ route('gestionnaire.delegated.companies.create', $prestataire) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#164f43] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    Ajouter une entreprise
                </a>
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-2xl border border-[#c7e4d3] bg-[#d9eee2] px-4 py-3 text-sm font-medium text-[#123e36]">
                {{ session('status') }}
            </div>
        @endif

        @if ($companies->isEmpty())
            <section class="rounded-3xl border border-dashed border-[#b8d7c6] bg-[#fffefa] px-5 py-12 text-center shadow-sm sm:px-8">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7">
                        <path d="M4 20V5.5A1.5 1.5 0 0 1 5.5 4H15v16M15 9h3.5A1.5 1.5 0 0 1 20 10.5V20M2.5 20h19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <h2 class="mt-4 text-lg font-bold text-slate-900">Aucune entreprise pour le moment</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">
                    Ajoutez une entreprise pour commencer à préparer le dossier de {{ $prestataire->name }}.
                </p>
                <a href="{{ route('gestionnaire.delegated.companies.create', $prestataire) }}" class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#164f43] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                    Ajouter une entreprise
                </a>
            </section>
        @else
            <section aria-label="Entreprises du Prestataire" class="space-y-4">
                @foreach ($companies as $company)
                    @php
                        [$statusLabel, $statusClasses] = match ($company->status) {
                            \App\Models\Company::STATUS_DRAFT => ['Brouillon', 'border-slate-200 bg-slate-100 text-slate-700'],
                            \App\Models\Company::STATUS_PENDING => ['En attente', 'border-amber-200 bg-amber-50 text-amber-800'],
                            \App\Models\Company::STATUS_VALIDATED => ['Validée', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
                            \App\Models\Company::STATUS_REJECTED => ['Rejetée', 'border-rose-200 bg-rose-50 text-rose-800'],
                            default => [$company->status, 'border-slate-200 bg-slate-100 text-slate-700'],
                        };
                    @endphp
                    <article class="overflow-hidden rounded-3xl border border-slate-200 bg-[#fffefa] shadow-sm transition hover:border-[#c7e4d3] hover:shadow-md">
                        <div class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                            <path d="M4 20V5.5A1.5 1.5 0 0 1 5.5 4H15v16M15 9h3.5A1.5 1.5 0 0 1 20 10.5V20M2.5 20h19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="break-words text-lg font-bold text-slate-950">{{ $company->name }}</h2>
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
                                        </div>
                                        @if ($company->activity || $company->assignment)
                                            <p class="mt-1 break-words text-sm text-slate-600">
                                                @if ($company->activity)
                                                    {{ $company->activity }}
                                                @endif
                                                @if ($company->activity && $company->assignment)
                                                    <span aria-hidden="true"> · </span>
                                                @endif
                                                @if ($company->assignment)
                                                    {{ $company->assignment }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if ($company->contract_start_date || $company->contract_end_date || filled($company->additional_info))
                                    <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                                        @if ($company->contract_start_date || $company->contract_end_date)
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Période du contrat</dt>
                                                <dd class="mt-1 text-sm font-medium text-slate-800">
                                                    {{ $company->contract_start_date?->format('d/m/Y') ?? 'Début non renseigné' }}
                                                    <span aria-hidden="true"> – </span>
                                                    {{ $company->contract_end_date?->format('d/m/Y') ?? 'Fin non renseignée' }}
                                                </dd>
                                            </div>
                                        @endif
                                        @if (filled($company->additional_info))
                                            <div class="sm:col-span-2">
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informations complémentaires</dt>
                                                <dd class="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ $company->additional_info }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4 lg:shrink-0 lg:border-0 lg:pt-0">
                                @if (in_array($company->status, [\App\Models\Company::STATUS_DRAFT, \App\Models\Company::STATUS_REJECTED], true))
                                    <a href="{{ route('gestionnaire.delegated.companies.edit', [$prestataire, $company]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[#c7e4d3] bg-white px-4 py-2 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                                        Modifier
                                    </a>
                                @endif
                                <a href="{{ route('gestionnaire.delegated.companies.show', [$prestataire, $company]) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-[#164f43] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                                    Consulter le dossier
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
@endsection
