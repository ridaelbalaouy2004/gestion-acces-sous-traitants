<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mot de passe oublié | Gestion des accès</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="auth-card w-full max-w-md rounded-3xl border border-white/80 bg-white p-7 sm:p-10" aria-labelledby="reset-link-title">
            <header class="mb-8">
                <p class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-700">Gestion des accès</p>
                <h1 id="reset-link-title" class="text-3xl font-semibold tracking-tight text-slate-900">Mot de passe oublié</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Saisissez l’adresse e-mail de votre compte pour recevoir un lien sécurisé de réinitialisation.</p>
            </header>

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Adresse e-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        inputmode="email"
                        required
                        autofocus
                        class="form-control block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400"
                        placeholder="nom@entreprise.fr"
                    >
                </div>

                <button type="submit" class="primary-action flex w-full items-center justify-center rounded-xl px-4 py-3 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
                    Envoyer le lien
                </button>
            </form>

            <p class="mt-8 border-t border-slate-100 pt-5 text-center text-sm">
                <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-900">Retour à la connexion</a>
            </p>
        </section>
    </main>
</body>
</html>
