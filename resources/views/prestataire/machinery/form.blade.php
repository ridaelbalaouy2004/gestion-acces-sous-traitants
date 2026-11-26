@extends('layouts.authenticated')

@section('pageTitle', $pageTitle)

@section('content')
    <div class="mx-auto {{ isset($delegatedPrestataire) ? 'max-w-5xl' : 'max-w-4xl' }} space-y-6">
        @if (isset($delegatedPrestataire))
            @include('gestionnaire.delegated._context', [
                'prestataire' => $delegatedPrestataire,
                'company' => $contextCompany ?? null,
                'currentLabel' => $pageTitle,
                'backUrl' => $formCancelUrl,
                'backLabel' => 'Retour au dossier',
            ])
        @else
            <div>
            <a href="{{ $formCancelUrl ?? ($machinery ? route('prestataire.machinery.show', $machinery) : route('prestataire.machinery.index')) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour</a>
            <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h2>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ $formAction ?? ($machinery ? route('prestataire.machinery.update', $machinery) : route('prestataire.machinery.store')) }}" class="space-y-6 {{ isset($delegatedPrestataire) ? 'rounded-3xl' : 'rounded-2xl' }} border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @if ($machinery)
                @method('PUT')
            @endif
            <div>
                <label for="company_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Entreprise <span class="text-red-600" aria-hidden="true">*</span></label>
                @if ($machinery)
                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">{{ $companies->first()->name }}</p>
                @else
                    <select id="company_id" name="company_id" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
                        <option value="">Sélectionner une entreprise</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) old('company_id', $contextCompany->id ?? null) === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('company_id')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="designation" class="mb-1.5 block text-sm font-semibold text-slate-700">Désignation <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="designation" name="designation" type="text" maxlength="150" required value="{{ old('designation', $machinery?->designation) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('designation')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="category" class="mb-1.5 block text-sm font-semibold text-slate-700">Catégorie <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="category" name="category" type="text" maxlength="100" required value="{{ old('category', $machinery?->category) }}" placeholder="Ex. engin de levage" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('category')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="assignment" class="mb-1.5 block text-sm font-semibold text-slate-700">Affectation</label>
                    <input id="assignment" name="assignment" type="text" maxlength="255" value="{{ old('assignment', $machinery?->assignment) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                </div>
                <div>
                    <label for="brand" class="mb-1.5 block text-sm font-semibold text-slate-700">Marque</label>
                    <input id="brand" name="brand" type="text" maxlength="100" value="{{ old('brand', $machinery?->brand) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                </div>
                <div>
                    <label for="model" class="mb-1.5 block text-sm font-semibold text-slate-700">Modèle</label>
                    <input id="model" name="model" type="text" maxlength="100" value="{{ old('model', $machinery?->model) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                </div>
                <div>
                    <label for="serial_number" class="mb-1.5 block text-sm font-semibold text-slate-700">Numéro de série</label>
                    <input id="serial_number" name="serial_number" type="text" maxlength="100" value="{{ old('serial_number', $machinery?->serial_number) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                </div>
                <div>
                    <label for="registration_number" class="mb-1.5 block text-sm font-semibold text-slate-700">Immatriculation</label>
                    <input id="registration_number" name="registration_number" type="text" maxlength="100" value="{{ old('registration_number', $machinery?->registration_number) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                </div>
                <div>
                    <label for="commissioned_at" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de mise en service</label>
                    <input id="commissioned_at" name="commissioned_at" type="date" value="{{ old('commissioned_at', $machinery?->commissioned_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
                    @error('commissioned_at')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="space-y-3 border-t border-slate-100 pt-5">
                <div>
                    <label for="photo" class="mb-1.5 block text-sm font-semibold text-slate-700">Photo de l’engin <span class="font-normal text-slate-500">(facultative)</span></label>
                    <x-file-upload
                        id="photo"
                        name="photo"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        :max-bytes="5242880"
                    />
                    <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG ou WEBP · 5 Mo maximum.</p>
                    @error('photo')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @if ($machinery?->photo_path)
                    <div class="flex flex-wrap items-center gap-4">
                        @include('shared.company-machinery.avatar', [
                            'machinery' => $machinery,
                            'photoUrl' => $photoUrl ?? route('prestataire.machinery.photo', $machinery),
                            'sizeClass' => 'size-20',
                            'fallbackOnError' => true,
                        ])
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                            Supprimer la photo actuelle
                        </label>
                    </div>
                @endif
            </div>
            <div>
                <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('description', $machinery?->description) }}</textarea>
                @error('description')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ $formCancelUrl ?? ($machinery ? route('prestataire.machinery.show', $machinery) : route('prestataire.machinery.index')) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                <button type="submit" @class([
                    'inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                    'bg-[#164f43] hover:bg-[#123e36] focus-visible:outline-[#164f43]' => isset($delegatedPrestataire),
                    'bg-blue-700 hover:bg-blue-800 focus-visible:outline-blue-700' => ! isset($delegatedPrestataire),
                ])>Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
