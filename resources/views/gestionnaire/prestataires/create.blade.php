@extends('layouts.authenticated')

@section('pageTitle', 'Créer un Prestataire')

@section('content')
    <div class="mb-7">
        <a href="{{ route('gestionnaire.prestataires.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour aux Prestataires</a>
        <p class="mt-5 text-sm font-medium text-slate-500">Nouveau compte</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Créer un Prestataire</h2>
        <p class="mt-2 text-sm text-slate-600">Le Prestataire devra choisir un nouveau mot de passe lors de sa première connexion.</p>
    </div>

    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="{{ route('gestionnaire.prestataires.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nom complet</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" autocomplete="name" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('name')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('email')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                <p class="text-sm font-semibold text-blue-950">Mot de passe temporaire</p>
                <p class="mt-1 text-sm text-blue-900">Laissez ces champs vides pour en générer un automatiquement. Il sera affiché une seule fois après la création.</p>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe temporaire (facultatif)</label>
                <input id="password" name="password" type="password" autocomplete="new-password" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('password')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirmer le mot de passe temporaire</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>

            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Créer le compte</button>
                <a href="{{ route('gestionnaire.prestataires.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </section>
@endsection
