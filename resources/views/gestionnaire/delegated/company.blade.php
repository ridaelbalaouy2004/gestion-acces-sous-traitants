@extends('layouts.authenticated')

@section('pageTitle', 'Dossier entreprise')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @include('gestionnaire.delegated._context', [
            'prestataire' => $prestataire,
            'company' => $company,
            'showCompanyInContext' => false,
            'currentLabel' => 'Dossier entreprise',
            'backUrl' => route('gestionnaire.delegated.show', $prestataire),
            'backLabel' => 'Retour aux entreprises',
        ])

        <header class="rounded-2xl border border-[#d9eee2] bg-[#fffefa] px-5 py-4 shadow-sm sm:px-6">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#164f43]">Gestion déléguée</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#123e36] sm:text-3xl">Dossier entreprise</h1>
            <p class="mt-1 text-sm text-slate-600">Informations, documents et ressources de l’entreprise.</p>
        </header>

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">L’opération n’a pas pu être effectuée.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @forelse ($companies as $company)
            @php
                $expectedCompanyDocumentTypes = \App\Models\CompanyDocument::allTypes();
                $depositedCompanyDocumentCount = $company->documentsForDisplay
                    ->filter(fn (\App\Models\CompanyDocument $document): bool => in_array($document->type, $expectedCompanyDocumentTypes, true) && filled($document->file_path))
                    ->count();
                $expectedCompanyDocumentCount = count($expectedCompanyDocumentTypes);
                $documentCompletion = $expectedCompanyDocumentCount > 0
                    ? (int) round($depositedCompanyDocumentCount / $expectedCompanyDocumentCount * 100)
                    : 0;
                $employeeCount = $company->employees->count();
                $machineryCount = $company->machinery->count();
                $toolingCount = $company->toolings->count();
            @endphp
            <section class="space-y-5" aria-labelledby="company-{{ $company->id }}">
                <section class="overflow-hidden rounded-3xl border border-[#d9eee2] bg-[#fffefa] shadow-sm" aria-label="Présentation de l’entreprise">
                    <div class="border-b border-[#e7f1ea] bg-gradient-to-r from-[#f2f8f3] to-[#fffefa] p-5 sm:p-7">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#d9eee2] text-[#164f43]">
                                    <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7" aria-hidden="true">
                                        <path d="M4 20V7.5L12 4l8 3.5V20M2.5 20h19M8 9h1m6 0h1M8 12.5h1m6 0h1M10 20v-4h4v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#164f43]">Entreprise</p>
                                    <h2 id="company-{{ $company->id }}" class="mt-1 break-words text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">{{ $company->name }}</h2>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                                        @if (filled($company->activity))
                                            <span>{{ $company->activity }}</span>
                                        @endif
                                        @if (filled($company->assignment))
                                            @if (filled($company->activity))
                                                <span aria-hidden="true">·</span>
                                            @endif
                                            <span>{{ $company->assignment }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                @if ($company->status === \App\Models\Company::STATUS_DRAFT)
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">Brouillon</span>
                                @else
                                    @include('shared.document-status-badge', ['status' => $company->status])
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 p-5 sm:p-7">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @if ($company->contract_start_date)
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Début du contrat</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ $company->contract_start_date->format('d/m/Y') }}</p>
                                </div>
                            @endif
                            @if ($company->contract_end_date)
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fin du contrat</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ $company->contract_end_date->format('d/m/Y') }}</p>
                                </div>
                            @endif
                        </div>

                        @if (filled($company->additional_info))
                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informations complémentaires</p>
                                <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ $company->additional_info }}</p>
                            </div>
                        @endif

                        @if ($company->status === 'rejected' && filled($company->rejection_reason))
                            <p class="whitespace-pre-wrap break-words rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                <span class="font-semibold">Motif de rejet :</span> {{ $company->rejection_reason }}
                            </p>
                        @endif

                        <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        @if ($company->status === \App\Models\Company::STATUS_PENDING && auth()->user()->hasPermission('entreprises.validate'))
                            <form method="POST" action="{{ route('gestionnaire.delegated.companies.approve', [$prestataire, $company]) }}">
                                @csrf
                                @if ($approvalBlockers === [])
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">Valider l’entreprise</button>
                                @else
                                    <button type="button" disabled aria-describedby="company-approval-blockers-{{ $company->id }}" class="inline-flex min-h-9 cursor-not-allowed items-center justify-center rounded-lg bg-slate-300 px-3 text-sm font-semibold text-slate-600">Valider l’entreprise</button>
                                @endif
                            </form>
                        @endif
                        @if (in_array($company->status, ['draft', 'rejected'], true))
                            <a href="{{ route('gestionnaire.delegated.companies.edit', [$prestataire, $company]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[#c7e4d3] bg-white px-4 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">Modifier l’entreprise</a>
                            <form method="POST" action="{{ route('gestionnaire.delegated.companies.submit', [$prestataire, $company]) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 text-sm font-semibold text-white transition hover:bg-[#123e36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">Soumettre</button>
                            </form>
                        @endif
                        </div>

                        @if ($company->status === \App\Models\Company::STATUS_PENDING && $approvalBlockers !== [])
                            <div id="company-approval-blockers-{{ $company->id }}" role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <p class="font-semibold">Validation de l’entreprise indisponible : les documents requis doivent être déposés et validés, et les documents déposés doivent être régularisés.</p>
                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    @foreach ($approvalBlockers as $blocker)
                                        <li>{{ $blocker['label'] }} : {{ $blocker['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="Résumé des ressources">
                    <article class="rounded-2xl border border-[#d9eee2] bg-[#fffefa] p-4 shadow-sm sm:p-5" aria-label="Documents : {{ $depositedCompanyDocumentCount }} sur {{ $expectedCompanyDocumentCount }} requis">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-600">Documents</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <path d="M7 3.75h7l4 4V20a.75.75 0 0 1-.75.75h-10.5A.75.75 0 0 1 6 20V4.5a.75.75 0 0 1 .75-.75ZM14 4v4h4M9 13h6m-6 3h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-[#123e36]">{{ $depositedCompanyDocumentCount }}/{{ $expectedCompanyDocumentCount }}</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Complétude des documents requis" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $documentCompletion }}">
                            <div class="h-full rounded-full bg-[#164f43] transition-[width]" style="width: {{ $documentCompletion }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Documents requis déposés</p>
                    </article>
                    <article class="rounded-2xl border border-[#d9eee2] bg-[#fffefa] p-4 shadow-sm sm:p-5" aria-label="Employés : {{ $employeeCount }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-600">Employés</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef4f0] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                                    <path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8m1 2.2a5 5 0 0 1 3.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-[#123e36]">{{ $employeeCount }}</p>
                        <p class="mt-2 text-xs text-slate-500">Employés associés</p>
                    </article>
                    <article class="rounded-2xl border border-[#d9eee2] bg-[#fffefa] p-4 shadow-sm sm:p-5" aria-label="Engins et machines : {{ $machineryCount }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-600">Engins &amp; machines</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef4f0] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <path d="M4 15h13l2 2v2H4v-4Zm3 0V9h6l3 6M8 19a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm9 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" transform="translate(0 -2)" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-[#123e36]">{{ $machineryCount }}</p>
                        <p class="mt-2 text-xs text-slate-500">Ressources enregistrées</p>
                    </article>
                    <article class="rounded-2xl border border-[#d9eee2] bg-[#fffefa] p-4 shadow-sm sm:p-5" aria-label="Outillages : {{ $toolingCount }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-600">Outillages</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eef4f0] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <path d="m14.5 6.5 3 3M5 19l9.5-9.5m-2-2a5 5 0 0 0 6 6l-3.5 3.5-6-6L12.5 7A5 5 0 0 0 12 3.5l-2 2L8 5l-2 2 .5 2-2 2A5 5 0 0 0 12 13" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-[#123e36]">{{ $toolingCount }}</p>
                        <p class="mt-2 text-xs text-slate-500">Ressources enregistrées</p>
                    </article>
                </section>

                <details name="delegated-company-sections-{{ $company->id }}" class="group/section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <path d="M7 3.75h7l4 4V20a.75.75 0 0 1-.75.75h-10.5A.75.75 0 0 1 6 20V4.5a.75.75 0 0 1 .75-.75ZM14 4v4h4M9 13h6m-6 3h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>Documents entreprise ({{ $depositedCompanyDocumentCount }}/{{ $expectedCompanyDocumentCount }})</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        @include('gestionnaire.delegated._documents', [
                            'documents' => $company->documentsForDisplay,
                            'record' => $company,
                            'prestataire' => $prestataire,
                            'kind' => 'company',
                            'uploadRoute' => 'gestionnaire.delegated.companies.documents.store',
                            'downloadRoute' => 'gestionnaire.companies.documents.download',
                        ])
                    </div>
                </details>

                <details name="delegated-company-sections-{{ $company->id }}" class="group/section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#eef4f0] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                                    <path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8m1 2.2a5 5 0 0 1 3.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span>Employés</span>
                            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $employeeCount }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <a href="{{ route('gestionnaire.delegated.employees.create', ['prestataire' => $prestataire, 'company_id' => $company->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-[#c7e4d3] bg-white px-3 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2]">Ajouter un employé</a>
                        </div>
                        <div class="mt-3 space-y-4">
                        @forelse ($company->employees as $employee)
                            <details name="delegated-employees-{{ $company->id }}" class="group/employee overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 font-semibold text-slate-900 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] marker:hidden">
                                    <span class="min-w-0 break-words">{{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: 'Employé sans nom' }}</span>
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/employee:rotate-180" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </summary>
                                <div class="space-y-4 border-t border-slate-100 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="mt-1 text-sm text-slate-500">{{ $employee->position ?: 'Fonction non renseignée' }}{{ $employee->cin ? ' · CIN '.$employee->cin : '' }}</p>
                                            @if (filled($employee->locker_number))
                                                <p class="mt-1 text-xs text-slate-500">Numéro de cadenas : {{ $employee->locker_number }}</p>
                                            @endif
                                            <div class="mt-2">@include('shared.company-employees.status', ['status' => $employee->status])</div>
                                            @if ($employee->status === 'rejected' && filled($employee->rejection_reason))
                                                <p class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800"><span class="font-semibold">Motif de rejet :</span> {{ $employee->rejection_reason }}</p>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if (in_array($employee->status, ['draft', 'rejected'], true))
                                                <a href="{{ route('gestionnaire.delegated.employees.edit', [$prestataire, $employee]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[#c7e4d3] bg-white px-4 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2]">Modifier</a>
                                                <form method="POST" action="{{ route('gestionnaire.delegated.employees.submit', [$prestataire, $employee]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 text-sm font-semibold text-white transition hover:bg-[#123e36]">Soumettre</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    @include('gestionnaire.delegated._documents', [
                                        'documents' => $employee->documentsForDisplay,
                                        'record' => $employee,
                                        'prestataire' => $prestataire,
                                        'kind' => 'employee',
                                        'uploadRoute' => 'gestionnaire.delegated.employees.documents.store',
                                        'downloadRoute' => 'gestionnaire.employees.documents.download',
                                    ])
                                </div>
                            </details>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Aucun employé n’est associé à cette entreprise.</p>
                        @endforelse
                        </div>
                    </div>
                </details>

                <details name="delegated-company-sections-{{ $company->id }}" class="group/section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#eef4f0] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <path d="M4 15h13l2 2v2H4v-4Zm3 0V9h6l3 6M8 19a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm9 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" transform="translate(0 -2)" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>Engins et machines</span>
                            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $machineryCount }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <a href="{{ route('gestionnaire.delegated.machinery.create', ['prestataire' => $prestataire, 'company_id' => $company->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-[#c7e4d3] bg-white px-3 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2]">Ajouter un engin</a>
                        </div>
                        <div class="mt-3 space-y-4">
                        @forelse ($company->machinery as $machinery)
                            <details name="delegated-machinery-{{ $company->id }}" class="group/machinery overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 font-semibold text-slate-900 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] marker:hidden">
                                    <span class="min-w-0 break-words">{{ $machinery->designation }}</span>
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/machinery:rotate-180" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </summary>
                                <div class="space-y-4 border-t border-slate-100 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-sm text-slate-500">
                                                {{ $machinery->category }}
                                                @if ($machinery->assignment)
                                                    · {{ $machinery->assignment }}
                                                @endif
                                                @if ($machinery->brand || $machinery->model)
                                                    · {{ trim(($machinery->brand ?? '').' '.($machinery->model ?? '')) }}
                                                @endif
                                            </p>
                                            @if ($machinery->serial_number || $machinery->registration_number)
                                                <p class="mt-1 text-xs text-slate-500">
                                                    @if ($machinery->serial_number) Série : {{ $machinery->serial_number }} @endif
                                                    @if ($machinery->registration_number) · Immatriculation : {{ $machinery->registration_number }} @endif
                                                </p>
                                            @endif
                                            @if (filled($machinery->description))
                                                <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{{ $machinery->description }}</p>
                                            @endif
                                            <div class="mt-2">@include('shared.company-machinery.status', ['status' => $machinery->status])</div>
                                            @if ($machinery->status === 'rejected' && filled($machinery->rejection_reason))
                                                <p class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800"><span class="font-semibold">Motif de rejet :</span> {{ $machinery->rejection_reason }}</p>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if (in_array($machinery->status, ['draft', 'rejected'], true))
                                                <a href="{{ route('gestionnaire.delegated.machinery.edit', [$prestataire, $machinery]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[#c7e4d3] bg-white px-4 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2]">Modifier</a>
                                                <form method="POST" action="{{ route('gestionnaire.delegated.machinery.submit', [$prestataire, $machinery]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 text-sm font-semibold text-white transition hover:bg-[#123e36]">Soumettre</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    @include('gestionnaire.delegated._documents', [
                                        'documents' => $machinery->documents,
                                        'record' => $machinery,
                                        'prestataire' => $prestataire,
                                        'kind' => 'machinery',
                                        'uploadRoute' => 'gestionnaire.delegated.machinery.documents.store',
                                        'downloadRoute' => 'gestionnaire.machinery.documents.download',
                                    ])
                                </div>
                            </details>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Aucun engin n’est associé à cette entreprise.</p>
                        @endforelse
                        </div>
                    </div>
                </details>

                <details name="delegated-company-sections-{{ $company->id }}" class="group/section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 transition hover:bg-[#f5faf6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#eef4f0] text-[#164f43]" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                                    <path d="m14.5 6.5 3 3M5 19l9.5-9.5m-2-2a5 5 0 0 0 6 6l-3.5 3.5-6-6L12.5 7A5 5 0 0 0 12 3.5l-2 2L8 5l-2 2 .5 2-2 2A5 5 0 0 0 12 13" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>Outillages</span>
                            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $toolingCount }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <a href="{{ route('gestionnaire.delegated.toolings.create', ['prestataire' => $prestataire, 'company_id' => $company->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-[#c7e4d3] bg-white px-3 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2]">Ajouter un outillage</a>
                        </div>
                        <div class="mt-3 space-y-4">
                        @forelse ($company->toolings as $tooling)
                            <details name="delegated-toolings-{{ $company->id }}" class="group/tooling overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 font-semibold text-slate-900 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#164f43] marker:hidden">
                                    <span class="min-w-0 break-words">{{ $tooling->designation }}</span>
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/tooling:rotate-180" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </summary>
                                <div class="space-y-4 border-t border-slate-100 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-sm text-slate-500">{{ $tooling->category }} · Quantité : {{ $tooling->quantity }}</p>
                                            @if ($tooling->internal_reference || $tooling->brand || $tooling->model || $tooling->serial_number)
                                                <p class="mt-1 text-xs text-slate-500">
                                                    @if ($tooling->internal_reference) Référence : {{ $tooling->internal_reference }} @endif
                                                    @if ($tooling->brand || $tooling->model) · {{ trim(($tooling->brand ?? '').' '.($tooling->model ?? '')) }} @endif
                                                    @if ($tooling->serial_number) · Série : {{ $tooling->serial_number }} @endif
                                                </p>
                                            @endif
                                            @if ($tooling->location || $tooling->physical_condition)
                                                <p class="mt-1 text-xs text-slate-500">
                                                    @if ($tooling->location) Emplacement : {{ $tooling->location }} @endif
                                                    @if ($tooling->physical_condition) · État : {{ $tooling->physical_condition }} @endif
                                                </p>
                                            @endif
                                            @if (filled($tooling->description))
                                                <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{{ $tooling->description }}</p>
                                            @endif
                                            @if (filled($tooling->observations))
                                                <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-600">{{ $tooling->observations }}</p>
                                            @endif
                                            <div class="mt-2">@include('shared.company-toolings.status', ['status' => $tooling->status])</div>
                                            @if ($tooling->status === 'rejected' && filled($tooling->rejection_reason))
                                                <p class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800"><span class="font-semibold">Motif de rejet :</span> {{ $tooling->rejection_reason }}</p>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if (in_array($tooling->status, ['draft', 'rejected'], true))
                                                <a href="{{ route('gestionnaire.delegated.toolings.edit', [$prestataire, $tooling]) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[#c7e4d3] bg-white px-4 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2]">Modifier</a>
                                                <form method="POST" action="{{ route('gestionnaire.delegated.toolings.submit', [$prestataire, $tooling]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 text-sm font-semibold text-white transition hover:bg-[#123e36]">Soumettre</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    @include('gestionnaire.delegated._documents', [
                                        'documents' => $tooling->documents,
                                        'record' => $tooling,
                                        'prestataire' => $prestataire,
                                        'kind' => 'tooling',
                                        'uploadRoute' => 'gestionnaire.delegated.toolings.documents.store',
                                        'downloadRoute' => 'gestionnaire.toolings.documents.download',
                                    ])
                                </div>
                            </details>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-200 px-4 py-5 text-sm text-slate-500">Aucun outillage n’est associé à cette entreprise.</p>
                        @endforelse
                        </div>
                    </div>
                </details>
            </section>
        @empty
            <section class="rounded-3xl border border-dashed border-[#b8d7c6] bg-[#fffefa] p-8 text-center shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Aucune entreprise pour le moment</h3>
                <p class="mt-2 text-sm text-slate-500">Créez la première entreprise pour commencer à préparer le dossier du Prestataire.</p>
                <a href="{{ route('gestionnaire.delegated.companies.create', $prestataire) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl bg-[#164f43] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#123e36]">Ajouter une entreprise</a>
            </section>
        @endforelse
    </div>
    @include('shared.company-list-import-modal')
@endsection
