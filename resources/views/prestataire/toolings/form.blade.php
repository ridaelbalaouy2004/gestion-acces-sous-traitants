@extends('layouts.authenticated')

@section('pageTitle', $pageTitle)

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
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
            <a href="{{ $formCancelUrl ?? ($tooling ? route('prestataire.toolings.show', $tooling) : route('prestataire.toolings.index')) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour aux outillages</a>
            <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h2>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ $formAction ?? ($tooling ? route('prestataire.toolings.update', $tooling) : route('prestataire.toolings.store')) }}" class="space-y-6 {{ isset($delegatedPrestataire) ? 'rounded-3xl' : 'rounded-2xl' }} border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @if ($tooling)
                @method('PUT')
            @endif
            <div>
                <label for="company_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Entreprise <span class="text-red-600" aria-hidden="true">*</span></label>
                @if ($tooling)
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

            @include('shared.company-toolings.fields', ['tooling' => $tooling])

            <div class="space-y-3 border-t border-slate-100 pt-5">
                <div>
                    <label for="photo" class="mb-1.5 block text-sm font-semibold text-slate-700">Photo de l’outillage <span class="font-normal text-slate-500">(facultative)</span></label>
                    <x-file-upload
                        id="photo"
                        name="photo"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        :max-bytes="5242880"
                    />
                    <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG ou WEBP · 5 Mo maximum.</p>
                    @error('photo')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @if ($tooling?->photo_path)
                    <div class="flex flex-wrap items-center gap-4">
                        @include('shared.company-toolings.avatar', [
                            'tooling' => $tooling,
                            'photoUrl' => $photoUrl ?? route('prestataire.toolings.photo', $tooling),
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

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ $formCancelUrl ?? ($tooling ? route('prestataire.toolings.show', $tooling) : route('prestataire.toolings.index')) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                <button type="submit" @class([
                    'inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                    'bg-[#164f43] hover:bg-[#123e36] focus-visible:outline-[#164f43]' => isset($delegatedPrestataire),
                    'bg-blue-700 hover:bg-blue-800 focus-visible:outline-blue-700' => ! isset($delegatedPrestataire),
                ])>Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
