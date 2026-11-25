@extends('layouts.authenticated')

@section('pageTitle', $pageTitle)

@section('content')
    <div class="mb-7">
        <a href="{{ $formCancelUrl ?? ($employee ? route('prestataire.employees.show', $employee) : route('prestataire.employees.index')) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à mon équipe</a>
        <p class="mt-5 text-sm font-medium text-slate-500">Employés permanents</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h2>
        @if (isset($delegatedPrestataire))
            <p class="mt-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                Vous effectuez ces opérations pour le compte de : <span class="font-semibold">{{ $delegatedPrestataire->name }}</span>
            </p>
        @endif
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <form method="POST" enctype="multipart/form-data" action="{{ $formAction ?? ($employee ? route('prestataire.employees.update', $employee) : route('prestataire.employees.store')) }}" class="space-y-6">
            @csrf
            @if ($employee)
                @method('PUT')
            @else
                <div>
                    <label for="company_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Entreprise <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="company_id" name="company_id" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900">
                        <option value="">Sélectionner une entreprise</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('company_id')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="mb-1.5 block text-sm font-semibold text-slate-700">Prénom</label>
                    <input id="first_name" name="first_name" type="text" maxlength="100" value="{{ old('first_name', $employee?->first_name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('first_name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom</label>
                    <input id="last_name" name="last_name" type="text" maxlength="100" value="{{ old('last_name', $employee?->last_name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('last_name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="cin" class="mb-1.5 block text-sm font-semibold text-slate-700">CIN</label>
                    <input id="cin" name="cin" type="text" maxlength="50" value="{{ old('cin', $employee?->cin) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('cin')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="position" class="mb-1.5 block text-sm font-semibold text-slate-700">Fonction</label>
                    <input id="position" name="position" type="text" maxlength="255" value="{{ old('position', $employee?->position) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('position')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="locker_number" class="mb-1.5 block text-sm font-semibold text-slate-700">Numéro de cadenas <span class="font-normal text-slate-500">(facultatif)</span></label>
                    <input id="locker_number" name="locker_number" type="text" maxlength="50" value="{{ old('locker_number', $employee?->locker_number) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('locker_number')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="space-y-3 border-t border-slate-100 pt-5">
                <div>
                    <label for="profile_photo" class="mb-1.5 block text-sm font-semibold text-slate-700">Photo de profil <span class="font-normal text-slate-500">(facultative)</span></label>
                    <x-file-upload
                        id="profile_photo"
                        name="profile_photo"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        :max-bytes="5242880"
                    />
                    <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG ou WEBP · 5 Mo maximum.</p>
                    @error('profile_photo')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @if ($employee?->profile_photo_path)
                    @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: 'cet employé')
                    <div class="flex flex-wrap items-center gap-4">
                        @include('shared.company-employees.avatar', [
                            'employee' => $employee,
                            'employeeName' => $employeeName,
                            'photoUrl' => $photoUrl ?? route('prestataire.employees.photo', $employee),
                            'sizeClass' => 'size-20',
                        ])
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="remove_profile_photo" value="1" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                            Supprimer la photo actuelle
                        </label>
                    </div>
                @endif
            </div>

            <p class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">Les informations peuvent être complétées ensuite. Le prénom, le nom, la CIN, la fonction et les documents CIN, CNSS et aptitude physique seront exigés avant la soumission.</p>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ $formCancelUrl ?? ($employee ? route('prestataire.employees.show', $employee) : route('prestataire.employees.index')) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">{{ $employee ? 'Enregistrer les modifications' : 'Créer la fiche' }}</button>
            </div>
        </form>
    </section>
@endsection
