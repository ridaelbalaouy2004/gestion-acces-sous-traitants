<?php

namespace App\Http\Controllers\Prestataire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyEmployeeDocumentVersion;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
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

class CompanyEmployeeController extends Controller
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
            'position' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $employeeQuery = CompanyEmployee::query()
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()));
        $employeeCount = (clone $employeeQuery)
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->count();

        $employeeQuery
            ->with('company:id,name')
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['position']) && $filters['position'] !== '', fn (Builder $query) => $query->where('position', $filters['position']))
            ->when(isset($filters['search']) && $filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('cin', 'like', $term);
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CompanyEmployee::STATUS_PENDING])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        return $this->page('prestataire.employees.index', [
            'employees' => $employeeQuery->paginate(15)->withQueryString(),
            'employeeCount' => $employeeCount,
            'companies' => $prestataire->companies()->orderBy('name')->get(['id', 'name']),
            'positions' => (clone $employeeQuery)
                ->whereNotNull('position')
                ->where('position', '!=', '')
                ->distinct()
                ->orderBy('position')
                ->pluck('position'),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $companies = $this->currentPrestataire($request)->companies()->orderBy('name')->get(['id', 'name']);
        abort_if($companies->isEmpty(), 403, 'Créez d’abord une entreprise avant d’ajouter un employé.');

        return $this->page('prestataire.employees.form', [
            'employee' => null,
            'companies' => $companies,
            'pageTitle' => 'Ajouter un employé',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
        $this->normalizeCin($request);
        $validated = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where('prestataire_id', $prestataire->getKey()),
            ],
            ...$this->employeeInputRules($request->integer('company_id')),
        ], $this->employeeInputMessages());
        $profilePhoto = $validated['profile_photo'] ?? null;
        unset($validated['profile_photo']);
        $storedProfilePhotoPath = null;

        try {
            $employee = DB::transaction(function () use (
                $prestataire,
                $validated,
                $profilePhoto,
                &$storedProfilePhotoPath,
            ): CompanyEmployee {
                $company = $prestataire->companies()->findOrFail($validated['company_id']);
                $employee = $company->employees()->create([
                    ...$this->employeeAttributes($validated),
                    'status' => CompanyEmployee::STATUS_DRAFT,
                ]);

                if ($profilePhoto instanceof UploadedFile) {
                    $storedProfilePhotoPath = $this->storeProfilePhoto($profilePhoto, $employee);
                    $employee->update(['profile_photo_path' => $storedProfilePhotoPath]);
                }

                foreach (CompanyEmployeeDocument::allTypes() as $type) {
                    $employee->documents()->create([
                        'type' => $type,
                        'label' => CompanyEmployeeDocument::labelForType($type),
                        'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                    ]);
                }

                $employee->history()->create([
                    'user_id' => $prestataire->getKey(),
                    'action' => 'created',
                    'to_status' => CompanyEmployee::STATUS_DRAFT,
                    'reason' => 'Création de la fiche employé.',
                ]);

                return $employee;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredProfilePhotoAfterFailure($storedProfilePhotoPath, $exception);

            throw $exception;
        }

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'La fiche employé a été créée.');
    }

    public function show(Request $request, CompanyEmployee $employee): View
    {
        $employee = $this->ownedEmployee($request, $employee);
        $employee->load([
            'company:id,name,prestataire_id',
            'documents.versions.uploadedBy:id,name',
            'documents.validatedBy:id,name',
            'trainings.versions',
            'history.user:id,name',
            'history.document:id,type,label',
            'validatedBy:id,name',
        ]);
        $documentsByType = $employee->documents->keyBy('type');
        $documentsForDisplay = collect(CompanyEmployeeDocument::allTypes())
            ->map(fn (string $type): CompanyEmployeeDocument => $documentsByType->get($type)
                ?? new CompanyEmployeeDocument([
                    'company_employee_id' => $employee->getKey(),
                    'type' => $type,
                    'label' => CompanyEmployeeDocument::labelForType($type),
                    'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                ]));

        return $this->page('prestataire.employees.show', [
            'employee' => $employee,
            'documentsForDisplay' => $documentsForDisplay,
            'requiredDocumentTypes' => CompanyEmployeeDocument::requiredTypes(),
            'optionalDocumentTypes' => CompanyEmployeeDocument::optionalTypes(),
        ]);
    }

    public function photo(Request $request, CompanyEmployee $employee): StreamedResponse
    {
        $employee = $this->ownedEmployee($request, $employee);

        return $this->streamPrivateImageInline($employee->profile_photo_path);
    }

    public function edit(Request $request, CompanyEmployee $employee): View
    {
        $employee = $this->ownedEmployee($request, $employee);
        abort_unless(in_array($employee->status, [CompanyEmployee::STATUS_DRAFT, CompanyEmployee::STATUS_REJECTED], true), 403);

        return $this->page('prestataire.employees.form', [
            'employee' => $employee,
            'companies' => collect([$employee->company()->firstOrFail(['id', 'name'])]),
            'pageTitle' => 'Modifier un employé',
        ]);
    }

    public function update(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->ownedEmployee($request, $employee);
        abort_unless(in_array($employee->status, [CompanyEmployee::STATUS_DRAFT, CompanyEmployee::STATUS_REJECTED], true), 403);
        $this->normalizeCin($request);
        $validated = $request->validate($this->employeeInputRules(
            (int) $employee->company_id,
            $employee->getKey(),
        ) + [
            'company_id' => ['prohibited'],
            'remove_profile_photo' => ['sometimes', 'accepted'],
        ], $this->employeeInputMessages());
        $profilePhoto = $validated['profile_photo'] ?? null;
        $removeProfilePhoto = (bool) ($validated['remove_profile_photo'] ?? false);
        unset($validated['profile_photo'], $validated['remove_profile_photo']);
        $storedProfilePhotoPath = null;
        $oldProfilePhotoPath = null;
        $profilePhotoChanged = false;

        try {
            DB::transaction(function () use (
                $employee,
                $validated,
                $profilePhoto,
                $removeProfilePhoto,
                $request,
                &$storedProfilePhotoPath,
                &$oldProfilePhotoPath,
                &$profilePhotoChanged,
            ): void {
                $lockedEmployee = CompanyEmployee::query()
                    ->whereKey($employee->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $fromStatus = $lockedEmployee->status;
                $oldValues = $lockedEmployee->only(['first_name', 'last_name', 'cin', 'position', 'locker_number']);
                $lockedEmployee->fill($this->employeeAttributes($validated));

                if ($profilePhoto instanceof UploadedFile) {
                    $storedProfilePhotoPath = $this->storeProfilePhoto($profilePhoto, $lockedEmployee);
                    $lockedEmployee->profile_photo_path = $storedProfilePhotoPath;
                } elseif ($removeProfilePhoto) {
                    $lockedEmployee->profile_photo_path = null;
                }

                $newValues = $lockedEmployee->getDirty();
                $profilePhotoChanged = array_key_exists('profile_photo_path', $newValues);

                if ($profilePhotoChanged) {
                    $oldProfilePhotoPath = $lockedEmployee->getOriginal('profile_photo_path');
                }

                if ($newValues === []) {
                    return;
                }

                $historyValues = $newValues;
                unset($historyValues['profile_photo_path']);
                $lockedEmployee->save();
                $lockedEmployee->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'updated',
                    'from_status' => $fromStatus,
                    'to_status' => $lockedEmployee->status,
                    'reason' => 'Mise à jour des informations de l’employé.',
                    'metadata' => [
                        'before' => array_intersect_key($oldValues, $historyValues),
                        'after' => $historyValues,
                        'profile_photo_changed' => $profilePhotoChanged,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredProfilePhotoAfterFailure($storedProfilePhotoPath, $exception);

            throw $exception;
        }

        if ($profilePhotoChanged && is_string($oldProfilePhotoPath) && $oldProfilePhotoPath !== '') {
            $this->deleteProfilePhotoIfUnused($oldProfilePhotoPath, $employee->getKey());
        }

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'Les informations de l’employé ont été enregistrées.');
    }

    public function submit(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->ownedEmployee($request, $employee);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($employee, $prestataire): void {
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(in_array($lockedEmployee->status, [CompanyEmployee::STATUS_DRAFT, CompanyEmployee::STATUS_REJECTED], true), 409);
            DocumentWorkflowStatus::lockDocuments($lockedEmployee);

            if (! $lockedEmployee->isReadyForSubmission()) {
                $requiredDocuments = collect(CompanyEmployeeDocument::requiredTypes())
                    ->map(fn (string $type): string => CompanyEmployeeDocument::labelForType($type))
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'employee' => 'Complétez le nom, le prénom, la CIN, la fonction et les documents obligatoires ('.$requiredDocuments.') avant la soumission.',
                ]);
            }

            $fromStatus = $lockedEmployee->status;
            $lockedEmployee->update([
                'status' => CompanyEmployee::STATUS_PENDING,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $event = $fromStatus === CompanyEmployee::STATUS_REJECTED
                ? CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED
                : CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED;

            $lockedEmployee->history()->create([
                'user_id' => $prestataire->getKey(),
                'action' => $fromStatus === CompanyEmployee::STATUS_REJECTED ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployee::STATUS_PENDING,
                'reason' => 'Soumission de l’employé pour validation.',
            ]);

            Notification::send(
                User::reviewersForPrestataire($prestataire, 'employees.view')->get(),
                new CompanyEmployeeWorkflowNotification($lockedEmployee, $event),
            );
        });

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'L’employé a été soumis pour validation.');
    }

    public function storeDocument(Request $request, CompanyEmployee $employee, string $type): RedirectResponse
    {
        $employee = $this->ownedEmployee($request, $employee);
        abort_unless(in_array($type, CompanyEmployeeDocument::allTypes(), true), 404);
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
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
            CompanyEmployeeDocument::labelForType($type),
            $employee->cin ?: trim($employee->first_name.' '.$employee->last_name) ?: (string) $employee->getKey(),
        );
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs(
            'company-employees/'.$employee->getKey().'/'.$type,
            $storedName,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('Le fichier n’a pas pu être enregistré.');
        }

        $prestataire = $this->currentPrestataire($request);

        try {
            DB::transaction(function () use ($employee, $file, $fileName, $path, $validated, $prestataire, $type): void {
                $lockedEmployee = CompanyEmployee::query()
                    ->whereKey($employee->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $document = $lockedEmployee->documents()
                    ->where('type', $type)
                    ->lockForUpdate()
                    ->first();

                if ($document === null) {
                    $document = $lockedEmployee->documents()->create([
                        'type' => $type,
                        'label' => CompanyEmployeeDocument::labelForType($type),
                        'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                    ]);
                }

                $fromStatus = $document->status;
                $currentVersion = $document->versions()->where('is_current', true)->lockForUpdate()->first();
                $versionNumber = ($document->versions()->max('version') ?? 0) + 1;

                $document->update([
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'expires_at' => $validated['expires_at'] ?? null,
                    'status' => CompanyEmployeeDocument::STATUS_PENDING,
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

                $lockedEmployee->history()->create([
                    'company_employee_document_id' => $document->getKey(),
                    'user_id' => $prestataire->getKey(),
                    'action' => $isReplacement ? 'document_replaced' : 'document_uploaded',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyEmployeeDocument::STATUS_PENDING,
                    'reason' => $isReplacement ? 'Remplacement du document.' : 'Ajout du document.',
                    'metadata' => [
                        'document_type' => $type,
                        'file_name' => $fileName,
                        'version' => $version->version,
                    ],
                ]);
                DocumentWorkflowStatus::synchronize($lockedEmployee);

                Notification::send(
                    User::reviewersForPrestataire($prestataire, 'employees.view')->get(),
                    new CompanyEmployeeWorkflowNotification(
                        $lockedEmployee,
                        $isReplacement
                            ? CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED
                            : CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        $document,
                    ),
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('documents_private')->delete($path);

            throw $exception;
        }

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'Le document a été téléversé. Sa validation reste indépendante de celle de l’employé.');
    }

    public function downloadDocument(Request $request, CompanyEmployee $employee, CompanyEmployeeDocument $document): StreamedResponse
    {
        $employee = $this->ownedEmployee($request, $employee);
        $document = $employee->documents()->findOrFail($document->getKey());

        return $this->privateDownload($document->file_path, $document->file_name);
    }

    public function previewDocument(Request $request, CompanyEmployee $employee, CompanyEmployeeDocument $document): StreamedResponse
    {
        $employee = $this->ownedEmployee($request, $employee);
        $document = $employee->documents()->findOrFail($document->getKey());

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
        CompanyEmployeeDocumentVersion $version,
    ): StreamedResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $document = $employee->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
        CompanyEmployeeDocumentVersion $version,
    ): StreamedResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $document = $employee->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    public function removeDocument(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
    ): RedirectResponse {
        $employee = $this->ownedEmployee($request, $employee);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($employee, $document, $prestataire): void {
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = $lockedEmployee->documents()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedDocument->status !== CompanyEmployeeDocument::STATUS_VALIDATED, 409, 'Un document validé ne peut pas être retiré.');
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
                'expires_at' => null,
                'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                'rejection_reason' => null,
                'submitted_at' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $lockedEmployee->history()->create([
                'company_employee_document_id' => $lockedDocument->getKey(),
                'user_id' => $prestataire->getKey(),
                'action' => 'document_removed',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployeeDocument::STATUS_DRAFT,
                'reason' => 'Retrait du fichier courant; les versions historiques restent conservées.',
                'metadata' => [
                    'file_name' => $fileName,
                    'version' => $currentVersion?->version,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedEmployee);
            $this->deletePrivateDocumentFile($filePath);
        });

        return to_route('prestataire.employees.show', $employee)
            ->with('status', 'Le fichier courant a été retiré. Les versions historiques restent disponibles.');
    }

    private function currentPrestataire(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roles()->where('name', 'prestataire')->exists(), 403);

        return $user;
    }

    private function ownedEmployee(Request $request, CompanyEmployee $employee): CompanyEmployee
    {
        return CompanyEmployee::query()
            ->whereKey($employee->getKey())
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $this->currentPrestataire($request)->getKey()))
            ->firstOrFail();
    }

    private function employeeInputRules(int $companyId, ?int $ignoreId = null): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'cin' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('company_employees', 'cin')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'position' => ['nullable', 'string', 'max:255'],
            'locker_number' => ['nullable', 'string', 'max:50'],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ];
    }

    private function employeeInputMessages(): array
    {
        return [
            'cin.unique' => 'Cette CIN est déjà utilisée dans cette entreprise.',
            'first_name.max' => 'Le prénom ne peut pas dépasser 100 caractères.',
            'last_name.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            'position.max' => 'La fonction ne peut pas dépasser 255 caractères.',
            'profile_photo.image' => 'La photo doit être une image valide.',
            'profile_photo.mimes' => 'La photo doit être au format JPG, JPEG, PNG ou WEBP.',
            'profile_photo.mimetypes' => 'Le type de fichier de la photo n’est pas autorisé.',
            'profile_photo.max' => 'La photo ne peut pas dépasser 5 Mo.',
        ];
    }

    private function employeeAttributes(array $validated): array
    {
        return [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'cin' => $validated['cin'] ?? null,
            'position' => $validated['position'] ?? null,
            'locker_number' => $validated['locker_number'] ?? null,
        ];
    }

    private function storeProfilePhoto(UploadedFile $photo, CompanyEmployee $employee): string
    {
        $extension = $photo->guessExtension();

        if (! is_string($extension) || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Le format de la photo n’a pas pu être déterminé.');
        }

        $path = $photo->storeAs(
            'company-employees/'.$employee->getKey().'/profile',
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('La photo de l’employé n’a pas pu être enregistrée.');
        }

        return $path;
    }

    private function deleteProfilePhotoIfUnused(string $path, int $employeeId): void
    {
        if (CompanyEmployee::query()
            ->where('profile_photo_path', $path)
            ->where('id', '!=', $employeeId)
            ->exists()) {
            return;
        }

        $disk = Storage::disk('documents_private');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('L’ancienne photo de l’employé n’a pas pu être supprimée.');
        }
    }

    private function deleteStoredProfilePhotoAfterFailure(?string $path, Throwable $exception): void
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

    private function normalizeCin(Request $request): void
    {
        $cin = $request->input('cin');

        if (is_string($cin)) {
            $request->merge(['cin' => Str::upper(trim($cin))]);
        }
    }

    private function statuses(): array
    {
        return [
            CompanyEmployee::STATUS_DRAFT,
            CompanyEmployee::STATUS_PENDING,
            CompanyEmployee::STATUS_VALIDATED,
            CompanyEmployee::STATUS_REJECTED,
        ];
    }

    private function privateDownload(?string $path, ?string $fileName): StreamedResponse
    {
        abort_unless(is_string($path) && Storage::disk('documents_private')->exists($path), 404);

        return Storage::disk('documents_private')->download($path, $fileName ?: 'document.pdf');
    }

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
