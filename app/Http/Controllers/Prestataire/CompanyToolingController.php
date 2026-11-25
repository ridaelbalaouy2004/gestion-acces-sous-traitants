<?php

namespace App\Http\Controllers\Prestataire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\CompanyToolingDocumentVersion;
use App\Models\User;
use App\Notifications\CompanyToolingWorkflowNotification;
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

class CompanyToolingController extends Controller
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
        $ownedToolings = CompanyTooling::query()
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()));
        $toolingCount = (clone $ownedToolings)
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->count();
        $toolingQuery = (clone $ownedToolings)
            ->with('company:id,name')
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['category']) && $filters['category'] !== '', fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['search']) && $filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery->where('designation', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('internal_reference', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('serial_number', 'like', $term);
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CompanyTooling::STATUS_PENDING])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        return $this->page('prestataire.toolings.index', [
            'toolings' => $toolingQuery->paginate(15)->withQueryString(),
            'toolingCount' => $toolingCount,
            'companies' => $prestataire->companies()->orderBy('name')->get(['id', 'name']),
            'categories' => (clone $ownedToolings)
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
        abort_if($companies->isEmpty(), 403, 'Créez d’abord une entreprise avant d’ajouter un outillage.');

        return $this->page('prestataire.toolings.form', [
            'tooling' => null,
            'companies' => $companies,
            'pageTitle' => 'Ajouter un outillage',
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
            ...$this->toolingInputRules(),
        ], $this->toolingInputMessages());
        $photo = $validated['photo'] ?? null;
        unset($validated['photo']);
        $storedPhotoPath = null;

        try {
            $tooling = DB::transaction(function () use (
                $prestataire,
                $validated,
                $photo,
                &$storedPhotoPath,
            ): CompanyTooling {
                $company = $prestataire->companies()->findOrFail($validated['company_id']);
                $tooling = $company->toolings()->create([
                    ...$this->toolingAttributes($validated),
                    'status' => CompanyTooling::STATUS_DRAFT,
                ]);

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, $tooling);
                    $tooling->update(['photo_path' => $storedPhotoPath]);
                }

                foreach (CompanyToolingDocument::standardTypes() as $type) {
                    $tooling->documents()->create([
                        'type' => $type,
                        'label' => CompanyToolingDocument::labelForType($type),
                        'status' => CompanyToolingDocument::STATUS_DRAFT,
                    ]);
                }

                $tooling->history()->create([
                    'user_id' => $prestataire->getKey(),
                    'action' => 'created',
                    'to_status' => CompanyTooling::STATUS_DRAFT,
                    'reason' => 'Création de la fiche outillage.',
                ]);

                return $tooling;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhotoAfterFailure($storedPhotoPath, $exception);

            throw $exception;
        }

        return to_route('prestataire.toolings.show', $tooling)
            ->with('status', 'La fiche outillage a été créée.');
    }

    public function show(Request $request, CompanyTooling $tooling): View
    {
        $tooling = $this->ownedTooling($request, $tooling);
        $tooling->load([
            'company:id,name,prestataire_id',
            'documents.versions.uploadedBy:id,name',
            'documents.validatedBy:id,name',
            'history.user:id,name',
            'history.document:id,type,label',
            'validatedBy:id,name',
        ]);

        return $this->page('prestataire.toolings.show', [
            'tooling' => $tooling,
            'missingRequiredDocuments' => $this->missingRequiredDocuments($tooling),
        ]);
    }

    public function photo(Request $request, CompanyTooling $tooling): StreamedResponse
    {
        $tooling = $this->ownedTooling($request, $tooling);

        return $this->streamPrivateImageInline($tooling->photo_path);
    }

    public function edit(Request $request, CompanyTooling $tooling): View
    {
        $tooling = $this->ownedTooling($request, $tooling);
        abort_unless(in_array($tooling->status, [CompanyTooling::STATUS_DRAFT, CompanyTooling::STATUS_REJECTED], true), 403);

        return $this->page('prestataire.toolings.form', [
            'tooling' => $tooling,
            'companies' => collect([$tooling->company()->firstOrFail(['id', 'name'])]),
            'pageTitle' => 'Modifier un outillage',
        ]);
    }

    public function update(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->ownedTooling($request, $tooling);
        abort_unless(in_array($tooling->status, [CompanyTooling::STATUS_DRAFT, CompanyTooling::STATUS_REJECTED], true), 403);
        $validated = $request->validate($this->toolingInputRules() + [
            'company_id' => ['prohibited'],
            'remove_photo' => ['sometimes', 'accepted'],
        ], $this->toolingInputMessages());
        $photo = $validated['photo'] ?? null;
        $removePhoto = (bool) ($validated['remove_photo'] ?? false);
        unset($validated['photo'], $validated['remove_photo']);
        $prestataire = $this->currentPrestataire($request);
        $storedPhotoPath = null;
        $oldPhotoPath = null;
        $photoChanged = false;

        try {
            DB::transaction(function () use (
                $tooling,
                $validated,
                $photo,
                $removePhoto,
                $prestataire,
                &$storedPhotoPath,
                &$oldPhotoPath,
                &$photoChanged,
            ): void {
                $lockedTooling = CompanyTooling::query()
                    ->whereKey($tooling->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless(in_array($lockedTooling->status, [CompanyTooling::STATUS_DRAFT, CompanyTooling::STATUS_REJECTED], true), 409);

                $before = $lockedTooling->only(array_keys($this->toolingAttributes($validated)));
                $lockedTooling->fill($this->toolingAttributes($validated));

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, $lockedTooling);
                    $lockedTooling->photo_path = $storedPhotoPath;
                } elseif ($removePhoto) {
                    $lockedTooling->photo_path = null;
                }

                $changed = $lockedTooling->getDirty();
                $photoChanged = array_key_exists('photo_path', $changed);

                if ($photoChanged) {
                    $oldPhotoPath = $lockedTooling->getOriginal('photo_path');
                }

                if ($changed === []) {
                    return;
                }

                $historyChanges = $changed;
                unset($historyChanges['photo_path']);
                $lockedTooling->save();

                if ($historyChanges === []) {
                    return;
                }

                $lockedTooling->history()->create([
                    'user_id' => $prestataire->getKey(),
                    'action' => 'updated',
                    'from_status' => $lockedTooling->status,
                    'to_status' => $lockedTooling->status,
                    'reason' => 'Mise à jour des informations de l’outillage.',
                    'metadata' => [
                        'before' => array_intersect_key($before, $historyChanges),
                        'after' => $historyChanges,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhotoAfterFailure($storedPhotoPath, $exception);

            throw $exception;
        }

        if ($photoChanged && is_string($oldPhotoPath) && $oldPhotoPath !== '') {
            $this->deletePhotoIfUnused($oldPhotoPath, $tooling->getKey());
        }

        return to_route('prestataire.toolings.show', $tooling)
            ->with('status', 'Les informations de l’outillage ont été enregistrées.');
    }

    public function submit(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->ownedTooling($request, $tooling);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($tooling, $prestataire): void {
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(in_array($lockedTooling->status, [CompanyTooling::STATUS_DRAFT, CompanyTooling::STATUS_REJECTED], true), 409);
            if (DocumentWorkflowStatus::hasRejectedDocument($lockedTooling)) {
                throw ValidationException::withMessages([
                    'tooling' => 'Corrigez et téléversez les documents rejetés avant de resoumettre l’outillage.',
                ]);
            }

            $missingDocuments = $this->missingRequiredDocuments($lockedTooling);

            if ($missingDocuments !== []) {
                throw ValidationException::withMessages([
                    'tooling' => 'Soumission impossible. Veuillez téléverser tous les documents obligatoires : '.implode(', ', $missingDocuments).'.',
                ]);
            }

            if (blank($lockedTooling->designation) || blank($lockedTooling->category) || $lockedTooling->quantity < 1) {
                throw ValidationException::withMessages([
                    'tooling' => 'Complétez la désignation, la catégorie et la quantité avant la soumission.',
                ]);
            }

            $fromStatus = $lockedTooling->status;
            $lockedTooling->update([
                'status' => CompanyTooling::STATUS_PENDING,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $isResubmission = $fromStatus === CompanyTooling::STATUS_REJECTED;
            $lockedTooling->history()->create([
                'user_id' => $prestataire->getKey(),
                'action' => $isResubmission ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => CompanyTooling::STATUS_PENDING,
                'reason' => $isResubmission
                    ? 'Nouvelle soumission de l’outillage après correction.'
                    : 'Soumission de l’outillage pour validation.',
            ]);

            Notification::send(
                User::reviewersForPrestataire($prestataire, 'toolings.view')->get(),
                new CompanyToolingWorkflowNotification(
                    $lockedTooling,
                    $isResubmission
                        ? CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED
                        : CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED,
                ),
            );
        });

        return to_route('prestataire.toolings.show', $tooling)
            ->with('status', 'L’outillage a été soumis pour validation.');
    }

    public function storeDocument(Request $request, CompanyTooling $tooling, string $type): RedirectResponse
    {
        $tooling = $this->ownedTooling($request, $tooling);
        abort_unless(in_array($type, CompanyToolingDocument::standardTypes(), true), 404);
        $document = $tooling->documents()->where('type', $type)->firstOrFail();

        return $this->saveDocumentUpload($request, $tooling, $document, $this->currentPrestataire($request));
    }

    public function storeCustomDocument(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->ownedTooling($request, $tooling);
        $prestataire = $this->currentPrestataire($request);
        $validated = $request->validate($this->documentRules() + [
            'label' => ['required', 'string', 'max:100'],
        ]);
        $type = 'custom_'.Str::uuid()->toString();

        return $this->saveDocumentUpload(
            $request,
            $tooling,
            null,
            $prestataire,
            $type,
            trim($validated['label']),
            $validated,
        );
    }

    public function replaceDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): RedirectResponse {
        $tooling = $this->ownedTooling($request, $tooling);
        $document = $tooling->documents()->findOrFail($document->getKey());

        return $this->saveDocumentUpload($request, $tooling, $document, $this->currentPrestataire($request));
    }

    public function downloadDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): StreamedResponse {
        $tooling = $this->ownedTooling($request, $tooling);
        $document = $tooling->documents()->findOrFail($document->getKey());

        return $this->privateDownload($document->file_path, $document->file_name);
    }

    public function previewDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): StreamedResponse {
        $tooling = $this->ownedTooling($request, $tooling);
        $document = $tooling->documents()->findOrFail($document->getKey());

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
        CompanyToolingDocumentVersion $version,
    ): StreamedResponse {
        $tooling = $this->ownedTooling($request, $tooling);
        $document = $tooling->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
        CompanyToolingDocumentVersion $version,
    ): StreamedResponse {
        $tooling = $this->ownedTooling($request, $tooling);
        $document = $tooling->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    public function removeDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): RedirectResponse {
        $tooling = $this->ownedTooling($request, $tooling);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($tooling, $document, $prestataire): void {
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = $lockedTooling->documents()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedDocument->status !== CompanyToolingDocument::STATUS_VALIDATED, 409, 'Un document validé ne peut pas être retiré.');
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
                'status' => CompanyToolingDocument::STATUS_DRAFT,
                'rejection_reason' => null,
                'submitted_at' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $lockedTooling->history()->create([
                'company_tooling_document_id' => $lockedDocument->getKey(),
                'user_id' => $prestataire->getKey(),
                'action' => 'document_removed',
                'from_status' => $fromStatus,
                'to_status' => CompanyToolingDocument::STATUS_DRAFT,
                'reason' => 'Retrait du fichier courant; les versions historiques restent conservées.',
                'metadata' => [
                    'file_name' => $fileName,
                    'version' => $currentVersion?->version,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedTooling);
            $this->deletePrivateDocumentFile($filePath);
        });

        return to_route('prestataire.toolings.show', $tooling)
            ->with('status', 'Le fichier courant a été retiré. Les versions historiques restent disponibles.');
    }

    /**
     * @param  array<string, mixed>|null  $validated
     */
    private function saveDocumentUpload(
        Request $request,
        CompanyTooling $tooling,
        ?CompanyToolingDocument $document,
        User $prestataire,
        ?string $customType = null,
        ?string $customLabel = null,
        ?array $validated = null,
    ): RedirectResponse {
        $validated ??= $request->validate($this->documentRules());
        $file = $validated['document'];
        if (! $file instanceof UploadedFile) {
            throw new RuntimeException('Le fichier transmis n’est pas valide.');
        }

        $extension = $file->guessExtension();
        if (! is_string($extension)) {
            throw new RuntimeException('Le type du fichier n’a pas pu être déterminé.');
        }

        $fileName = $this->generatedDocumentFileName(
            $file,
            $customLabel ?? CompanyToolingDocument::labelForType($customType ?? $document?->type ?? ''),
            $tooling->internal_reference ?: $tooling->serial_number ?: $tooling->designation ?: (string) $tooling->getKey(),
        );
        $path = $file->storeAs(
            'company-toolings/'.$tooling->getKey(),
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );
        if (! is_string($path)) {
            throw new RuntimeException('Le fichier n’a pas pu être enregistré.');
        }

        try {
            DB::transaction(function () use (
                $tooling,
                $document,
                $prestataire,
                $customType,
                $customLabel,
                $validated,
                $file,
                $fileName,
                $path,
            ): void {
                $lockedTooling = CompanyTooling::query()
                    ->whereKey($tooling->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedDocument = $document === null
                    ? $lockedTooling->documents()->create([
                        'type' => $customType,
                        'label' => $customLabel,
                        'status' => CompanyToolingDocument::STATUS_DRAFT,
                    ])
                    : $lockedTooling->documents()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
                $fromStatus = $lockedDocument->status;
                $currentVersion = $lockedDocument->versions()->where('is_current', true)->lockForUpdate()->first();
                $versionNumber = ($lockedDocument->versions()->max('version') ?? 0) + 1;

                $lockedDocument->update([
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'issued_at' => $validated['issued_at'] ?? null,
                    'expires_at' => $validated['expires_at'] ?? null,
                    'status' => CompanyToolingDocument::STATUS_PENDING,
                    'rejection_reason' => null,
                    'submitted_at' => now(),
                    'validated_by' => null,
                    'validated_at' => null,
                ]);
                $currentVersion?->update(['is_current' => false]);
                $version = $lockedDocument->versions()->create([
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

                $lockedTooling->history()->create([
                    'company_tooling_document_id' => $lockedDocument->getKey(),
                    'user_id' => $prestataire->getKey(),
                    'action' => $isReplacement ? 'document_replaced' : 'document_uploaded',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyToolingDocument::STATUS_PENDING,
                    'reason' => $isReplacement ? 'Remplacement du document.' : 'Ajout du document.',
                    'metadata' => [
                        'document_type' => $lockedDocument->type,
                        'file_name' => $fileName,
                        'version' => $version->version,
                    ],
                ]);
                DocumentWorkflowStatus::synchronize($lockedTooling);

                $lockedTooling->loadMissing('company.prestataire');
                Notification::send(
                    User::reviewersForPrestataire($lockedTooling->company->prestataire, 'toolings.view')->get(),
                    new CompanyToolingWorkflowNotification(
                        $lockedTooling,
                        $isReplacement
                            ? CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED
                            : CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        $lockedDocument,
                    ),
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('documents_private')->delete($path);

            throw $exception;
        }

        return to_route('prestataire.toolings.show', $tooling)
            ->with('status', 'Le document a été téléversé. Sa validation reste indépendante de celle de l’outillage.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function toolingInputRules(): array
    {
        return [
            'designation' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:100'],
            'internal_reference' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'physical_condition' => ['nullable', 'string', 'in:new,good,average,damaged'],
            'acquired_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:5000'],
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
    private function toolingInputMessages(): array
    {
        return [
            'photo.image' => 'La photo doit être une image valide.',
            'photo.mimes' => 'La photo doit être au format JPG, JPEG, PNG ou WEBP.',
            'photo.mimetypes' => 'Le type de fichier de la photo n’est pas autorisé.',
            'photo.max' => 'La photo ne peut pas dépasser 5 Mo.',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function documentRules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function toolingAttributes(array $validated): array
    {
        return [
            'designation' => $validated['designation'],
            'category' => $validated['category'],
            'internal_reference' => $validated['internal_reference'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'quantity' => $validated['quantity'],
            'physical_condition' => $validated['physical_condition'] ?? null,
            'acquired_at' => $validated['acquired_at'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'observations' => $validated['observations'] ?? null,
        ];
    }

    private function storePhoto(UploadedFile $photo, CompanyTooling $tooling): string
    {
        $extension = $photo->guessExtension();

        if (! is_string($extension) || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Le format de la photo n’a pas pu être déterminé.');
        }

        $path = $photo->storeAs(
            'company-toolings/'.$tooling->getKey().'/photo',
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('La photo de l’outillage n’a pas pu être enregistrée.');
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function missingRequiredDocuments(CompanyTooling $tooling): array
    {
        $documents = $tooling->relationLoaded('documents')
            ? $tooling->getRelation('documents')
            : $tooling->documents()->get();
        $disk = Storage::disk('documents_private');

        return collect(CompanyToolingDocument::requiredTypes())
            ->filter(function (string $type) use ($documents, $disk): bool {
                $document = $documents->firstWhere('type', $type);

                return $document === null
                    || blank($document->file_path)
                    || ! $disk->exists($document->file_path);
            })
            ->map(static fn (string $type): string => CompanyToolingDocument::labelForType($type))
            ->values()
            ->all();
    }

    private function deletePhotoIfUnused(string $path, int $toolingId): void
    {
        if (CompanyTooling::query()
            ->where('photo_path', $path)
            ->where('id', '!=', $toolingId)
            ->exists()) {
            return;
        }

        $disk = Storage::disk('documents_private');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('L’ancienne photo de l’outillage n’a pas pu être supprimée.');
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
            CompanyTooling::STATUS_DRAFT,
            CompanyTooling::STATUS_PENDING,
            CompanyTooling::STATUS_VALIDATED,
            CompanyTooling::STATUS_REJECTED,
        ];
    }

    private function currentPrestataire(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roles()->where('name', 'prestataire')->exists(), 403);

        return $user;
    }

    private function ownedTooling(Request $request, CompanyTooling $tooling): CompanyTooling
    {
        return CompanyTooling::query()
            ->whereKey($tooling->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->where('prestataire_id', $this->currentPrestataire($request)->getKey()))
            ->firstOrFail();
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
