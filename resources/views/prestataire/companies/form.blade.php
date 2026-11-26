@extends('layouts.authenticated')

@section('pageTitle', isset($company) && $company->exists ? 'Modifier l’entreprise' : 'Ajouter une entreprise')

@section('content')
    <div @class([
        'mx-auto space-y-6' => isset($delegatedPrestataire),
        'mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7' => ! isset($delegatedPrestataire),
    ])>
        @if (isset($delegatedPrestataire))
            @include('gestionnaire.delegated._context', [
                'prestataire' => $delegatedPrestataire,
                'company' => $contextCompany ?? null,
                'currentLabel' => isset($company) && $company->exists ? 'Modifier l’entreprise' : 'Ajouter une entreprise',
                'backUrl' => $formCancelUrl,
                'backLabel' => 'Retour au dossier',
            ])
            <header class="rounded-3xl border border-[#d9eee2] bg-[#fffefa] p-5 shadow-sm sm:p-7">
                <p class="text-sm font-semibold uppercase tracking-wide text-[#164f43]">Dossier délégué</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                    {{ isset($company) && $company->exists ? 'Modifier l’entreprise' : 'Ajouter une entreprise' }}
                </h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Les modifications sont enregistrées dans le dossier du Prestataire affiché ci-dessus.</p>
            </header>
        @endif

        @if (isset($delegatedPrestataire))
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @endif
        <form method="POST" action="{{ $formAction ?? (isset($company) && $company->exists ? route('prestataire.companies.update', $company) : route('prestataire.companies.store')) }}" enctype="multipart/form-data">
            @csrf
            @if (isset($company) && $company->exists)
                @method('PUT')
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom de l’entreprise <span class="text-red-600">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $company?->name) }}" maxlength="255" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900" required>
                    @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="activity" class="mb-1.5 block text-sm font-semibold text-slate-700">Activité</label>
                    <input id="activity" name="activity" type="text" value="{{ old('activity', $company?->activity) }}" maxlength="255" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('activity')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="assignment" class="mb-1.5 block text-sm font-semibold text-slate-700">Affectation</label>
                    <input id="assignment" name="assignment" type="text" value="{{ old('assignment', $company?->assignment) }}" maxlength="255" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('assignment')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="contract_start_date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de début du contrat</label>
                    <input id="contract_start_date" name="contract_start_date" type="date" value="{{ old('contract_start_date', $company?->contract_start_date?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('contract_start_date')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="contract_end_date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de fin du contrat</label>
                    <input id="contract_end_date" name="contract_end_date" type="date" value="{{ old('contract_end_date', $company?->contract_end_date?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('contract_end_date')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="additional_info" class="mb-1.5 block text-sm font-semibold text-slate-700">Informations complémentaires</label>
                    <textarea id="additional_info" name="additional_info" rows="5" maxlength="2000" class="form-control w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900">{{ old('additional_info', $company?->additional_info) }}</textarea>
                    @error('additional_info')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <a href="{{ $formCancelUrl ?? route('prestataire.companies.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Annuler</a>
                <button type="submit" @class([
                    'inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                    'bg-[#164f43] hover:bg-[#123e36] focus-visible:outline-[#164f43]' => isset($delegatedPrestataire),
                    'bg-blue-700 hover:bg-blue-800 focus-visible:outline-blue-700' => ! isset($delegatedPrestataire),
                ])>
                    {{ isset($company) && $company->exists ? 'Enregistrer les modifications' : 'Créer l’entreprise' }}
                </button>
            </div>
        </form>
        @if (isset($delegatedPrestataire))
            </div>
        @endif
    </div>
@endsection
