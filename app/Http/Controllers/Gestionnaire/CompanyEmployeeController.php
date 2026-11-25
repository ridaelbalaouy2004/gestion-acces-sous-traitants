<?php

namespace App\Http\Controllers\Gestionnaire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyEmployeeDocumentVersion;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyEmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $manager = $this->currentManager($request);
        $accessiblePrestataireIds = $manager->accessiblePrestataires()->pluck('users.id')->all();
        $filters = $request->validate([
            'prestataire_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereIn('id', $accessiblePrestataireIds),
            ],
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->whereIn('prestataire_id', $accessiblePrestataireIds),
            ],
            'status' => ['nullable', 'string', Rule::in($this->statuses())],
            'position' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:100'],
        ], [
            'prestataire_id.exists' => 'Le Prestataire sélectionné est inaccessible.',
            'company_id.exists' => 'L’entreprise sélectionnée est inaccessible.',
        ]);

        $employeeQuery = CompanyEmployee::query()
            ->visibleToManager()
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $accessiblePrestataireIds));
        $employeeCount = (clone $employeeQuery)
            ->when(isset($filters['prestataire_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $filters['prestataire_id']));
            })
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->count();

        $employeeQuery
            ->with(['company:id,name,prestataire_id', 'company.prestataire:id,name'])
            ->when(isset($filters['prestataire_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $filters['prestataire_id']));
            })
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

        $accessibleCompanies = Company::query()
            ->visibleToManager()
            ->whereIn('prestataire_id', $accessiblePrestataireIds);

        return $this->page('gestionnaire.employees.index', [
            'employees' => $employeeQuery->paginate(15)->withQueryString(),
            'employeeCount' => $employeeCount,
            'prestataires' => $manager->accessiblePrestataires()->orderBy('name')->get(['id', 'name']),
            'companies' => $accessibleCompanies->orderBy('name')->get(['id', 'name', 'prestataire_id']),
            'positions' => (clone $employeeQuery)
                ->whereNotNull('position')
                ->where('position', '!=', '')
                ->distinct()
                ->orderBy('position')
                ->pluck('position'),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, CompanyEmployee $employee): View
    {
        $employee = $this->accessibleSubmittedEmployee($request, $employee);
        $employee->load([
            'company:id,name,prestataire_id,status',
            'company.prestataire:id,name',
            'documents' => fn (Relation $query) => $query->visibleToManager(),
            'documents.versions.uploadedBy:id,name',
            'documents.validatedBy:id,name',
            'trainings.versions',
            'history' => fn (Relation $query) => $query->where(function (Builder $historyQuery): void {
                $historyQuery->whereNull('company_employee_document_id')
                    ->orWhereHas('document', fn (Builder $documentQuery) => $documentQuery->visibleToManager());
            }),
            'history.user:id,name',
            'history.document' => fn (Relation $query) => $query->visibleToManager()->select(['id', 'type', 'label']),
            'validatedBy:id,name',
        ]);

        return $this->page('gestionnaire.employees.show', [
            'employee' => $employee,
            'documentsForDisplay' => $employee->documents,
            'requiredDocumentTypes' => CompanyEmployeeDocument::requiredTypes(),
            'optionalDocumentTypes' => CompanyEmployeeDocument::optionalTypes(),
            'approvalBlockers' => $employee->approvalBlockers(),
        ]);
    }

    public function photo(Request $request, CompanyEmployee $employee): StreamedResponse
    {
        $employee = $this->accessibleSubmittedEmployee($request, $employee);

        return $this->streamPrivateImageInline($employee->profile_photo_path);
    }

    public function update(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->accessibleSubmittedEmployee($request, $employee);
        abort_if($request->exists('company_id'), 422, 'Un employé ne peut pas être transféré vers une autre entreprise.');
        $manager = $this->currentManager($request);
        $this->normalizeCin($request);
        $requiredForReviewedEmployee = in_array(
            $employee->status,
            [CompanyEmployee::STATUS_PENDING, CompanyEmployee::STATUS_VALIDATED],
            true,
        ) ? 'required' : 'nullable';
        $validated = $request->validate([
            'first_name' => [$requiredForReviewedEmployee, 'string', 'max:100'],
            'last_name' => [$requiredForReviewedEmployee, 'string', 'max:100'],
            'cin' => [
                $requiredForReviewedEmployee,
                'string',
                'max:50',
                Rule::unique('company_employees', 'cin')
                    ->where('company_id', $employee->company_id)
                    ->ignore($employee->getKey()),
            ],
            'position' => [$requiredForReviewedEmployee, 'string', 'max:255'],
            'locker_number' => ['nullable', 'string', 'max:50'],
        ], [
            'cin.unique' => 'Cette CIN est déjà utilisée dans cette entreprise.',
        ]);

        DB::transaction(function () use ($employee, $validated, $manager): void {
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $before = $lockedEmployee->only(['first_name', 'last_name', 'cin', 'position', 'locker_number']);
            $lockedEmployee->fill([
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'cin' => $validated['cin'] ?? null,
                'position' => $validated['position'] ?? null,
                'locker_number' => $validated['locker_number'] ?? null,
            ]);
            $changed = $lockedEmployee->getDirty();

            if ($changed === []) {
                return;
            }

            $lockedEmployee->save();
            $lockedEmployee->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'information_completed',
                'from_status' => $lockedEmployee->status,
                'to_status' => $lockedEmployee->status,
                'reason' => 'Complément des informations par le Gestionnaire.',
                'metadata' => [
                    'before' => array_intersect_key($before, $changed),
                    'after' => $changed,
                ],
            ]);
        });

        return to_route('gestionnaire.employees.show', $employee)
            ->with('status', 'Les informations de l’employé ont été mises à jour.');
    }

    public function approve(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->accessibleSubmittedEmployee($request, $employee);
        $manager = $this->currentManager($request);

        DB::transaction(function () use ($employee, $manager): void {
            $this->lockValidatedCompany($employee->company_id);
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedEmployee->status === CompanyEmployee::STATUS_PENDING, 409);
            $lockedEmployee->setRelation(
                'documents',
                $lockedEmployee->documents()->lockForUpdate()->get(),
            );
            $approvalBlockers = $lockedEmployee->approvalBlockers();
            abort_if(
                $approvalBlockers !== [],
                409,
                'Approbation impossible. Documents à régulariser : '.collect($approvalBlockers)
                    ->map(fn (array $blocker): string => $blocker['label'].' ('.$blocker['reason'].')')
                    ->implode('; ').'.',
            );

            $fromStatus = $lockedEmployee->status;
            $lockedEmployee->update([
                'status' => CompanyEmployee::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedEmployee->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployee::STATUS_VALIDATED,
                'reason' => 'Validation de l’employé par le Gestionnaire.',
            ]);

            $lockedEmployee->loadMissing('company.prestataire');
            $lockedEmployee->company->prestataire->notify(new CompanyEmployeeWorkflowNotification(
                $lockedEmployee,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_VALIDATED,
            ));
        });

        return to_route('gestionnaire.employees.show', $employee)
            ->with('status', 'L’employé a été validé. Le statut de ses documents reste inchangé.');
    }

    public function reject(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->accessibleSubmittedEmployee($request, $employee);
        $manager = $this->currentManager($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($employee, $manager, $validated): void {
            $this->lockValidatedCompany($employee->company_id);
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedEmployee->status === CompanyEmployee::STATUS_PENDING, 409);
            $fromStatus = $lockedEmployee->status;
            $lockedEmployee->update([
                'status' => CompanyEmployee::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedEmployee->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployee::STATUS_REJECTED,
                'reason' => $validated['reason'],
            ]);

            $lockedEmployee->loadMissing('company.prestataire');
            $lockedEmployee->company->prestataire->notify(new CompanyEmployeeWorkflowNotification(
                $lockedEmployee,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED,
                reason: $validated['reason'],
            ));
        });

        return to_route('gestionnaire.employees.show', $employee)
            ->with('status', 'L’employé a été rejeté.');
    }

    public function approveDocument(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
    ): RedirectResponse {
        [$employee, $document] = $this->accessibleDocument($request, $employee, $document);
        $manager = $this->currentManager($request);

        DB::transaction(function () use ($employee, $document, $manager): void {
            $this->lockValidatedCompany($employee->company_id);
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = CompanyEmployeeDocument::query()
                ->whereKey($document->getKey())
                ->where('company_employee_id', $lockedEmployee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyEmployeeDocument::STATUS_PENDING, 409);
            abort_unless(
                ! $lockedDocument->versions()->where('is_current', true)->where('uploaded_by', $manager->getKey())->exists(),
                409,
                'Vous ne pouvez pas valider un document que vous avez vous-même déposé.',
            );
            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedEmployee->history()->create([
                'company_employee_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                'reason' => 'Validation indépendante du document '.$lockedDocument->label.'.',
            ]);
            DocumentWorkflowStatus::synchronize($lockedEmployee);

            $lockedEmployee->loadMissing('company.prestataire');
            $lockedEmployee->company->prestataire->notify(new CompanyEmployeeWorkflowNotification(
                $lockedEmployee,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                $lockedDocument,
            ));
        });

        return to_route('gestionnaire.employees.show', $employee)
            ->with('status', 'Le document a été validé indépendamment de l’employé et des autres documents.');
    }

    public function approveSelectedDocuments(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        $employee = $this->accessibleEmployee($request, $employee);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('employees.validate'), 403);

        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'document_ids.required' => 'Sélectionnez au moins un document en attente.',
            'document_ids.min' => 'Sélectionnez au moins un document en attente.',
        ]);
        $documentIds = array_values(array_unique(array_map('intval', $validated['document_ids'])));

        $documents = DB::transaction(function () use ($employee, $manager, $documentIds) {
            $this->lockValidatedCompany($employee->company_id);
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $documents = CompanyEmployeeDocument::query()
                ->where('company_employee_id', $lockedEmployee->getKey())
                ->whereIn('id', $documentIds)
                ->lockForUpdate()
                ->get();

            abort_unless(
                $documents->count() === count($documentIds),
                422,
                'Tous les documents sélectionnés doivent appartenir à cet employé.',
            );

            foreach ($documents as $document) {
                abort_unless(
                    $manager->hasPermission('employees.validate'),
                    403,
                    'Vous ne pouvez pas valider tous les documents sélectionnés.',
                );
                abort_unless(
                    $document->status === CompanyEmployeeDocument::STATUS_PENDING,
                    409,
                    'La sélection contient un document qui n’est plus en attente.',
                );
                abort_unless(
                    ! $document->versions()->where('is_current', true)->where('uploaded_by', $manager->getKey())->exists(),
                    409,
                    'Vous ne pouvez pas valider un document que vous avez vous-même déposé.',
                );
            }

            foreach ($documents as $document) {
                $fromStatus = $document->status;
                $document->update([
                    'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                    'rejection_reason' => null,
                    'validated_by' => $manager->getKey(),
                    'validated_at' => now(),
                ]);
                $lockedEmployee->history()->create([
                    'company_employee_document_id' => $document->getKey(),
                    'user_id' => $manager->getKey(),
                    'action' => 'document_validated',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                    'reason' => 'Validation groupée du document '.$document->displayLabel().'.',
                ]);
            }

            DocumentWorkflowStatus::synchronize($lockedEmployee);

            return $documents;
        });

        $employee->refresh();
        $employee->loadMissing('company.prestataire');
        foreach ($documents as $document) {
            $employee->company->prestataire->notify(new CompanyEmployeeWorkflowNotification(
                $employee,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                $document,
            ));
        }

        $count = count($documentIds);

        return to_route('gestionnaire.employees.show', $employee)
            ->with('status', $count.' document'.($count > 1 ? 's validés.' : ' validé.'));
    }

    public function rejectDocument(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
    ): RedirectResponse {
        [$employee, $document] = $this->accessibleDocument($request, $employee, $document);
        $manager = $this->currentManager($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($employee, $document, $manager, $validated): void {
            $this->lockValidatedCompany($employee->company_id);
            $lockedEmployee = CompanyEmployee::query()
                ->whereKey($employee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = CompanyEmployeeDocument::query()
                ->whereKey($document->getKey())
                ->where('company_employee_id', $lockedEmployee->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyEmployeeDocument::STATUS_PENDING, 409);
            $statusBeforeRejection = DocumentWorkflowStatus::statusBeforeDocumentRejection($lockedEmployee);
            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyEmployeeDocument::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedEmployee->history()->create([
                'company_employee_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyEmployeeDocument::STATUS_REJECTED,
                'reason' => $validated['reason'],
                'metadata' => [
                    'dossier_status_before_document_rejection' => $statusBeforeRejection,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedEmployee);

            $lockedEmployee->loadMissing('company.prestataire');
            $lockedEmployee->company->prestataire->notify(new CompanyEmployeeWorkflowNotification(
                $lockedEmployee,
                CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                $lockedDocument,
                $validated['reason'],
            ));
        });

        return to_route('gestionnaire.employees.show', $employee)
            ->with('status', 'Le document a été rejeté et le statut global de l’employé a été actualisé.');
    }

    public function downloadDocument(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
    ): StreamedResponse {
        [$employee, $document] = $this->accessibleDocument($request, $employee, $document);

        return $this->privateDownload($document->file_path, $document->file_name);
    }

    public function previewDocument(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
    ): StreamedResponse {
        [$employee, $document] = $this->accessibleDocument($request, $employee, $document);

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
        CompanyEmployeeDocumentVersion $version,
    ): StreamedResponse {
        [$employee, $document] = $this->accessibleDocument($request, $employee, $document);
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
        CompanyEmployeeDocumentVersion $version,
    ): StreamedResponse {
        [$employee, $document] = $this->accessibleDocument($request, $employee, $document);
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    private function currentManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roles()->where('name', 'gestionnaire')->exists(), 403);

        return $user;
    }

    private function lockValidatedCompany(int $companyId): void
    {
        $company = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
        abort_unless(
            $company->status === Company::STATUS_VALIDATED,
            409,
            "La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.",
        );

    }

    private function accessibleEmployee(Request $request, CompanyEmployee $employee): CompanyEmployee
    {
        $manager = $this->currentManager($request);

        return CompanyEmployee::query()
            ->whereKey($employee->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    private function accessibleSubmittedEmployee(Request $request, CompanyEmployee $employee): CompanyEmployee
    {
        $manager = $this->currentManager($request);

        return CompanyEmployee::query()
            ->visibleToManager()
            ->whereKey($employee->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    /**
     * @return array{CompanyEmployee, CompanyEmployeeDocument}
     */
    private function accessibleDocument(
        Request $request,
        CompanyEmployee $employee,
        CompanyEmployeeDocument $document,
    ): array {
        $employee = $this->accessibleEmployee($request, $employee);
        $document = $employee->documents()->visibleToManager()->findOrFail($document->getKey());

        return [$employee, $document];
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
            'role' => 'gestionnaire',
            'routePrefix' => 'gestionnaire',
            'roleLabel' => 'Gestionnaire',
            'navigation' => DashboardController::navigationForUser(auth()->user()),
        ], $data));
    }
}
