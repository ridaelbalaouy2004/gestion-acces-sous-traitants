@extends('layouts.authenticated')

@section('pageTitle', 'Mon profil')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-blue-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6">
                        <circle cx="12" cy="8" r="3.75" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M4.5 19a7.5 7.5 0 0 1 15 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-medium text-slate-500">Informations personnelles</p>
                    <h2 class="text-xl font-semibold text-slate-900">Modifier mon profil</h2>
                </div>
            </div>

            <form method="POST" action="{{ route($routePrefix.'.profile.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nom complet</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @error('name')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @error('email')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </section>

        <aside class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                <p class="text-sm font-medium text-slate-500">Informations du compte</p>
                <dl class="mt-4 space-y-4 text-sm text-slate-700">
                    <div>
                        <dt class="text-slate-500">Rôle</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $roleLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Date de création</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $user->created_at?->translatedFormat('d F Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Statut du compte</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                {{ $user->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                            <rect x="5" y="10" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M8 10V7.5A4 4 0 0 1 12 3.5a4 4 0 0 1 4 4V10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Sécurité</p>
                        <h3 class="text-lg font-semibold text-slate-900">Changer le mot de passe</h3>
                    </div>
                </div>

                <form method="POST" action="{{ route($routePrefix.'.profile.password') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe actuel</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @error('current_password')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Nouveau mot de passe</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirmer le nouveau mot de passe</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>

                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700">
                        Modifier le mot de passe
                    </button>
                </form>
            </section>
        </aside>
    </div>
@endsection
