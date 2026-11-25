@extends('layouts.authenticated')

@section('pageTitle', 'Modifier un Gestionnaire')

@section('content')
    <div class="mb-7">
        <a href="{{ route('gestionnaire.employes.show', $manager) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Retour à la fiche</a>
        <p class="mt-5 text-sm font-medium text-slate-500">Compte Gestionnaire</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Modifier {{ $manager->name }}</h2>
    </div>
    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="{{ route('gestionnaire.employes.update', $manager) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nom complet</label>
                <input id="name" name="name" type="text" value="{{ old('name', $manager->name) }}" maxlength="255" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $manager->email) }}" maxlength="255" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('email')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Enregistrer</button>
        </form>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h3 class="text-lg font-semibold text-slate-900">Permissions Entreprises et Mon équipe</h3>
        <p class="mt-1 text-sm text-slate-600">Seules les permissions de ces deux modules sont modifiées ici. Les autres permissions restent inchangées.</p>
        <form method="POST" action="{{ route('gestionnaire.employes.permissions.update', $manager) }}" class="mt-5">
            @csrf
            @method('PUT')
            @foreach (['entreprises', 'employees'] as $module)
                <input type="hidden" name="permission_modules[]" value="{{ $module }}">
            @endforeach
            <div class="grid gap-5 md:grid-cols-2">
                @foreach ($permissionsByModule->toBase()->only(['entreprises', 'employees']) as $module => $permissions)
                    <fieldset class="rounded-xl border border-slate-200 p-4">
                        <legend class="px-1 text-sm font-semibold text-slate-800">{{ $moduleLabels[$module] ?? $module }}</legend>
                        <div class="mt-2 space-y-3">
                            @foreach ($permissions as $permission)
                                <label class="flex items-start gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($manager->permissions->contains('id', $permission->id)) class="mt-0.5 rounded border-slate-300 text-blue-700">
                                    <span>{{ $permission->label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            @error('permission_ids')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
            <button type="submit" class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">Enregistrer ces permissions</button>
        </form>
    </section>
@endsection
