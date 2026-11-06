@extends('layouts.authenticated')

@section('pageTitle', $pageTitle)

@section('content')
    <div class="mb-7">
        <a href="{{ route('prestataire.safety-team') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à mon équipe</a>
        <p class="mt-5 text-sm font-medium text-slate-500">Mon équipe de sécurité</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h2>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <form method="POST" action="{{ $member ? route('prestataire.safety-team.update', $member) : route('prestataire.safety-team.store') }}" class="space-y-6">
            @csrf
            @if ($member)
                @method('PUT')
                <input type="hidden" name="document_id" value="{{ $member->document_id }}">
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="document_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Document associé <span class="text-red-600" aria-hidden="true">*</span></label>
                    @if ($member)
                        <p class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-sm text-slate-700">{{ $member->document->title }}{{ $member->document->reference_number ? ' — '.$member->document->reference_number : '' }}</p>
                    @else
                        <select id="document_id" name="document_id" required class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900">
                            <option value="">Sélectionner un document</option>
                            @foreach ($documents as $document)
                                <option value="{{ $document->id }}" @selected((string) old('document_id') === (string) $document->id)>
                                    {{ $document->title }}{{ $document->reference_number ? ' — '.$document->reference_number : '' }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @error('document_id')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                    @if (! $member && $documents->isEmpty())
                        <p class="mt-1.5 text-sm text-slate-500">Aucun document validé disponible. Veuillez attendre la validation d’un dossier par le Gestionnaire.</p>
                    @endif
                </div>

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom complet <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $member?->name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="cin" class="mb-1.5 block text-sm font-semibold text-slate-700">Numéro CIN <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="cin" name="cin" type="text" required maxlength="255" value="{{ old('cin', $member?->cin) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('cin')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="position" class="mb-1.5 block text-sm font-semibold text-slate-700">Fonction <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="position" name="position" type="text" required maxlength="255" value="{{ old('position', $member?->position) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('position')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Adresse e-mail <span class="font-normal text-slate-500">(facultatif)</span></label>
                    <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $member?->email) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('email')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Téléphone <span class="font-normal text-slate-500">(facultatif)</span></label>
                    <input id="phone" name="phone" type="tel" maxlength="255" value="{{ old('phone', $member?->phone) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
                    @error('phone')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('prestataire.safety-team') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                <button type="submit" @disabled(! $member && $documents->isEmpty()) class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ $member ? 'Enregistrer les modifications' : 'Ajouter le membre' }}
                </button>
            </div>
        </form>
    </section>
@endsection
