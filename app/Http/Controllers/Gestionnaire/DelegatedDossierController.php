<?php

namespace App\Http\Controllers\Gestionnaire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use App\Notifications\CompanyMachineryWorkflowNotification;
use App\Notifications\CompanyToolingWorkflowNotification;
use App\Services\CompanyListImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
use Throwable;

class DelegatedDossierController extends Controller
{
    public function index(Request $request): View
    {
        $manager = $this->primaryManager($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                Rule::in([
                    Company::STATUS_DRAFT,
                    Company::STATUS_PENDING,
                    Company::STATUS_VALIDATED,
                    Company::STATUS_REJECTED,
                ]),
            ],
            'date_field' => ['nullable', Rule::in(['created_at', 'submitted_at'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $dateField = $filters['date_field'] ?? 'created_at';
        $hasDateFilters = filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null);
        $hasCompanyFilters = filled($filters['status'] ?? null) || $hasDateFilters;
        $applyCompanyFilters = static function (Builder $query) use ($filters, $dateField): void {
            if (filled($filters['status'] ?? null)) {
                $query->where('status', $filters['status']);
            }

            if (filled($filters['date_from'] ?? null)) {
                $query->whereDate($dateField, '>=', $filters['date_from']);
            }

            if (filled($filters['date_to'] ?? null)) {
                $query->whereDate($dateField, '<=', $filters['date_to']);
            }
        };
        $companyStatusCounts = Company::query()
            ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id'))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $companyStats = [
            'total' => (int) $companyStatusCounts->sum(),
            'pending' => (int) $companyStatusCounts->get(Company::STATUS_PENDING, 0),
            'validated' => (int) $companyStatusCounts->get(Company::STATUS_VALIDATED, 0),
            'rejected' => (int) $companyStatusCounts->get(Company::STATUS_REJECTED, 0),
        ];
        $prestatairesQuery = $manager->accessiblePrestataires()
            ->withCount([
                'companies',
                'companies as pending_companies_count' => fn (Builder $query) => $query->where('status', Company::STATUS_PENDING),
                'companies as validated_companies_count' => fn (Builder $query) => $query->where('status', Company::STATUS_VALIDATED),
                'companies as rejected_companies_count' => fn (Builder $query) => $query->where('status', Company::STATUS_REJECTED),
                'companies as draft_companies_count' => fn (Builder $query) => $query->where('status', Company::STATUS_DRAFT),
            ])
            ->withMax('companies as latest_company_created_at', 'created_at');
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $prestatairesQuery->where(function (Builder $query) use (
                $search,
                $hasCompanyFilters,
                $applyCompanyFilters,
            ): void {
                $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('companies', function (Builder $companyQuery) use (
                        $search,
                        $hasCompanyFilters,
                        $applyCompanyFilters,
                    ): void {
                        $companyQuery->where('name', 'like', '%'.$search.'%');

                        if ($hasCompanyFilters) {
                            $applyCompanyFilters($companyQuery);
                        }
                    });
            });
        }

        if ($hasCompanyFilters) {
            $prestatairesQuery->whereHas('companies', $applyCompanyFilters);
        }

        $prestataires = $prestatairesQuery
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15, ['id', 'name', 'email'])
            ->withQueryString();

