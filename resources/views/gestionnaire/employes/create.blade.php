@extends('layouts.authenticated')

@section('pageTitle', 'Créer un Gestionnaire')

@section('content')
    <div class="mb-7">
        <a href="{{ route('gestionnaire.employes.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Retour aux Gestionnaires</a>
        <p class="mt-5 text-sm font-medium text-slate-500">Nouveau compte</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Créer un Gestionnaire</h2>
        <p class="mt-2 text-sm text-slate-600">Le Gestionnaire devra modifier son mot de passe temporaire à sa première connexion.</p>
    </div>

    <form method="POST" action="{{ route('gestionnaire.employes.store') }}" class="space-y-6">
        @csrf
        <section class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="text-lg font-semibold text-slate-900">Informations du compte</h3>
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nom complet</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" autocomplete="name" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('email')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-sm text-blue-950">
                Choisissez un mot de passe temporaire à transmettre au Gestionnaire par un canal sécurisé. Il ne sera ni affiché après l’enregistrement ni conservé en clair.
            </div>
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe temporaire</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('password')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirmer le mot de passe temporaire</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="text-lg font-semibold text-slate-900">Permissions initiales</h3>
            <p class="mt-1 text-sm text-slate-600">Aucune permission n’est attribuée automatiquement.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                @foreach ($permissionsByModule as $module => $permissions)
                    <fieldset class="rounded-xl border border-slate-200 p-4">
                        <legend class="px-1 text-sm font-semibold text-slate-800">{{ $moduleLabels[$module] ?? $module }}</legend>
                        <div class="mt-2 space-y-3">
                            @foreach ($permissions as $permission)
                                <label class="flex items-start gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, (array) old('permission_ids', []))) class="mt-0.5 rounded border-slate-300 text-blue-700">
                                    <span>{{ $permission->label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            @error('permission_ids')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h3 class="text-lg font-semibold text-slate-900">Affectations initiales</h3>
            <p class="mt-1 text-sm text-slate-600">Les employés ne voient que les Prestataires qui leur sont affectés.</p>
            @if ($prestataires->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Aucun Prestataire à affecter.</p>
            @else
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($prestataires as $prestataire)
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
                            <input type="checkbox" name="prestataire_ids[]" value="{{ $prestataire->id }}" @checked(in_array($prestataire->id, (array) old('prestataire_ids', []))) class="mt-0.5 rounded border-slate-300 text-blue-700">
                            <span><span class="block font-semibold text-slate-800">{{ $prestataire->name }}</span><span class="text-slate-500">{{ $prestataire->email }}</span></span>
                        </label>
                    @endforeach
                </div>
            @endif
            @error('prestataire_ids')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Créer le compte</button>
            <a href="{{ route('gestionnaire.employes.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
        </div>
    </form>
@endsection
