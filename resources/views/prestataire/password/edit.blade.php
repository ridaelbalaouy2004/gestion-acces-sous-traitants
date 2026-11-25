<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Changer le mot de passe | Gestion des Accès Sous-traitants</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
    <main class="auth-card w-full max-w-lg rounded-3xl border border-white/80 bg-white p-7 sm:p-10">
        <header class="mb-8">
            <div class="brand-mark mb-6 flex h-12 w-12 items-center justify-center rounded-2xl text-white" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6">
                    <path d="M12 3 5 6v5c0 4.7 2.9 8.2 7 10 4.1-1.8 7-5.3 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-700">Première connexion</p>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Changez votre mot de passe</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Pour sécuriser votre compte, choisissez un mot de passe personnel avant de continuer.</p>
        </header>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route($passwordRoutePrefix.'.password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required autofocus class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                <p class="mt-1.5 text-xs text-slate-500">12 caractères minimum. Le mot de passe temporaire ne peut pas être réutilisé.</p>
                @error('password')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirmer le nouveau mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>

            <button type="submit" class="primary-action flex min-h-11 w-full items-center justify-center rounded-xl px-4 py-3 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                Enregistrer le nouveau mot de passe
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
            @csrf
            <button type="submit" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Se déconnecter</button>
        </form>
    </main>
</body>
</html>
