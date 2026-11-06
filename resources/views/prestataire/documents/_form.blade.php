@php
    $existingFields = collect($document?->custom_fields ?? [])
        ->map(fn ($value, $name) => [
            'name' => (string) $name,
            'value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        ])
        ->values()
        ->all();
    $customFields = old('custom_fields', $existingFields);
    $customFields = is_array($customFields) ? $customFields : [];
@endphp

<form
    method="POST"
    action="{{ $document ? route('prestataire.documents.update', $document) : route('prestataire.documents.store') }}"
    enctype="multipart/form-data"
    class="space-y-6"
    data-confirm="{{ $document ? 'Confirmer la transmission de vos corrections ?' : 'Confirmer la transmission de ce document ?' }}"
>
    @csrf
    @if ($document)
        @method('PUT')
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="title" class="mb-1.5 block text-sm font-semibold text-slate-700">Titre du document <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="title" name="title" type="text" required maxlength="255" value="{{ old('title', $document?->title) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900" aria-describedby="title-error">
            @error('title')<p id="title-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label for="company_name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nom de l’entreprise <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="company_name" name="company_name" type="text" required maxlength="255" value="{{ old('company_name', $document?->company_name) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
            @error('company_name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="start_date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de début <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="start_date" name="start_date" type="date" required value="{{ old('start_date', $document?->start_date?->toDateString()) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
            @error('start_date')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="end_date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date de fin <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="end_date" name="end_date" type="date" required value="{{ old('end_date', $document?->end_date?->toDateString()) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm text-slate-900">
            @error('end_date')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description <span class="text-red-600" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" rows="4" required maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900">{{ old('description', $document?->description) }}</textarea>
            @error('description')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label for="attachment" class="mb-1.5 block text-sm font-semibold text-slate-700">Fichier joint <span class="font-normal text-slate-500">(facultatif)</span></label>
            @if ($document?->attachment_path)
                <p class="mb-2 text-sm text-slate-600">Un fichier est déjà joint à ce document. Sélectionnez-en un nouveau pour le remplacer.</p>
            @endif
            <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block min-h-11 w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:min-h-11 file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
            <p class="mt-1.5 text-xs text-slate-500">Formats acceptés : PDF, JPG ou PNG. Taille maximale : 10 Mo.</p>
            @error('attachment')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>

    <section class="border-t border-slate-100 pt-5" aria-labelledby="custom-fields-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 id="custom-fields-title" class="text-sm font-semibold text-slate-800">Informations complémentaires</h3>
                <p class="mt-1 text-sm text-slate-500">Ajoutez jusqu’à 10 champs personnalisés si nécessaire.</p>
            </div>
            <button type="button" data-add-custom-field class="min-h-10 rounded-xl border border-slate-300 px-3.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Ajouter un champ
            </button>
        </div>
        @error('custom_fields')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        <div data-custom-fields class="mt-4 space-y-3">
            @foreach ($customFields as $index => $field)
                @if (is_array($field))
                    <div class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]" data-custom-field-row>
                        <div>
                            <label for="custom-field-name-{{ $index }}" class="mb-1 block text-xs font-semibold text-slate-600">Nom du champ</label>
                            <input id="custom-field-name-{{ $index }}" name="custom_fields[{{ $index }}][name]" value="{{ $field['name'] ?? '' }}" maxlength="100" class="form-control min-h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        </div>
                        <div>
                            <label for="custom-field-value-{{ $index }}" class="mb-1 block text-xs font-semibold text-slate-600">Valeur</label>
                            <input id="custom-field-value-{{ $index }}" name="custom_fields[{{ $index }}][value]" value="{{ $field['value'] ?? '' }}" maxlength="500" class="form-control min-h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        </div>
                        <button type="button" data-remove-custom-field class="min-h-10 self-end rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50">Supprimer</button>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ $document ? route('prestataire.documents.show', $document) : route('prestataire.documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Annuler
        </a>
        <button type="submit" class="primary-action inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm">
            {{ $document ? 'Transmettre les corrections' : 'Transmettre le document' }}
        </button>
    </div>
</form>
