@extends('layouts.authenticated')

@section('pageTitle', 'Modifier un Prestataire')

@section('content')
    <div class="mb-7">
        <a href="{{ route('gestionnaire.prestataires.show', $prestataire) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour au compte</a>
        <p class="mt-5 text-sm font-medium text-slate-500">Informations du compte</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Modifier {{ $prestataire->name }}</h2>
    </div>

    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <form method="POST" action="{{ route('gestionnaire.prestataires.update', $prestataire) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nom complet</label>
                <input id="name" name="name" type="text" value="{{ old('name', $prestataire->name) }}" maxlength="255" autocomplete="name" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('name')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $prestataire->email) }}" maxlength="255" autocomplete="email" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                @error('email')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                Le rôle et le statut du compte ne peuvent pas être modifiés depuis ce formulaire.
            </div>

            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Enregistrer</button>
                <a href="{{ route('gestionnaire.prestataires.show', $prestataire) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </section>
@endsection
