<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Réinitialiser le mot de passe | Gestion des accès</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="auth-card w-full max-w-md rounded-3xl border border-white/80 bg-white p-7 sm:p-10" aria-labelledby="new-password-title">
            <header class="mb-8">
                <p class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-700">Gestion des accès</p>
                <h1 id="new-password-title" class="text-3xl font-semibold tracking-tight text-slate-900">Choisir un nouveau mot de passe</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Le nouveau mot de passe doit contenir au moins 12 caractères.</p>
            </header>

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        required
                        class="form-control block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"
                    >
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Nouveau mot de passe</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="form-control block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">Confirmer le mot de passe</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="form-control block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"
                    >
                </div>

                <button type="submit" class="primary-action flex w-full items-center justify-center rounded-xl px-4 py-3 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                    Réinitialiser le mot de passe
                </button>
            </form>
        </section>
    </main>
</body>
</html>
