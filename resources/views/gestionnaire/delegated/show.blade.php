@extends('layouts.authenticated')

@section('pageTitle', 'Entreprises de '.$prestataire->name)

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-4 rounded-2xl border border-blue-100 bg-blue-50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div class="min-w-0">
                <a href="{{ route('gestionnaire.delegated.index') }}" class="text-sm font-semibold text-blue-800 hover:text-blue-950">← Retour à la liste des Prestataires</a>
                <h2 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Entreprises de {{ $prestataire->name }}</h2>
                <p class="mt-2 text-sm leading-6 text-blue-950">
                    Vous effectuez ces opérations pour le compte de :
                    <span class="font-bold">{{ $prestataire->name }}</span>
                </p>
            </div>
            <a href="{{ route('gestionnaire.delegated.companies.create', $prestataire) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                Ajouter une entreprise
            </a>
        </header>

        @if ($companies->isEmpty())
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <h3 class="text-lg font-semibold text-slate-900">Aucune entreprise pour le moment</h3>
                <p class="mt-2 text-sm text-slate-500">Créez la première entreprise pour commencer à préparer le dossier du Prestataire.</p>
                <a href="{{ route('gestionnaire.delegated.companies.create', $prestataire) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    Ajouter une entreprise
                </a>
            </section>
        @else
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Entreprises du Prestataire">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h3 class="font-semibold text-slate-900">Entreprises</h3>
                    <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $companies->count() }}</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($companies as $company)
                        <li>
                            <details name="delegated-companies" class="group/company">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:px-6 marker:hidden">
                                    <div class="min-w-0">
                                        <h4 class="break-words font-semibold text-slate-900">{{ $company->name }}</h4>
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
                                        <div class="mt-2">
                                            @if ($company->status === \App\Models\Company::STATUS_DRAFT)
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Brouillon</span>
                                            @else
                                                @include('shared.document-status-badge', ['status' => $company->status])
                                            @endif
                                        </div>
                                    </div>
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/company:rotate-180" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </summary>
                                <div class="border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                                    @if ($company->contract_start_date || $company->contract_end_date)
                                        <p class="text-sm text-slate-600">
                                            Contrat :
                                            {{ $company->contract_start_date?->format('d/m/Y') ?? 'date de début non renseignée' }}
                                            –
                                            {{ $company->contract_end_date?->format('d/m/Y') ?? 'date de fin non renseignée' }}
                                        </p>
                                    @endif
                                    @if (filled($company->additional_info))
                                        <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{{ $company->additional_info }}</p>
                                    @endif
                                    <a href="{{ route('gestionnaire.delegated.companies.show', [$prestataire, $company]) }}" class="mt-3 inline-flex min-h-10 items-center justify-center rounded-xl border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-50">
                                        Voir les détails
                                    </a>
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
