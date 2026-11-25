@extends('layouts.authenticated')

@section('pageTitle', 'Détails de l’entreprise')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 rounded-2xl border border-blue-100 bg-blue-50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div class="min-w-0">
                <a href="{{ route('gestionnaire.delegated.show', $prestataire) }}" class="text-sm font-semibold text-blue-800 hover:text-blue-950">← Retour aux entreprises</a>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Détails de {{ $company->name }}</h2>
                <p class="mt-2 text-sm leading-6 text-blue-950">
                    Vous effectuez ces opérations pour le compte de :
                    <span class="font-bold">{{ $prestataire->name }}</span>
                </p>
            </div>
        </header>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
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
            @endphp
            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="company-{{ $company->id }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Entreprise</p>
                        <h3 id="company-{{ $company->id }}" class="mt-1 break-words text-xl font-bold text-slate-900">{{ $company->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $company->activity ?: 'Activité non renseignée' }} · {{ $company->assignment ?: 'Affectation non renseignée' }}</p>
                        @if ($company->contract_start_date || $company->contract_end_date)
                            <p class="mt-1 text-sm text-slate-500">
                                Contrat :
                                {{ $company->contract_start_date?->format('d/m/Y') ?? 'date de début non renseignée' }}
                                –
                                {{ $company->contract_end_date?->format('d/m/Y') ?? 'date de fin non renseignée' }}
                            </p>
                        @endif
                        @if (filled($company->additional_info))
                            <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{{ $company->additional_info }}</p>
                        @endif
                        <div class="mt-2">
                            @if ($company->status === \App\Models\Company::STATUS_DRAFT)
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Brouillon</span>
                            @else
                                @include('shared.document-status-badge', ['status' => $company->status])
                            @endif
                        </div>
                        @if ($company->status === 'rejected' && filled($company->rejection_reason))
                            <p class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">
                                <span class="font-semibold">Motif de rejet :</span> {{ $company->rejection_reason }}
                            </p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($company->status === \App\Models\Company::STATUS_PENDING && auth()->user()->hasPermission('entreprises.validate'))
                            <form method="POST" action="{{ route('gestionnaire.delegated.companies.approve', [$prestataire, $company]) }}">
                                @csrf
                                @if ($approvalBlockers === [])
                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-700 px-3 text-sm font-semibold text-white hover:bg-emerald-800">Valider l’entreprise</button>
                                @else
                                    <button type="button" disabled aria-describedby="company-approval-blockers-{{ $company->id }}" class="inline-flex min-h-9 cursor-not-allowed items-center justify-center rounded-lg bg-slate-300 px-3 text-sm font-semibold text-slate-600">Valider l’entreprise</button>
                                @endif
                            </form>
                        @endif
                        @if (in_array($company->status, ['draft', 'rejected'], true))
                            <a href="{{ route('gestionnaire.delegated.companies.edit', [$prestataire, $company]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
                            <form method="POST" action="{{ route('gestionnaire.delegated.companies.submit', [$prestataire, $company]) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800">Soumettre</button>
                            </form>
                        @endif
                    </div>
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

                <details name="delegated-company-sections-{{ $company->id }}" class="group/section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span>Documents entreprise ({{ $depositedCompanyDocumentCount }}/{{ count($expectedCompanyDocumentTypes) }})</span>
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
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span>Employés</span>
                            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $company->employees->count() }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <a href="{{ route('gestionnaire.delegated.employees.create', $prestataire) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Ajouter</a>
                        </div>
                        <div class="mt-3 space-y-4">
                        @forelse ($company->employees as $employee)
                            <details name="delegated-employees-{{ $company->id }}" class="group/employee overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 marker:hidden">
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
                                                <a href="{{ route('gestionnaire.delegated.employees.edit', [$prestataire, $employee]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
                                                <form method="POST" action="{{ route('gestionnaire.delegated.employees.submit', [$prestataire, $employee]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800">Soumettre</button>
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
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span>Engins et machines</span>
                            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $company->machinery->count() }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <a href="{{ route('gestionnaire.delegated.machinery.create', $prestataire) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Ajouter</a>
                        </div>
                        <div class="mt-3 space-y-4">
                        @forelse ($company->machinery as $machinery)
                            <details name="delegated-machinery-{{ $company->id }}" class="group/machinery overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 marker:hidden">
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
                                                <a href="{{ route('gestionnaire.delegated.machinery.edit', [$prestataire, $machinery]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
                                                <form method="POST" action="{{ route('gestionnaire.delegated.machinery.submit', [$prestataire, $machinery]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800">Soumettre</button>
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
                    <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-base font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:px-6 marker:hidden">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <span>Outillages</span>
                            <span class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-700">{{ $company->toolings->count() }}</span>
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 text-slate-500 transition-transform group-open/section:rotate-180" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="border-t border-slate-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex justify-end">
                            <a href="{{ route('gestionnaire.delegated.toolings.create', $prestataire) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Ajouter</a>
                        </div>
                        <div class="mt-3 space-y-4">
                        @forelse ($company->toolings as $tooling)
                            <details name="delegated-toolings-{{ $company->id }}" class="group/tooling overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 marker:hidden">
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
                                                <a href="{{ route('gestionnaire.delegated.toolings.edit', [$prestataire, $tooling]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
                                                <form method="POST" action="{{ route('gestionnaire.delegated.toolings.submit', [$prestataire, $tooling]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800">Soumettre</button>
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
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <h3 class="text-lg font-semibold text-slate-900">Aucune entreprise pour le moment</h3>
                <p class="mt-2 text-sm text-slate-500">Créez la première entreprise pour commencer à préparer le dossier du Prestataire.</p>
                <a href="{{ route('gestionnaire.delegated.companies.create', $prestataire) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Ajouter une entreprise</a>
            </section>
        @endforelse
    </div>
    @include('shared.company-list-import-modal')
@endsection
