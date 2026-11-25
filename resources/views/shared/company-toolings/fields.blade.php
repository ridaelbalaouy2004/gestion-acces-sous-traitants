<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label for="designation" class="mb-1.5 block text-sm font-semibold text-slate-700">Désignation <span class="text-red-600" aria-hidden="true">*</span></label>
        <input id="designation" name="designation" type="text" maxlength="150" required value="{{ old('designation', $tooling?->designation) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
        @error('designation')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="category" class="mb-1.5 block text-sm font-semibold text-slate-700">Catégorie <span class="text-red-600" aria-hidden="true">*</span></label>
        <input id="category" name="category" type="text" maxlength="100" required value="{{ old('category', $tooling?->category) }}" placeholder="Ex. matériel de mesure" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
        @error('category')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    @foreach ([
        'internal_reference' => ['Référence interne', 100],
        'brand' => ['Marque', 100],
        'model' => ['Modèle', 100],
        'serial_number' => ['Numéro de série', 100],
    ] as $field => [$label, $maximum])
        <div>
            <label for="{{ $field }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="{{ $maximum }}" value="{{ old($field, $tooling?->{$field}) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
            @error($field)<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <div>
        <label for="quantity" class="mb-1.5 block text-sm font-semibold text-slate-700">Quantité <span class="text-red-600" aria-hidden="true">*</span></label>
        <input id="quantity" name="quantity" type="number" min="1" max="1000000" required value="{{ old('quantity', $tooling?->quantity ?? 1) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
        @error('quantity')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="physical_condition" class="mb-1.5 block text-sm font-semibold text-slate-700">État physique</label>
        <select id="physical_condition" name="physical_condition" class="form-control min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900">
            <option value="">Non renseigné</option>
            @foreach (['new' => 'Neuf', 'good' => 'Bon', 'average' => 'Moyen', 'damaged' => 'À réparer'] as $value => $label)
                <option value="{{ $value }}" @selected(old('physical_condition', $tooling?->physical_condition) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('physical_condition')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="acquired_at" class="mb-1.5 block text-sm font-semibold text-slate-700">Date d’acquisition</label>
        <input id="acquired_at" name="acquired_at" type="date" value="{{ old('acquired_at', $tooling?->acquired_at?->format('Y-m-d')) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
        @error('acquired_at')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2 lg:col-span-3">
        <label for="location" class="mb-1.5 block text-sm font-semibold text-slate-700">Affectation ou emplacement</label>
        <input id="location" name="location" type="text" maxlength="255" value="{{ old('location', $tooling?->location) }}" class="form-control min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900">
        @error('location')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description</label>
        <textarea id="description" name="description" rows="4" maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('description', $tooling?->description) }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="observations" class="mb-1.5 block text-sm font-semibold text-slate-700">Observations</label>
        <textarea id="observations" name="observations" rows="4" maxlength="5000" class="form-control w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">{{ old('observations', $tooling?->observations) }}</textarea>
        @error('observations')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
