@extends('layouts.authenticated')

@section('pageTitle', 'Guide des documents requis')

@section('content')
    <div class="mb-7">
        <p class="text-sm font-medium text-slate-500">Document partagé</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Guide des documents requis</h2>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Téléversez une seule version du Guide Word. Le même fichier sera proposé à tous les Prestataires.</p>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="shared-guide-status-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h3 id="shared-guide-status-title" class="text-base font-semibold text-slate-900">Guide partagé</h3>
                @if ($guide === null)
                    <p class="mt-2 text-sm text-slate-600">Aucun guide n’a encore été téléversé.</p>
                @elseif ($guideAvailable)
                    <span class="mt-2 inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Disponible</span>
                    <p class="mt-2 break-all text-sm font-medium text-slate-800">{{ $guide->file_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">Dernière mise à jour : {{ $guide->updated_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                @else
                    <p class="mt-2 text-sm font-medium text-red-700" role="alert">Le Guide enregistré est indisponible dans le stockage privé. Téléversez une nouvelle version.</p>
                @endif
            </div>
            @if ($guideAvailable || ($guide !== null && $canDeleteGuide))
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @if ($guideAvailable)
                        <a href="{{ route('gestionnaire.guides.required-documents.download') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-50">
                            Télécharger
                        </a>
                    @endif
                    @if ($guide !== null && $canDeleteGuide)
                        <form method="POST" action="{{ route('gestionnaire.guides.required-documents.destroy') }}" data-confirm="Voulez-vous vraiment supprimer le Guide des documents requis ? Cette action est irréversible.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Supprimer le Guide
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        @if ($canUploadGuide)
            <form method="POST" action="{{ route('gestionnaire.guides.required-documents.store') }}" enctype="multipart/form-data" class="mt-6 border-t border-slate-100 pt-5">
                @csrf
                <label for="required-documents-guide-file" class="mb-1.5 block text-sm font-medium text-slate-700">
                    {{ $guide === null ? 'Téléverser le Guide Word' : 'Remplacer le Guide Word' }}
                </label>
                <div class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <x-file-upload
                        id="required-documents-guide-file"
                        name="guide"
                        accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        :max-bytes="10485760"
                        required
                    />
                    <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                        {{ $guide === null ? 'Téléverser le Guide Word' : 'Remplacer le Guide' }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-slate-500">Fichier .docx uniquement, taille maximale : 10 Mo. L’ancien Guide est conservé si le remplacement échoue.</p>
                @error('guide')
                    <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </form>
        @else
            <p class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-600">Seul le Gestionnaire principal peut téléverser ou remplacer le Guide partagé.</p>
        @endif
    </section>

    @if ($guideAvailable)
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="send-shared-guide-title">
            <h3 id="send-shared-guide-title" class="text-base font-semibold text-slate-900">Envoyer le Guide par e-mail</h3>
            <p class="mt-1 text-sm text-slate-600">Sélectionnez un Prestataire auquel vous avez accès. Son adresse e-mail est récupérée depuis son compte.</p>

            @if ($prestataires->isEmpty())
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Aucun Prestataire accessible n’est disponible pour l’envoi.</p>
            @else
                <form method="POST" action="{{ route('gestionnaire.guides.required-documents.send') }}" class="mt-5 grid gap-4" data-guide-email-form data-confirmation-dialog="shared-guide-send-confirmation">
                    @csrf
                    <input type="hidden" name="send_token" value="{{ $sendToken }}">
                    <input type="hidden" name="confirmed" value="0" data-guide-email-confirmed>
                    <div>
                        <label for="guide-prestataire-id" class="mb-1.5 block text-sm font-medium text-slate-700">Prestataire destinataire</label>
                        <select id="guide-prestataire-id" name="prestataire_id" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                            <option value="">Sélectionnez un Prestataire</option>
                            @foreach ($prestataires as $prestataire)
                                <option value="{{ $prestataire->id }}" @selected((string) old('prestataire_id') === (string) $prestataire->id)>{{ $prestataire->name }} — {{ $prestataire->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="guide-email-subject" class="mb-1.5 block text-sm font-medium text-slate-700">Objet</label>
                        <input id="guide-email-subject" name="subject" type="text" maxlength="255" required value="{{ old('subject', $defaultSubject) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
                    </div>
                    <div>
                        <label for="guide-email-message" class="mb-1.5 block text-sm font-medium text-slate-700">Message</label>
                        <textarea id="guide-email-message" name="message" rows="7" maxlength="10000" required class="form-control w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('message', $defaultMessage) }}</textarea>
                    </div>
                    <p class="text-sm text-slate-600">Pièce jointe : <span class="font-semibold text-slate-800">{{ $guide->file_name }}</span></p>
                    <div>
                        <button type="submit" data-guide-email-submit class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                            Envoyer par e-mail
                        </button>
                    </div>
                </form>
                @include('shared.guide-email-confirmation', ['dialogId' => 'shared-guide-send-confirmation'])
            @endif
        </section>
    @endif
@endsection
