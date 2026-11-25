<?php

namespace App\Http\Controllers\Prestataire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyMachineryDocumentVersion;
use App\Models\User;
use App\Notifications\CompanyMachineryWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CompanyMachineryController extends Controller
{
    public function index(Request $request): View
    {
        $prestataire = $this->currentPrestataire($request);
        $filters = $request->validate([
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->where('prestataire_id', $prestataire->getKey()),
            ],
            'status' => ['nullable', 'string', Rule::in($this->statuses())],
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $machineryQuery = CompanyMachinery::query()
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()));
        $machineryCount = (clone $machineryQuery)
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->count();

        $machineryQuery
            ->with('company:id,name')
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['category']) && $filters['category'] !== '', fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['search']) && $filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery->where('designation', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('serial_number', 'like', $term)
                        ->orWhere('registration_number', 'like', $term);
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CompanyMachinery::STATUS_PENDING])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        return $this->page('prestataire.machinery.index', [
            'machineryItems' => $machineryQuery->paginate(15)->withQueryString(),
            'machineryCount' => $machineryCount,
            'companies' => $prestataire->companies()->orderBy('name')->get(['id', 'name']),
            'categories' => (clone $machineryQuery)
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $companies = $this->currentPrestataire($request)->companies()->orderBy('name')->get(['id', 'name']);
        abort_if($companies->isEmpty(), 403, 'Créez d’abord une entreprise avant d’ajouter un engin.');

        return $this->page('prestataire.machinery.form', [
            'machinery' => null,
            'companies' => $companies,
            'pageTitle' => 'Ajouter un engin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
        $validated = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where('prestataire_id', $prestataire->getKey()),
            ],
            ...$this->machineryInputRules(),
        ], $this->machineryInputMessages());
        $photo = $validated['photo'] ?? null;
        unset($validated['photo']);
        $storedPhotoPath = null;

        try {
            $machinery = DB::transaction(function () use (
                $prestataire,
                $validated,
                $photo,
                &$storedPhotoPath,
            ): CompanyMachinery {
                $company = $prestataire->companies()->findOrFail($validated['company_id']);
                $machinery = $company->machinery()->create([
                    ...$this->machineryAttributes($validated),
                    'status' => CompanyMachinery::STATUS_DRAFT,
                ]);

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, $machinery);
                    $machinery->update(['photo_path' => $storedPhotoPath]);
                }

                foreach (CompanyMachineryDocument::allTypes() as $type) {
                    $machinery->documents()->create([
                        'type' => $type,
                        'label' => CompanyMachineryDocument::labelForType($type),
                        'status' => CompanyMachineryDocument::STATUS_DRAFT,
                    ]);
                }

                $machinery->history()->create([
                    'user_id' => $prestataire->getKey(),
                    'action' => 'created',
                    'to_status' => CompanyMachinery::STATUS_DRAFT,
                    'reason' => 'Création de la fiche engin.',
                ]);

                return $machinery;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhotoAfterFailure($storedPhotoPath, $exception);

            throw $exception;
        }

        return to_route('prestataire.machinery.show', $machinery)
            ->with('status', 'La fiche engin a été créée.');
    }

    public function show(Request $request, CompanyMachinery $machinery): View
    {
        $machinery = $this->ownedMachinery($request, $machinery);
        $machinery->load([
            'company:id,name,prestataire_id',
            'documents.versions.uploadedBy:id,name',
            'documents.validatedBy:id,name',
            'history.user:id,name',
            'history.document:id,type,label',
            'validatedBy:id,name',
        ]);

        return $this->page('prestataire.machinery.show', [
            'machinery' => $machinery,
            'missingRequiredDocuments' => $this->missingRequiredDocuments($machinery),
        ]);
    }

    public function photo(Request $request, CompanyMachinery $machinery): StreamedResponse
    {
        $machinery = $this->ownedMachinery($request, $machinery);

        return $this->streamPrivateImageInline($machinery->photo_path);
    }

    public function edit(Request $request, CompanyMachinery $machinery): View
    {
        $machinery = $this->ownedMachinery($request, $machinery);
        abort_unless(in_array($machinery->status, [CompanyMachinery::STATUS_DRAFT, CompanyMachinery::STATUS_REJECTED], true), 403);

        return $this->page('prestataire.machinery.form', [
            'machinery' => $machinery,
            'companies' => collect([$machinery->company()->firstOrFail(['id', 'name'])]),
            'pageTitle' => 'Modifier un engin',
        ]);
    }

    public function update(Request $request, CompanyMachinery $machinery): RedirectResponse
    {
        $machinery = $this->ownedMachinery($request, $machinery);
        abort_unless(in_array($machinery->status, [CompanyMachinery::STATUS_DRAFT, CompanyMachinery::STATUS_REJECTED], true), 403);
        $validated = $request->validate($this->machineryInputRules() + [
            'company_id' => ['prohibited'],
            'remove_photo' => ['sometimes', 'accepted'],
        ], $this->machineryInputMessages());
        $photo = $validated['photo'] ?? null;
        $removePhoto = (bool) ($validated['remove_photo'] ?? false);
        unset($validated['photo'], $validated['remove_photo']);
        $storedPhotoPath = null;
        $oldPhotoPath = null;
        $photoChanged = false;

        try {
            DB::transaction(function () use (
                $machinery,
                $validated,
                $photo,
                $removePhoto,
                $request,
                &$storedPhotoPath,
                &$oldPhotoPath,
                &$photoChanged,
            ): void {
                $lockedMachinery = CompanyMachinery::query()
                    ->whereKey($machinery->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $before = $lockedMachinery->only(array_keys($this->machineryAttributes($validated)));
                $lockedMachinery->fill($this->machineryAttributes($validated));

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, $lockedMachinery);
                    $lockedMachinery->photo_path = $storedPhotoPath;
                } elseif ($removePhoto) {
                    $lockedMachinery->photo_path = null;
                }

                $changed = $lockedMachinery->getDirty();
                $photoChanged = array_key_exists('photo_path', $changed);

                if ($photoChanged) {
                    $oldPhotoPath = $lockedMachinery->getOriginal('photo_path');
                }

                if ($changed === []) {
                    return;
                }

                $historyChanges = $changed;
                unset($historyChanges['photo_path']);
                $lockedMachinery->save();
                $lockedMachinery->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'updated',
                    'from_status' => $lockedMachinery->status,
                    'to_status' => $lockedMachinery->status,
                    'reason' => 'Mise à jour des informations de l’engin.',
                    'metadata' => [
                        'before' => array_intersect_key($before, $historyChanges),
                        'after' => $historyChanges,
                        'photo_changed' => $photoChanged,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhotoAfterFailure($storedPhotoPath, $exception);

            throw $exception;
        }

        if ($photoChanged && is_string($oldPhotoPath) && $oldPhotoPath !== '') {
            $this->deletePhotoIfUnused($oldPhotoPath, $machinery->getKey());
        }

        return to_route('prestataire.machinery.show', $machinery)
            ->with('status', 'Les informations de l’engin ont été enregistrées.');
    }

    public function submit(Request $request, CompanyMachinery $machinery): RedirectResponse
    {
        $machinery = $this->ownedMachinery($request, $machinery);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($machinery, $prestataire): void {
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(in_array($lockedMachinery->status, [CompanyMachinery::STATUS_DRAFT, CompanyMachinery::STATUS_REJECTED], true), 409);
            if (DocumentWorkflowStatus::hasRejectedDocument($lockedMachinery)) {
                throw ValidationException::withMessages([
                    'machinery' => 'Corrigez et téléversez les documents rejetés avant de resoumettre l’engin.',
                ]);
            }

            $missingDocuments = $this->missingRequiredDocuments($lockedMachinery);

            if ($missingDocuments !== []) {
                throw ValidationException::withMessages([
                    'machinery' => 'Soumission impossible. Veuillez téléverser tous les documents obligatoires : '.implode(', ', $missingDocuments).'.',
                ]);
            }

            if (blank($lockedMachinery->designation) || blank($lockedMachinery->category)) {
                throw ValidationException::withMessages([
                    'machinery' => 'Complétez la désignation et la catégorie avant la soumission.',
                ]);
            }

            $fromStatus = $lockedMachinery->status;
            $lockedMachinery->update([
                'status' => CompanyMachinery::STATUS_PENDING,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $isResubmission = $fromStatus === CompanyMachinery::STATUS_REJECTED;
            $lockedMachinery->history()->create([
                'user_id' => $prestataire->getKey(),
                'action' => $isResubmission ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachinery::STATUS_PENDING,
                'reason' => $isResubmission
                    ? 'Nouvelle soumission de l’engin après correction.'
                    : 'Soumission de l’engin pour validation.',
            ]);

            Notification::send(
                User::reviewersForPrestataire($prestataire, 'machinery.view')->get(),
                new CompanyMachineryWorkflowNotification(
                    $lockedMachinery,
                    $isResubmission
                        ? CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED
                        : CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED,
                ),
            );
        });

        return to_route('prestataire.machinery.show', $machinery)
            ->with('status', 'L’engin a été soumis pour validation.');
    }

    public function storeDocument(Request $request, CompanyMachinery $machinery, string $type): RedirectResponse
    {
        $machinery = $this->ownedMachinery($request, $machinery);
        abort_unless(in_array($type, CompanyMachineryDocument::allTypes(), true), 404);
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ], [
            'document.required' => 'Veuillez sélectionner un fichier.',
            'document.mimes' => 'Le fichier doit être au format PDF, JPG, JPEG ou PNG.',
            'document.mimetypes' => 'Le type de fichier envoyé n’est pas autorisé.',
            'document.max' => 'Le document ne peut pas dépasser 10 Mo.',
        ]);
        $file = $request->file('document');
        $extension = $file->guessExtension();

        if (! is_string($extension)) {
            throw new RuntimeException('Le type du fichier n’a pas pu être déterminé.');
        }

        $fileName = $this->generatedDocumentFileName(
            $file,
            CompanyMachineryDocument::labelForType($type),
            $machinery->registration_number ?: $machinery->serial_number ?: $machinery->designation ?: (string) $machinery->getKey(),
        );
        $path = $file->storeAs(
            'company-machinery/'.$machinery->getKey().'/'.$type,
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('Le fichier n’a pas pu être enregistré.');
        }

        $prestataire = $this->currentPrestataire($request);

        try {
            DB::transaction(function () use ($machinery, $file, $fileName, $path, $validated, $prestataire, $type): void {
                $lockedMachinery = CompanyMachinery::query()
                    ->whereKey($machinery->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $document = $lockedMachinery->documents()->where('type', $type)->lockForUpdate()->firstOrFail();
                $fromStatus = $document->status;
                $currentVersion = $document->versions()->where('is_current', true)->lockForUpdate()->first();
                $versionNumber = ($document->versions()->max('version') ?? 0) + 1;

                $document->update([
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'issued_at' => $validated['issued_at'] ?? null,
                    'expires_at' => $validated['expires_at'] ?? null,
                    'status' => CompanyMachineryDocument::STATUS_PENDING,
                    'rejection_reason' => null,
                    'submitted_at' => now(),
                    'validated_by' => null,
                    'validated_at' => null,
                ]);
                $currentVersion?->update(['is_current' => false]);
                $version = $document->versions()->create([
                    'version' => $versionNumber,
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $prestataire->getKey(),
                    'uploaded_at' => now(),
                    'notes' => $currentVersion === null
                        ? 'Téléversement initial du document.'
                        : 'Remplacement du document précédent.',
                    'is_current' => true,
                ]);
                $isReplacement = $currentVersion !== null;

                $lockedMachinery->history()->create([
                    'company_machinery_document_id' => $document->getKey(),
                    'user_id' => $prestataire->getKey(),
                    'action' => $isReplacement ? 'document_replaced' : 'document_uploaded',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyMachineryDocument::STATUS_PENDING,
                    'reason' => $isReplacement ? 'Remplacement du document.' : 'Ajout du document.',
                    'metadata' => [
                        'document_type' => $type,
                        'file_name' => $fileName,
                        'version' => $version->version,
                    ],
                ]);
                DocumentWorkflowStatus::synchronize($lockedMachinery);

                Notification::send(
                    User::reviewersForPrestataire($prestataire, 'machinery.view')->get(),
                    new CompanyMachineryWorkflowNotification(
                        $lockedMachinery,
                        $isReplacement
                            ? CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED
                            : CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        $document,
                    ),
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('documents_private')->delete($path);

            throw $exception;
        }

        return to_route('prestataire.machinery.show', $machinery)
            ->with('status', 'Le document a été téléversé. Sa validation reste indépendante de celle de l’engin.');
    }

    public function downloadDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): StreamedResponse {
        $machinery = $this->ownedMachinery($request, $machinery);
        $document = $machinery->documents()->findOrFail($document->getKey());

        return $this->privateDownload($document->file_path, $document->file_name);
    }

    public function previewDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): StreamedResponse {
        $machinery = $this->ownedMachinery($request, $machinery);
        $document = $machinery->documents()->findOrFail($document->getKey());

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
        CompanyMachineryDocumentVersion $version,
    ): StreamedResponse {
        $machinery = $this->ownedMachinery($request, $machinery);
        $document = $machinery->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
        CompanyMachineryDocumentVersion $version,
    ): StreamedResponse {
        $machinery = $this->ownedMachinery($request, $machinery);
        $document = $machinery->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    public function removeDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): RedirectResponse {
        $machinery = $this->ownedMachinery($request, $machinery);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($machinery, $document, $prestataire): void {
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = $lockedMachinery->documents()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedDocument->status !== CompanyMachineryDocument::STATUS_VALIDATED, 409, 'Un document validé ne peut pas être retiré.');
            $filePath = $lockedDocument->file_path;
            abort_unless(is_string($filePath) && $filePath !== '', 409, 'Aucun fichier courant ne peut être retiré.');

            $fromStatus = $lockedDocument->status;
            $fileName = $lockedDocument->file_name;
            $currentVersion = $lockedDocument->versions()
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();
            abort_unless(
                $currentVersion === null || $currentVersion->file_path === $filePath,
                409,
                'Le fichier courant ne correspond pas à sa version active.',
            );
            $otherVersions = $lockedDocument->versions()->where('file_path', $filePath);
            if ($currentVersion !== null) {
                $otherVersions->where('id', '!=', $currentVersion->getKey());
            }
            abort_unless(
                ! $otherVersions->exists(),
                409,
                'Le fichier courant est également référencé par une autre version.',
            );
            $currentVersion?->update(['is_current' => false, 'file_path' => '']);
            $lockedDocument->update([
                'file_name' => null,
                'file_path' => null,
                'mime_type' => null,
                'issued_at' => null,
                'expires_at' => null,
                'status' => CompanyMachineryDocument::STATUS_DRAFT,
                'rejection_reason' => null,
                'submitted_at' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $lockedMachinery->history()->create([
                'company_machinery_document_id' => $lockedDocument->getKey(),
                'user_id' => $prestataire->getKey(),
                'action' => 'document_removed',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachineryDocument::STATUS_DRAFT,
                'reason' => 'Retrait du fichier courant; les versions historiques restent conservées.',
                'metadata' => [
                    'file_name' => $fileName,
                    'version' => $currentVersion?->version,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedMachinery);
            $this->deletePrivateDocumentFile($filePath);
        });

        return to_route('prestataire.machinery.show', $machinery)
            ->with('status', 'Le fichier courant a été retiré. Les versions historiques restent disponibles.');
    }

    private function currentPrestataire(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roles()->where('name', 'prestataire')->exists(), 403);

        return $user;
    }

    private function ownedMachinery(Request $request, CompanyMachinery $machinery): CompanyMachinery
    {
        return CompanyMachinery::query()
            ->whereKey($machinery->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->where('prestataire_id', $this->currentPrestataire($request)->getKey()))
            ->firstOrFail();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function machineryInputRules(): array
    {
        return [
            'designation' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:100'],
            'assignment' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'commissioned_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function machineryInputMessages(): array
    {
        return [
            'photo.image' => 'La photo doit être une image valide.',
            'photo.mimes' => 'La photo doit être au format JPG, JPEG, PNG ou WEBP.',
            'photo.mimetypes' => 'Le type de fichier de la photo n’est pas autorisé.',
            'photo.max' => 'La photo ne peut pas dépasser 5 Mo.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function machineryAttributes(array $validated): array
    {
        return [
            'designation' => $validated['designation'],
            'category' => $validated['category'],
            'assignment' => $validated['assignment'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'commissioned_at' => $validated['commissioned_at'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }

    private function storePhoto(UploadedFile $photo, CompanyMachinery $machinery): string
    {
        $extension = $photo->guessExtension();

        if (! is_string($extension) || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Le format de la photo n’a pas pu être déterminé.');
        }

        $path = $photo->storeAs(
            'company-machinery/'.$machinery->getKey().'/photo',
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('La photo de l’engin n’a pas pu être enregistrée.');
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function missingRequiredDocuments(CompanyMachinery $machinery): array
    {
        $documents = $machinery->relationLoaded('documents')
            ? $machinery->getRelation('documents')
            : $machinery->documents()->get();
        $disk = Storage::disk('documents_private');

        return collect(CompanyMachineryDocument::requiredTypes())
            ->filter(function (string $type) use ($documents, $disk): bool {
                $document = $documents->firstWhere('type', $type);

                return $document === null
                    || blank($document->file_path)
                    || ! $disk->exists($document->file_path);
            })
            ->map(static fn (string $type): string => CompanyMachineryDocument::labelForType($type))
            ->values()
            ->all();
    }

    private function deletePhotoIfUnused(string $path, int $machineryId): void
    {
        if (CompanyMachinery::query()
            ->where('photo_path', $path)
            ->where('id', '!=', $machineryId)
            ->exists()) {
            return;
        }

        $disk = Storage::disk('documents_private');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('L’ancienne photo de l’engin n’a pas pu être supprimée.');
        }
    }

    private function deleteStoredPhotoAfterFailure(?string $path, Throwable $exception): void
    {
        if ($path === null) {
            return;
        }

        $disk = Storage::disk('documents_private');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException(
                'La nouvelle photo enregistrée avant l’échec n’a pas pu être supprimée.',
                previous: $exception,
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            CompanyMachinery::STATUS_DRAFT,
            CompanyMachinery::STATUS_PENDING,
            CompanyMachinery::STATUS_VALIDATED,
            CompanyMachinery::STATUS_REJECTED,
        ];
    }

    private function privateDownload(?string $path, ?string $fileName): StreamedResponse
    {
        abort_unless(is_string($path) && Storage::disk('documents_private')->exists($path), 404);

        return Storage::disk('documents_private')->download($path, $fileName ?: 'document.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data = []): View
    {
        return view($view, array_merge([
            'role' => 'prestataire',
            'routePrefix' => 'prestataire',
            'roleLabel' => 'Prestataire',
            'navigation' => DashboardController::navigationForRole('prestataire'),
        ], $data));
    }
}
