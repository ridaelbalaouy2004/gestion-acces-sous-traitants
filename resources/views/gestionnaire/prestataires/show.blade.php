@extends('layouts.authenticated')

@section('pageTitle', 'Détails du Prestataire')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('gestionnaire.prestataires.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour aux Prestataires</a>
            <p class="mt-5 text-sm font-medium text-slate-500">Compte Prestataire</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $prestataire->name }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $prestataire->email }}</p>
        </div>
        @if (auth()->user()->hasPermission('prestataires.update'))
            <a href="{{ route('gestionnaire.prestataires.edit', $prestataire) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier le compte</a>
        @endif
    </div>

    @if ($temporaryPassword !== null)
        <section class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6" aria-labelledby="temporary-password-title">
            <h3 id="temporary-password-title" class="text-base font-semibold text-amber-950">Mot de passe temporaire — à transmettre au Prestataire</h3>
            <p class="mt-1 text-sm text-amber-900">Il ne sera plus affiché après cette consultation. Le Prestataire devra le remplacer à sa première connexion.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <input id="temporary-password" type="text" value="{{ $temporaryPassword }}" readonly aria-label="Mot de passe temporaire" class="form-control min-h-11 min-w-64 flex-1 rounded-xl border border-amber-300 bg-white px-3 font-mono text-sm text-slate-900">
                <button type="button" data-copy-password="temporary-password" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-950 hover:bg-amber-100">Copier</button>
            </div>
            <p class="mt-2 text-sm text-amber-900" data-copy-feedback aria-live="polite"></p>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="account-details-title">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h3 id="account-details-title" class="text-base font-semibold text-slate-900">Informations du compte</h3>
        </div>
        <dl class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 sm:p-6">
            <div>
                <dt class="text-sm font-medium text-slate-500">Nom complet</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $prestataire->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Adresse e-mail</dt>
                <dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $prestataire->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Création du compte</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $prestataire->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">Statut du compte</dt>
                <dd class="mt-1 text-sm font-semibold {{ $prestataire->is_active ? 'text-emerald-800' : 'text-slate-700' }}">{{ $prestataire->is_active ? 'Actif' : 'Désactivé' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-slate-500">État du mot de passe</dt>
                <dd class="mt-1 text-sm text-slate-800">{{ $prestataire->must_change_password ? 'Temporaire — changement obligatoire à la première connexion' : 'Modifié' }}</dd>
            </div>
        </dl>
        @if (auth()->user()->hasPermission('prestataires.toggle_status'))
        <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
            <form method="POST" action="{{ route('gestionnaire.prestataires.toggle-status', $prestataire) }}" data-confirm="Confirmer le changement de statut de ce compte ?">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold {{ $prestataire->is_active ? 'border border-red-200 text-red-700 hover:bg-red-50' : 'bg-emerald-700 text-white hover:bg-emerald-800' }}">
                    {{ $prestataire->is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}
                </button>
            </form>
        </div>
        @endif
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="required-documents-guide-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 id="required-documents-guide-title" class="text-base font-semibold text-slate-900">Guide des documents requis</h3>
                @if ($guide === null)
                    <p class="mt-1 text-sm text-slate-600">Aucun guide n’a encore été téléversé.</p>
                @elseif ($guideAvailable)
                    <p class="mt-1 text-sm text-slate-600">Guide partagé mis à jour le {{ $guide->updated_at?->format('d/m/Y à H:i') ?? '—' }}.</p>
                    <p class="mt-1 break-all text-sm font-medium text-slate-800">Fichier joint : {{ $guide->file_name }}</p>
                @else
                    <p class="mt-1 text-sm font-medium text-red-700" role="alert">Le Guide partagé est actuellement indisponible.</p>
                @endif
            </div>
            @if ($guideAvailable)
                <a href="{{ route('gestionnaire.prestataires.guide.download', $prestataire) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-50">
                    Télécharger le Guide
                </a>
            @endif
        </div>

        @if ($guideAvailable)
            <form method="POST" action="{{ route('gestionnaire.prestataires.guide.send', $prestataire) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5" data-guide-email-form data-confirmation-dialog="prestataire-guide-send-confirmation">
                @csrf
                <input type="hidden" name="send_token" value="{{ $guideSendToken }}">
                <input type="hidden" name="confirmed" value="0" data-guide-email-confirmed>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Destinataire</label>
                    <p class="break-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">{{ $prestataire->email }}</p>
                    <p class="mt-1 text-xs text-slate-500">Adresse récupérée automatiquement depuis le compte Prestataire.</p>
                </div>
                <div>
                    <label for="prestataire-guide-email-subject" class="mb-1.5 block text-sm font-medium text-slate-700">Objet</label>
                    <input id="prestataire-guide-email-subject" name="subject" type="text" maxlength="255" required value="{{ old('subject', $defaultSubject) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                </div>
                <div>
                    <label for="prestataire-guide-email-message" class="mb-1.5 block text-sm font-medium text-slate-700">Message</label>
                    <textarea id="prestataire-guide-email-message" name="message" rows="7" maxlength="10000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('message', $defaultMessage) }}</textarea>
                </div>
                <p class="text-sm text-slate-600">Pièce jointe : <span class="font-semibold text-slate-800">{{ $guide->file_name }}</span></p>
                <div>
                    <button type="submit" data-guide-email-submit class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                        Envoyer par e-mail
                    </button>
                </div>
            </form>
            @include('shared.guide-email-confirmation', ['dialogId' => 'prestataire-guide-send-confirmation'])
        @endif
        @error('guide')
            <p class="mt-3 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
        @enderror
    </section>
@endsection
