<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion | Gestion des accès</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="auth-card w-full max-w-md rounded-3xl border border-white/80 bg-white p-7 sm:p-10" aria-labelledby="login-title">
            <header class="mb-8">
                <div class="brand-mark mb-6 flex h-12 w-12 items-center justify-center rounded-2xl text-white" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6">
                        <path d="M12 3 5 6v5c0 4.7 2.9 8.2 7 10 4.1-1.8 7-5.3 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-700">Gestion des accès</p>
                <h1 id="login-title" class="text-3xl font-semibold tracking-tight text-slate-900">Bienvenue</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Connectez-vous pour accéder à votre espace de travail.</p>
            </header>

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <p class="font-medium">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                        class="form-control block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400"
                        placeholder="nom@entreprise.fr"
                        aria-describedby="email-error"
                    >
                    @error('email')
                        <p id="email-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                    </div>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="form-control block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-24 text-slate-900 placeholder:text-slate-400"
                            placeholder="Votre mot de passe"
                            aria-describedby="password-error"
                        >
                        <button
                            type="button"
                            data-toggle-password
                            aria-label="Afficher le mot de passe"
                            aria-pressed="false"
                            class="absolute inset-y-0 right-3 my-auto h-9 rounded-lg px-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"
                        >Afficher</button>
                    </div>
                    @error('password')
                        <p id="password-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember'))
                        class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                    >
                    <span>Se souvenir de moi</span>
                </label>

                <button type="submit" class="primary-action flex w-full items-center justify-center rounded-xl px-4 py-3 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                    Se connecter
                </button>
            </form>

            <p class="mt-8 border-t border-slate-100 pt-5 text-center text-xs leading-5 text-slate-500">
                L’accès est réservé aux comptes créés par votre gestionnaire.
            </p>
        </section>
    </main>
</body>
</html>