        return view('gestionnaire.delegated.index', $this->managerViewData([
            'prestataires' => $prestataires,
            'companyStats' => $companyStats,
            'filters' => $filters,
            'hasActiveFilters' => $search !== '' || $hasCompanyFilters,
        ]));
    }

    public function show(Request $request, User $prestataire): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $companies = $prestataire->companies()
            ->orderBy('name')
            ->get();

        return view('gestionnaire.delegated.show', $this->managerViewData([
            'prestataire' => $prestataire,
            'companies' => $companies,
        ]));
    }

    public function showCompany(
        Request $request,
        User $prestataire,
        Company $company,
        CompanyListImportService $importService,
    ): View {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $this->ownedCompany($prestataire, $company);
        $company->load([
            'documents.versions.uploadedBy:id,name',
            'employees.documents.versions.uploadedBy:id,name',
            'machinery.documents.versions.uploadedBy:id,name',
            'toolings.documents.versions.uploadedBy:id,name',
        ]);
        $excelDataDocumentIds = $company->documents
            ->filter(fn (CompanyDocument $document): bool => $importService->hasImportedData($company, $document))
            ->modelKeys();
        $companyDocumentsByType = $company->documents->keyBy('type');
        $company->setRelation('documentsForDisplay', collect(CompanyDocument::allTypes())
            ->map(fn (string $type): CompanyDocument => $companyDocumentsByType->get($type)
                ?? new CompanyDocument([
                    'company_id' => $company->getKey(),
                    'type' => $type,
                    'label' => CompanyDocument::labelForType($type),
                    'status' => CompanyDocument::STATUS_DRAFT,
                ]))
            ->concat($company->documents->reject(
                fn (CompanyDocument $document): bool => in_array($document->type, CompanyDocument::allTypes(), true),
            )));

        $company->employees->each(function (CompanyEmployee $employee): void {
            $employeeDocumentsByType = $employee->documents->keyBy('type');
            $employee->setRelation('documentsForDisplay', collect(CompanyEmployeeDocument::allTypes())
                ->map(fn (string $type): CompanyEmployeeDocument => $employeeDocumentsByType->get($type)
                    ?? new CompanyEmployeeDocument([
                        'company_employee_id' => $employee->getKey(),
                        'type' => $type,
                        'label' => CompanyEmployeeDocument::labelForType($type),
                        'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                    ])));
        });

        return view('gestionnaire.delegated.company', $this->managerViewData([
            'prestataire' => $prestataire,
            'company' => $company,
            'companies' => collect([$company]),
            'excelDataDocumentIds' => $excelDataDocumentIds,
            'approvalBlockers' => $company->approvalBlockers(),
        ]));
    }

    public function importedCompanyDocumentData(
        Request $request,
        User $prestataire,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $this->ownedCompany($prestataire, $company);
        $document = $company->documents()->findOrFail($document->getKey());
        $data = $importService->importedData($company, $document);
        abort_if($data === null, 404);

        return view('company-list-import.data-modal-content', ['data' => $data]);
    }

    public function createCompany(Request $request, User $prestataire): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);

        return view('prestataire.companies.form', $this->managerViewData([
            'company' => null,
            'documentTypes' => CompanyDocument::allTypes(),
            'formAction' => route('gestionnaire.delegated.companies.store', $prestataire),
            'formCancelUrl' => route('gestionnaire.delegated.show', $prestataire),
            'delegatedPrestataire' => $prestataire,
        ]));
    }

    public function editCompany(Request $request, User $prestataire, Company $company): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $this->ownedCompany($prestataire, $company);
        $this->ensureEditable($company);

        return view('prestataire.companies.form', $this->managerViewData([
            'company' => $company,
            'documentTypes' => CompanyDocument::allTypes(),
            'formAction' => route('gestionnaire.delegated.companies.update', [$prestataire, $company]),
            'formCancelUrl' => route('gestionnaire.delegated.companies.show', [$prestataire, $company]),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $company,
        ]));
    }

    public function storeCompany(Request $request, User $prestataire): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $validated = $request->validate($this->companyRules($request), $this->companyMessages());
        $company = DB::transaction(function () use ($prestataire, $validated, $request): Company {
            $company = $prestataire->companies()->create([
                ...$validated,
                'status' => Company::STATUS_DRAFT,
            ]);

            foreach (CompanyDocument::requiredTypes() as $type) {
                $company->documents()->create([
                    'type' => $type,
                    'label' => CompanyDocument::labelForType($type),
                    'status' => CompanyDocument::STATUS_DRAFT,
                ]);
            }

            $company->reviewHistory()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'created',
                'to_status' => Company::STATUS_DRAFT,
                'reason' => 'Création de l’entreprise par le Gestionnaire pour le compte du Prestataire.',
            ]);

            return $company;
        });

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $company])
            ->with('status', 'L’entreprise a été créée pour le compte du Prestataire.');
    }

    public function updateCompany(Request $request, User $prestataire, Company $company): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $this->ownedCompany($prestataire, $company);
        $this->ensureEditable($company);
        $validated = $request->validate($this->companyRules($request), $this->companyMessages());

        DB::transaction(function () use ($company, $validated, $request): void {
            $lockedCompany = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($lockedCompany);
            $fromStatus = $lockedCompany->status;
            $lockedCompany->update([
                ...$validated,
                'status' => Company::STATUS_DRAFT,
                'rejection_reason' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            DocumentWorkflowStatus::synchronize($lockedCompany);
            $lockedCompany->reviewHistory()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'updated',
                'from_status' => $fromStatus,
                'to_status' => $lockedCompany->status,
                'reason' => 'Mise à jour des informations de l’entreprise par le Gestionnaire pour le compte du Prestataire.',
            ]);
        });

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $company])
            ->with('status', 'Les informations de l’entreprise ont été enregistrées.');
    }

    public function createEmployee(Request $request, User $prestataire): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $companies = $prestataire->companies()->orderBy('name')->get(['id', 'name']);
        abort_if($companies->isEmpty(), 403, 'Créez d’abord une entreprise avant d’ajouter un employé.');
        $selectedCompany = $this->requestedCompany($request, $prestataire);

        return view('prestataire.employees.form', $this->managerViewData([
            'employee' => null,
            'companies' => $companies,
            'pageTitle' => 'Ajouter un employé pour '.$prestataire->name,
            'formAction' => route('gestionnaire.delegated.employees.store', $prestataire),
            'formCancelUrl' => $selectedCompany
                ? $this->companyDetailsUrl($prestataire, $selectedCompany)
                : route('gestionnaire.delegated.show', $prestataire),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $selectedCompany,
        ]));
    }

    public function editEmployee(Request $request, User $prestataire, CompanyEmployee $employee): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $employee = $this->ownedEmployee($prestataire, $employee);
        $this->ensureEditable($employee);

        return view('prestataire.employees.form', $this->managerViewData([
            'employee' => $employee,
            'companies' => collect([$employee->company]),
            'pageTitle' => 'Modifier la fiche employé',
            'formAction' => route('gestionnaire.delegated.employees.update', [$prestataire, $employee]),
            'formCancelUrl' => $this->companyDetailsUrl($prestataire, $employee),
            'photoUrl' => route('gestionnaire.employees.photo', $employee),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $employee->company,
        ]));
    }

    public function storeEmployee(Request $request, User $prestataire): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $this->normalizeCin($request);
        $validated = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where('prestataire_id', $prestataire->getKey()),
            ],
            ...$this->employeeInputRules($request->integer('company_id')),
        ], $this->employeeInputMessages());
        $photo = $validated['profile_photo'] ?? null;
        unset($validated['profile_photo']);
        $storedPhotoPath = null;

        try {
            $employee = DB::transaction(function () use ($prestataire, $validated, $photo, $request, &$storedPhotoPath): CompanyEmployee {
                $company = $prestataire->companies()->findOrFail($validated['company_id']);
                $employee = $company->employees()->create([
                    ...$this->employeeAttributes($validated),
                    'status' => CompanyEmployee::STATUS_DRAFT,
                ]);

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, 'company-employees/'.$employee->getKey().'/profile');
                    $employee->update(['profile_photo_path' => $storedPhotoPath]);
                }

                foreach (CompanyEmployeeDocument::allTypes() as $type) {
                    $employee->documents()->create([
                        'type' => $type,
                        'label' => CompanyEmployeeDocument::labelForType($type),
                        'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                    ]);
                }

                $employee->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'created',
                    'to_status' => CompanyEmployee::STATUS_DRAFT,
                    'reason' => 'Création de la fiche employé par le Gestionnaire pour le compte du Prestataire.',
                ]);

                return $employee;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhoto($storedPhotoPath, $exception);

            throw $exception;
        }

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $employee->company_id])
            ->with('status', 'La fiche employé a été créée.');
    }

    public function updateEmployee(Request $request, User $prestataire, CompanyEmployee $employee): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $employee = $this->ownedEmployee($prestataire, $employee);
        $this->ensureEditable($employee);
        $this->normalizeCin($request);
        $validated = $request->validate(
            $this->employeeInputRules($employee->company_id, $employee->getKey()),
            $this->employeeInputMessages(),
        );
        $photo = $validated['profile_photo'] ?? null;
        unset($validated['profile_photo']);
        $oldPhotoPath = $employee->profile_photo_path;
        $newPhotoPath = null;

        try {
            DB::transaction(function () use ($employee, $validated, $photo, $request, &$newPhotoPath): void {
                $lockedEmployee = CompanyEmployee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
                $this->ensureEditable($lockedEmployee);
                $fromStatus = $lockedEmployee->status;
                $attributes = [
                    ...$this->employeeAttributes($validated),
                    'status' => CompanyEmployee::STATUS_DRAFT,
                    'rejection_reason' => null,
                    'validated_by' => null,
                    'validated_at' => null,
                ];

                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $this->storePhoto($photo, 'company-employees/'.$lockedEmployee->getKey().'/profile');
                    $attributes['profile_photo_path'] = $newPhotoPath;
                } elseif ($request->boolean('remove_profile_photo')) {
                    $attributes['profile_photo_path'] = null;
                }

                $lockedEmployee->update($attributes);
                DocumentWorkflowStatus::synchronize($lockedEmployee);
                $lockedEmployee->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'updated',
                    'from_status' => $fromStatus,
                    'to_status' => $lockedEmployee->status,
                    'reason' => 'Mise à jour de la fiche employé par le Gestionnaire pour le compte du Prestataire.',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhoto($newPhotoPath, $exception);

            throw $exception;
        }

        if (is_string($oldPhotoPath) && $oldPhotoPath !== '' && $oldPhotoPath !== $newPhotoPath && ($newPhotoPath !== null || $request->boolean('remove_profile_photo'))) {
            $this->deletePhotoIfUnused($oldPhotoPath);
        }

        return redirect()->to($this->companyDetailsUrl($prestataire, $employee))
            ->with('status', 'La fiche employé a été mise à jour.');
    }

    public function createMachinery(Request $request, User $prestataire): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $companies = $prestataire->companies()->orderBy('name')->get(['id', 'name']);
        abort_if($companies->isEmpty(), 403, 'Créez d’abord une entreprise avant d’ajouter un engin.');
        $selectedCompany = $this->requestedCompany($request, $prestataire);

        return view('prestataire.machinery.form', $this->managerViewData([
            'machinery' => null,
            'companies' => $companies,
            'pageTitle' => 'Ajouter un engin pour '.$prestataire->name,
            'formAction' => route('gestionnaire.delegated.machinery.store', $prestataire),
            'formCancelUrl' => $selectedCompany
                ? $this->companyDetailsUrl($prestataire, $selectedCompany)
                : route('gestionnaire.delegated.show', $prestataire),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $selectedCompany,
        ]));
    }

    public function editMachinery(Request $request, User $prestataire, CompanyMachinery $machinery): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $machinery = $this->ownedMachinery($prestataire, $machinery);
        $this->ensureEditable($machinery);

        return view('prestataire.machinery.form', $this->managerViewData([
            'machinery' => $machinery,
            'companies' => collect([$machinery->company]),
            'pageTitle' => 'Modifier l’engin',
            'formAction' => route('gestionnaire.delegated.machinery.update', [$prestataire, $machinery]),
            'formCancelUrl' => $this->companyDetailsUrl($prestataire, $machinery),
            'photoUrl' => route('gestionnaire.machinery.photo', $machinery),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $machinery->company,
        ]));
    }

    public function storeMachinery(Request $request, User $prestataire): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
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
            DB::transaction(function () use ($prestataire, $validated, $photo, $request, &$storedPhotoPath): void {
                $company = $prestataire->companies()->findOrFail($validated['company_id']);
                $machinery = $company->machinery()->create([
                    ...$this->machineryAttributes($validated),
                    'status' => CompanyMachinery::STATUS_DRAFT,
                ]);

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, 'company-machinery/'.$machinery->getKey().'/photo');
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
                    'user_id' => $request->user()->getKey(),
                    'action' => 'created',
                    'to_status' => CompanyMachinery::STATUS_DRAFT,
                    'reason' => 'Création de la fiche engin par le Gestionnaire pour le compte du Prestataire.',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhoto($storedPhotoPath, $exception);

            throw $exception;
        }

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $validated['company_id']])
            ->with('status', 'La fiche engin a été créée.');
    }

    public function updateMachinery(Request $request, User $prestataire, CompanyMachinery $machinery): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $machinery = $this->ownedMachinery($prestataire, $machinery);
        $this->ensureEditable($machinery);
        $validated = $request->validate($this->machineryInputRules(), $this->machineryInputMessages());
        $photo = $validated['photo'] ?? null;
        unset($validated['photo']);
        $oldPhotoPath = $machinery->photo_path;
        $newPhotoPath = null;

        try {
            DB::transaction(function () use ($machinery, $validated, $photo, $request, &$newPhotoPath): void {
                $lockedMachinery = CompanyMachinery::query()->whereKey($machinery->getKey())->lockForUpdate()->firstOrFail();
                $this->ensureEditable($lockedMachinery);
                $fromStatus = $lockedMachinery->status;
                $attributes = [
                    ...$this->machineryAttributes($validated),
                    'status' => CompanyMachinery::STATUS_DRAFT,
                    'rejection_reason' => null,
                    'validated_by' => null,
                    'validated_at' => null,
                ];

                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $this->storePhoto($photo, 'company-machinery/'.$lockedMachinery->getKey().'/photo');
                    $attributes['photo_path'] = $newPhotoPath;
                } elseif ($request->boolean('remove_photo')) {
                    $attributes['photo_path'] = null;
                }

                $lockedMachinery->update($attributes);
                DocumentWorkflowStatus::synchronize($lockedMachinery);
                $lockedMachinery->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'updated',
                    'from_status' => $fromStatus,
                    'to_status' => $lockedMachinery->status,
                    'reason' => 'Mise à jour de la fiche engin par le Gestionnaire pour le compte du Prestataire.',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhoto($newPhotoPath, $exception);

            throw $exception;
        }

        if (is_string($oldPhotoPath) && $oldPhotoPath !== '' && $oldPhotoPath !== $newPhotoPath && ($newPhotoPath !== null || $request->boolean('remove_photo'))) {
            $this->deletePhotoIfUnused($oldPhotoPath);
        }

        return redirect()->to($this->companyDetailsUrl($prestataire, $machinery))
            ->with('status', 'La fiche engin a été mise à jour.');
    }

    public function createTooling(Request $request, User $prestataire): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $companies = $prestataire->companies()->orderBy('name')->get(['id', 'name']);
        abort_if($companies->isEmpty(), 403, 'Créez d’abord une entreprise avant d’ajouter un outillage.');
        $selectedCompany = $this->requestedCompany($request, $prestataire);

        return view('prestataire.toolings.form', $this->managerViewData([
            'tooling' => null,
            'companies' => $companies,
            'pageTitle' => 'Ajouter un outillage pour '.$prestataire->name,
            'formAction' => route('gestionnaire.delegated.toolings.store', $prestataire),
            'formCancelUrl' => $selectedCompany
                ? $this->companyDetailsUrl($prestataire, $selectedCompany)
                : route('gestionnaire.delegated.show', $prestataire),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $selectedCompany,
        ]));
    }

    public function editTooling(Request $request, User $prestataire, CompanyTooling $tooling): View
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $tooling = $this->ownedTooling($prestataire, $tooling);
        $this->ensureEditable($tooling);

        return view('prestataire.toolings.form', $this->managerViewData([
            'tooling' => $tooling,
            'companies' => collect([$tooling->company]),
            'pageTitle' => 'Modifier l’outillage',
            'formAction' => route('gestionnaire.delegated.toolings.update', [$prestataire, $tooling]),
            'formCancelUrl' => $this->companyDetailsUrl($prestataire, $tooling),
            'photoUrl' => route('gestionnaire.toolings.photo', $tooling),
            'delegatedPrestataire' => $prestataire,
            'contextCompany' => $tooling->company,
        ]));
    }

    public function storeTooling(Request $request, User $prestataire): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
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
            DB::transaction(function () use ($prestataire, $validated, $photo, $request, &$storedPhotoPath): void {
                $company = $prestataire->companies()->findOrFail($validated['company_id']);
                $tooling = $company->toolings()->create([
                    ...$this->toolingAttributes($validated),
                    'status' => CompanyTooling::STATUS_DRAFT,
                ]);

                if ($photo instanceof UploadedFile) {
                    $storedPhotoPath = $this->storePhoto($photo, 'company-toolings/'.$tooling->getKey().'/photo');
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
                    'user_id' => $request->user()->getKey(),
                    'action' => 'created',
                    'to_status' => CompanyTooling::STATUS_DRAFT,
                    'reason' => 'Création de la fiche outillage par le Gestionnaire pour le compte du Prestataire.',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhoto($storedPhotoPath, $exception);

            throw $exception;
        }

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $validated['company_id']])
            ->with('status', 'La fiche outillage a été créée.');
    }

    public function updateTooling(Request $request, User $prestataire, CompanyTooling $tooling): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $tooling = $this->ownedTooling($prestataire, $tooling);
        $this->ensureEditable($tooling);
        $validated = $request->validate($this->toolingInputRules(), $this->toolingInputMessages());
        $photo = $validated['photo'] ?? null;
        unset($validated['photo']);
        $oldPhotoPath = $tooling->photo_path;
        $newPhotoPath = null;

        try {
            DB::transaction(function () use ($tooling, $validated, $photo, $request, &$newPhotoPath): void {
                $lockedTooling = CompanyTooling::query()->whereKey($tooling->getKey())->lockForUpdate()->firstOrFail();
                $this->ensureEditable($lockedTooling);
                $fromStatus = $lockedTooling->status;
                $attributes = [
                    ...$this->toolingAttributes($validated),
                    'status' => CompanyTooling::STATUS_DRAFT,
                    'rejection_reason' => null,
                    'validated_by' => null,
                    'validated_at' => null,
                ];

                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $this->storePhoto($photo, 'company-toolings/'.$lockedTooling->getKey().'/photo');
                    $attributes['photo_path'] = $newPhotoPath;
                } elseif ($request->boolean('remove_photo')) {
                    $attributes['photo_path'] = null;
                }

                $lockedTooling->update($attributes);
                DocumentWorkflowStatus::synchronize($lockedTooling);
                $lockedTooling->history()->create([
                    'user_id' => $request->user()->getKey(),
                    'action' => 'updated',
                    'from_status' => $fromStatus,
                    'to_status' => $lockedTooling->status,
                    'reason' => 'Mise à jour de la fiche outillage par le Gestionnaire pour le compte du Prestataire.',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPhoto($newPhotoPath, $exception);

            throw $exception;
        }

        if (is_string($oldPhotoPath) && $oldPhotoPath !== '' && $oldPhotoPath !== $newPhotoPath && ($newPhotoPath !== null || $request->boolean('remove_photo'))) {
            $this->deletePhotoIfUnused($oldPhotoPath);
        }

        return redirect()->to($this->companyDetailsUrl($prestataire, $tooling))
            ->with('status', 'La fiche outillage a été mise à jour.');
    }

    public function submitCompany(Request $request, User $prestataire, Company $company): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $this->ownedCompany($prestataire, $company);
        DB::transaction(function () use ($company, $request): void {
            $lockedCompany = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($lockedCompany);
            DocumentWorkflowStatus::lockDocuments($lockedCompany);

            if (! $lockedCompany->isReadyForSubmission()) {
                throw ValidationException::withMessages([
                    'company' => 'Complétez les informations obligatoires et téléversez les documents requis avant la soumission.',
                ]);
            }

            $fromStatus = $lockedCompany->status;
            $lockedCompany->update([
                'status' => Company::STATUS_PENDING,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);

            foreach ($lockedCompany->documents as $document) {
                if (
                    filled($document->file_path)
                    && $document->status !== CompanyDocument::STATUS_VALIDATED
                ) {
                    $document->update([
                        'status' => CompanyDocument::STATUS_PENDING,
                        'submitted_at' => now(),
                        'rejection_reason' => null,
                        'validated_by' => null,
                        'validated_at' => null,
                    ]);
                }
            }

            $lockedCompany->reviewHistory()->create([
                'user_id' => $request->user()->getKey(),
                'action' => $fromStatus === Company::STATUS_REJECTED ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => Company::STATUS_PENDING,
                'reason' => 'Soumission de l’entreprise par le Gestionnaire pour le compte du Prestataire.',
            ]);
        });

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $company])
            ->with('status', 'L’entreprise a été soumise pour validation.');
    }

    public function submitEmployee(Request $request, User $prestataire, CompanyEmployee $employee): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $employee = $this->ownedEmployee($prestataire, $employee);
        $manager = $request->user();
        DB::transaction(function () use ($employee, $prestataire, $manager): void {
            $lockedEmployee = CompanyEmployee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($lockedEmployee);
            DocumentWorkflowStatus::lockDocuments($lockedEmployee);

            if (! $lockedEmployee->isReadyForSubmission()) {
                $requiredDocuments = collect(CompanyEmployeeDocument::requiredTypes())
                    ->map(fn (string $type): string => CompanyEmployeeDocument::labelForType($type))
                    ->implode(', ');
                throw ValidationException::withMessages([
                    'employee' => 'Complétez les informations et les documents obligatoires ('.$requiredDocuments.') avant la soumission.',
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
            $lockedEmployee->history()->create([
                'user_id' => $manager->getKey(),
                'action' => $fromStatus === CompanyEmployee::STATUS_REJECTED ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployee::STATUS_PENDING,
                'reason' => 'Soumission de l’employé par le Gestionnaire pour le compte du Prestataire.',
            ]);
            Notification::send(
                User::reviewersForPrestataire($prestataire, 'employees.view')->get(),
                new CompanyEmployeeWorkflowNotification(
                    $lockedEmployee,
                    $fromStatus === CompanyEmployee::STATUS_REJECTED
                        ? CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED
                        : CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
                ),
            );
        });

        return redirect()->to($this->companyDetailsUrl($prestataire, $employee))
            ->with('status', 'L’employé a été soumis pour validation.');
    }

    public function submitMachinery(Request $request, User $prestataire, CompanyMachinery $machinery): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $machinery = $this->ownedMachinery($prestataire, $machinery);
        $manager = $request->user();
        DB::transaction(function () use ($machinery, $prestataire, $manager): void {
            $lockedMachinery = CompanyMachinery::query()->whereKey($machinery->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($lockedMachinery);

            if (DocumentWorkflowStatus::hasRejectedDocument($lockedMachinery)) {
                throw ValidationException::withMessages([
                    'machinery' => 'Corrigez et téléversez les documents rejetés avant de resoumettre l’engin.',
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
            $lockedMachinery->history()->create([
                'user_id' => $manager->getKey(),
                'action' => $fromStatus === CompanyMachinery::STATUS_REJECTED ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachinery::STATUS_PENDING,
                'reason' => 'Soumission de l’engin par le Gestionnaire pour le compte du Prestataire.',
            ]);
            Notification::send(
                User::reviewersForPrestataire($prestataire, 'machinery.view')->get(),
                new CompanyMachineryWorkflowNotification(
                    $lockedMachinery,
                    $fromStatus === CompanyMachinery::STATUS_REJECTED
                        ? CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED
                        : CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED,
                ),
            );
        });

        return redirect()->to($this->companyDetailsUrl($prestataire, $machinery))
            ->with('status', 'L’engin a été soumis pour validation.');
    }

    public function submitTooling(Request $request, User $prestataire, CompanyTooling $tooling): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $tooling = $this->ownedTooling($prestataire, $tooling);
        $manager = $request->user();
        DB::transaction(function () use ($tooling, $prestataire, $manager): void {
            $lockedTooling = CompanyTooling::query()->whereKey($tooling->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($lockedTooling);

            if (DocumentWorkflowStatus::hasRejectedDocument($lockedTooling)) {
                throw ValidationException::withMessages([
                    'tooling' => 'Corrigez et téléversez les documents rejetés avant de resoumettre l’outillage.',
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
            $lockedTooling->history()->create([
                'user_id' => $manager->getKey(),
                'action' => $fromStatus === CompanyTooling::STATUS_REJECTED ? 'resubmitted' : 'submitted',
                'from_status' => $fromStatus,
                'to_status' => CompanyTooling::STATUS_PENDING,
                'reason' => 'Soumission de l’outillage par le Gestionnaire pour le compte du Prestataire.',
            ]);
            Notification::send(
                User::reviewersForPrestataire($prestataire, 'toolings.view')->get(),
                new CompanyToolingWorkflowNotification(
                    $lockedTooling,
                    $fromStatus === CompanyTooling::STATUS_REJECTED
                        ? CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED
                        : CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED,
                ),
            );
        });

        return redirect()->to($this->companyDetailsUrl($prestataire, $tooling))
            ->with('status', 'L’outillage a été soumis pour validation.');
    }

    public function storeCompanyDocument(
        Request $request,
        User $prestataire,
        Company $company,
        string $type,
        CompanyListImportService $importService,
    ): RedirectResponse {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $this->ownedCompany($prestataire, $company);
        abort_unless(in_array($type, CompanyDocument::allTypes(), true), 404);

        $response = $this->saveDocumentUpload($request, $prestataire, $company, 'company', $type);
        $file = $request->file('document');

        if ($file instanceof UploadedFile && $importService->isSpreadsheetUpload($type, $file)) {
            $document = $company->documents()->where('type', $type)->firstOrFail();

            return to_route('gestionnaire.delegated.companies.documents.import-preview', [
                $prestataire,
                $company,
                $document,
            ])->with('status', 'La liste Excel a été déposée. Vérifiez les données avant de confirmer l’importation.');
        }

        return $response;
    }

    public function storeEmployeeDocument(
        Request $request,
        User $prestataire,
        CompanyEmployee $employee,
        string $type,
    ): RedirectResponse {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $employee = $this->ownedEmployee($prestataire, $employee);
        abort_unless(in_array($type, CompanyEmployeeDocument::allTypes(), true), 404);

        return $this->saveDocumentUpload($request, $prestataire, $employee, 'employee', $type);
    }

    public function storeMachineryDocument(
        Request $request,
        User $prestataire,
        CompanyMachinery $machinery,
        string $type,
    ): RedirectResponse {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $machinery = $this->ownedMachinery($prestataire, $machinery);
        abort_unless(in_array($type, CompanyMachineryDocument::allTypes(), true), 404);

        return $this->saveDocumentUpload($request, $prestataire, $machinery, 'machinery', $type);
    }

    public function storeToolingDocument(
        Request $request,
        User $prestataire,
        CompanyTooling $tooling,
        string $type,
    ): RedirectResponse {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $tooling = $this->ownedTooling($prestataire, $tooling);
        abort_unless(
            in_array($type, CompanyToolingDocument::standardTypes(), true)
                || (str_starts_with($type, 'custom_') && $tooling->documents()->where('type', $type)->exists()),
            404,
        );

        return $this->saveDocumentUpload($request, $prestataire, $tooling, 'tooling', $type);
    }

    public function storeCustomToolingDocument(Request $request, User $prestataire, CompanyTooling $tooling): RedirectResponse
    {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $tooling = $this->ownedTooling($prestataire, $tooling);
        $validated = $request->validate([
            ...$this->documentRules(),
            'label' => ['required', 'string', 'max:100'],
        ], $this->documentMessages('tooling'));
        $type = 'custom_'.Str::uuid()->toString();

        return $this->saveDocumentUpload(
            $request,
            $prestataire,
            $tooling,
            'tooling',
            $type,
            trim($validated['label']),
            $validated,
        );
    }

    /**
     * @param  'company'|'employee'|'machinery'|'tooling'  $kind
     * @param  array<string, mixed>|null  $validated
     */
    private function saveDocumentUpload(
        Request $request,
        User $prestataire,
        Company|CompanyEmployee|CompanyMachinery|CompanyTooling $record,
        string $kind,
        string $type,
        ?string $label = null,
        ?array $validated = null,
    ): RedirectResponse {
        $validated ??= $request->validate(
            $this->documentRules($kind, $type),
            $this->documentMessages($kind),
        );
        $file = $request->file('document');
        $extension = $file->guessExtension();

        if (! is_string($extension)) {
            throw new RuntimeException('Le type du fichier n’a pas pu être déterminé.');
        }

        $documentClasses = [
            'company' => CompanyDocument::class,
            'employee' => CompanyEmployeeDocument::class,
            'machinery' => CompanyMachineryDocument::class,
            'tooling' => CompanyToolingDocument::class,
        ];
        $recordReference = match ($kind) {
            'company' => $record->name ?: (string) $record->getKey(),
            'employee' => $record->cin ?: trim($record->first_name.' '.$record->last_name) ?: (string) $record->getKey(),
            'machinery' => $record->registration_number ?: $record->serial_number ?: $record->designation ?: (string) $record->getKey(),
            'tooling' => $record->internal_reference ?: $record->serial_number ?: $record->designation ?: (string) $record->getKey(),
        };
        $fileName = $this->generatedDocumentFileName(
            $file,
            $label ?? $documentClasses[$kind]::labelForType($type),
            $recordReference,
        );
        $directory = match ($kind) {
            'company' => 'companies/'.$record->getKey().'/'.$type,
            'employee' => 'company-employees/'.$record->getKey().'/'.$type,
            'machinery' => 'company-machinery/'.$record->getKey().'/'.$type,
            'tooling' => 'company-toolings/'.$record->getKey().'/'.$type,
        };
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $storedName, 'documents_private');

        if (! is_string($path)) {
            throw new RuntimeException('Le document n’a pas pu être enregistré.');
        }

        $documentClass = $documentClasses[$kind];
        $isReplacement = false;

        try {
            DB::transaction(function () use (
                $kind,
                $record,
                $type,
                $label,
                $file,
                $fileName,
                $path,
                $validated,
                $documentClass,
                $request,
                $prestataire,
                &$isReplacement,
            ): void {
                $lockedRecord = $record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
                $document = $lockedRecord->documents()->where('type', $type)->lockForUpdate()->first();

                if ($document === null) {
                    $document = $lockedRecord->documents()->create([
                        'type' => $type,
                        'label' => $label ?? $documentClass::labelForType($type),
                        'status' => $documentClass::STATUS_DRAFT,
                    ]);
                }

                $fromStatus = $document->status;
                $currentVersion = $document->versions()->where('is_current', true)->lockForUpdate()->first();
                $versionNumber = ($document->versions()->max('version') ?? 0) + 1;
                $documentAttributes = [
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'status' => $documentClass::STATUS_PENDING,
                    'rejection_reason' => null,
                    'submitted_at' => now(),
                    'validated_by' => null,
                    'validated_at' => null,
                ];

                if ($kind === 'company' && $type !== CompanyDocument::TYPE_LISTE_PERSONNEL) {
                    $documentAttributes['expires_at'] = $validated['expires_at'] ?? null;
                } elseif ($kind === 'employee') {
                    $documentAttributes['expires_at'] = $validated['expires_at'] ?? null;
                } elseif (in_array($kind, ['machinery', 'tooling'], true)) {
                    $documentAttributes['issued_at'] = $validated['issued_at'] ?? null;
                    $documentAttributes['expires_at'] = $validated['expires_at'] ?? null;
                }

                $document->update($documentAttributes);
                $currentVersion?->update(['is_current' => false]);
                $version = $document->versions()->create([
                    'version' => $versionNumber,
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $request->user()->getKey(),
                    'uploaded_at' => now(),
                    'notes' => $currentVersion === null
                        ? 'Téléversement initial du document.'
                        : 'Remplacement du document précédent.',
                    'is_current' => true,
                ]);
                $isReplacement = $currentVersion !== null;

                if ($kind === 'company') {
                    $lockedRecord->reviewHistory()->create([
                        'company_document_id' => $document->getKey(),
                        'user_id' => $request->user()->getKey(),
                        'action' => 'document_updated',
                        'from_status' => $fromStatus,
                        'to_status' => CompanyDocument::STATUS_PENDING,
                        'reason' => 'Document déposé ou remplacé par le Gestionnaire pour le compte du Prestataire.',
                        'metadata' => [
                            'document_type' => $type,
                            'file_name' => $fileName,
                            'version' => $version->version,
                        ],
                    ]);
                } else {
                    $historyForeignKey = match ($kind) {
                        'employee' => 'company_employee_document_id',
                        'machinery' => 'company_machinery_document_id',
                        'tooling' => 'company_tooling_document_id',
                    };
                    $historyAction = $isReplacement ? 'document_replaced' : 'document_uploaded';
                    $lockedRecord->history()->create([
                        $historyForeignKey => $document->getKey(),
                        'user_id' => $request->user()->getKey(),
                        'action' => $historyAction,
                        'from_status' => $fromStatus,
                        'to_status' => $documentClass::STATUS_PENDING,
                        'reason' => $isReplacement
                            ? 'Remplacement du document par le Gestionnaire pour le compte du Prestataire.'
                            : 'Ajout du document par le Gestionnaire pour le compte du Prestataire.',
                        'metadata' => [
                            'document_type' => $type,
                            'file_name' => $fileName,
                            'version' => $version->version,
                        ],
                    ]);
                }

                DocumentWorkflowStatus::synchronize($lockedRecord);
                $this->notifyDocumentReviewers($prestataire, $kind, $lockedRecord, $document, $isReplacement);
            });
        } catch (Throwable $exception) {
            $disk = Storage::disk('documents_private');

            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Le document n’a pas pu être supprimé après échec de son enregistrement.', 0, $exception);
            }

            throw $exception;
        }

        return redirect()->to($this->companyDetailsUrl($prestataire, $record))
            ->with('status', $isReplacement
                ? 'Le document a été remplacé et reste en attente de validation.'
                : 'Le document a été déposé et reste en attente de validation.');
    }

    /**
     * @param  'company'|'employee'|'machinery'|'tooling'  $kind
     * @return array<string, array<int, mixed>>
     */
    private function documentRules(string $kind = 'tooling', ?string $type = null): array
    {
        if ($kind === 'company') {
            $isHseDocument = in_array($type, CompanyDocument::hseTypes(), true);
            $allowedMimeTypes = $isHseDocument
                ? ['application/pdf', 'image/jpeg', 'image/png']
                : [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];
            $documentRules = ['required', 'file'];

            if ($isHseDocument) {
                $documentRules[] = 'mimes:pdf,jpg,jpeg,png';
            }

            $documentRules[] = 'mimetypes:'.implode(',', $allowedMimeTypes);
            $documentRules[] = 'max:10240';
            $rules = ['document' => $documentRules];

            if ($type !== CompanyDocument::TYPE_LISTE_PERSONNEL) {
                $rules['expires_at'] = ['nullable', 'date'];
            }

            return $rules;
        }

        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            ...($kind === 'employee' ? [] : ['issued_at' => ['nullable', 'date']]),
            'expires_at' => ['nullable', 'date'],
        ];
    }

    private function notifyDocumentReviewers(
        User $prestataire,
        string $kind,
        Model $record,
        Model $document,
        bool $isReplacement,
    ): void {
        if ($kind === 'company') {
            return;
        }

        $notification = match ($kind) {
            'employee' => new CompanyEmployeeWorkflowNotification(
                $record,
                $isReplacement
                    ? CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED
                    : CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                $document,
            ),
            'machinery' => new CompanyMachineryWorkflowNotification(
                $record,
                $isReplacement
                    ? CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED
                    : CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                $document,
            ),
            'tooling' => new CompanyToolingWorkflowNotification(
                $record,
                $isReplacement
                    ? CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED
                    : CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                $document,
            ),
        };
        $permission = match ($kind) {
            'employee' => 'employees.view',
            'machinery' => 'machinery.view',
            'tooling' => 'toolings.view',
        };

        Notification::send(User::reviewersForPrestataire($prestataire, $permission)->get(), $notification);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function companyRules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'assignment' => ['nullable', 'string', 'max:255'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => [
                'nullable',
                'date',
                Rule::when($request->filled('contract_start_date'), ['after_or_equal:contract_start_date']),
            ],
            'additional_info' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function companyMessages(): array
    {
        return [
            'name.required' => 'Le nom de l’entreprise est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'contract_start_date.date' => 'La date de début du contrat doit être une date valide.',
            'contract_end_date.date' => 'La date de fin du contrat doit être une date valide.',
            'contract_end_date.after_or_equal' => 'La date de fin du contrat doit être supérieure ou égale à la date de début.',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
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

    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<string, array<int, mixed>>
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
     * @return array<string, array<int, mixed>>
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
            'physical_condition' => ['nullable', 'string', Rule::in(['new', 'good', 'average', 'damaged'])],
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
     * @return array<string, string>
     */
    private function documentMessages(string $kind = 'tooling'): array
    {
        $typeLabel = $kind === 'company'
            ? 'Les documents HSE doivent être au format PDF, JPG, JPEG ou PNG.'
            : 'Le fichier doit être au format PDF, JPG, JPEG ou PNG.';

        return [
            'document.required' => 'Veuillez sélectionner un fichier.',
            'document.mimes' => $typeLabel,
            'document.mimetypes' => 'Le type de fichier envoyé n’est pas autorisé.',
            'document.max' => 'Le document ne peut pas dépasser 10 Mo.',
            'label.required' => 'Le nom du document est obligatoire.',
            'label.max' => 'Le nom du document ne peut pas dépasser 100 caractères.',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
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

    private function primaryManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPrimaryManager(), 403);

        return $user;
    }

    private function accessiblePrestataire(Request $request, User $prestataire): User
    {
        return $this->primaryManager($request)
            ->accessiblePrestataires()
            ->whereKey($prestataire->getKey())
            ->firstOrFail();
    }

    private function ownedCompany(User $prestataire, Company $company): Company
    {
        return $prestataire->companies()->whereKey($company->getKey())->firstOrFail();
    }

    private function ownedEmployee(User $prestataire, CompanyEmployee $employee): CompanyEmployee
    {
        return CompanyEmployee::query()
            ->whereKey($employee->getKey())
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->with('company:id,name,prestataire_id')
            ->firstOrFail();
    }

    private function ownedMachinery(User $prestataire, CompanyMachinery $machinery): CompanyMachinery
    {
        return CompanyMachinery::query()
            ->whereKey($machinery->getKey())
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->with('company:id,name,prestataire_id')
            ->firstOrFail();
    }

    private function ownedTooling(User $prestataire, CompanyTooling $tooling): CompanyTooling
    {
        return CompanyTooling::query()
            ->whereKey($tooling->getKey())
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->with('company:id,name,prestataire_id')
            ->firstOrFail();
    }

    private function ensureEditable(Model $record): void
    {
        abort_unless(
            in_array($record->getAttribute('status'), [Company::STATUS_DRAFT, Company::STATUS_REJECTED], true),
            403,
            'Seuls les dossiers en brouillon ou rejetés peuvent être modifiés dans l’espace délégué.',
        );
    }

    private function normalizeCin(Request $request): void
    {
        if ($request->filled('cin')) {
            $request->merge(['cin' => Str::upper(trim((string) $request->input('cin')))]);
        }
    }

    private function storePhoto(UploadedFile $photo, string $directory): string
    {
        $extension = $photo->guessExtension();

        if (! is_string($extension) || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Le format de la photo n’a pas pu être déterminé.');
        }

        $path = $photo->storeAs($directory, Str::uuid()->toString().'.'.$extension, 'documents_private');

        if (! is_string($path)) {
            throw new RuntimeException('La photo n’a pas pu être enregistrée.');
        }

        return $path;
    }

    private function deleteStoredPhoto(?string $path, Throwable $exception): void
    {
        if ($path === null) {
            return;
        }

        $disk = Storage::disk('documents_private');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('La photo temporaire n’a pas pu être supprimée après échec.', 0, $exception);
        }
    }

    private function deletePhotoIfUnused(string $path): void
    {
        $isUsed = CompanyEmployee::query()->where('profile_photo_path', $path)->exists()
            || CompanyMachinery::query()->where('photo_path', $path)->exists()
            || CompanyTooling::query()->where('photo_path', $path)->exists();

        if (! $isUsed) {
            $disk = Storage::disk('documents_private');

            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('L’ancienne photo privée n’a pas pu être supprimée.');
            }
        }
    }

    private function managerViewData(array $data = []): array
    {
        return array_merge([
            'role' => 'gestionnaire',
            'routePrefix' => 'gestionnaire',
            'roleLabel' => 'Gestionnaire',
            'navigation' => DashboardController::navigationForUser(auth()->user()),
        ], $data);
    }

    private function companyDetailsUrl(User $prestataire, Model $record): string
    {
        $companyId = $record instanceof Company
            ? $record->getKey()
            : $record->getAttribute('company_id');

        return route('gestionnaire.delegated.companies.show', [$prestataire, $companyId]);
    }

    private function requestedCompany(Request $request, User $prestataire): ?Company
    {
        $companyId = $request->query('company_id');

        if ($companyId === null) {
            return null;
        }

        abort_unless(
            is_scalar($companyId) && filter_var($companyId, FILTER_VALIDATE_INT) !== false,
            404,
        );

        return $prestataire->companies()->findOrFail((int) $companyId);
    }
}
