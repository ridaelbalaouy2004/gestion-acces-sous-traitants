<?php

namespace App\Http\Controllers\Gestionnaire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\User;
use App\Services\CompanyListImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $manager = $this->currentManager($request);
        $accessiblePrestataireIds = $manager->accessiblePrestataires()->pluck('users.id')->all();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in([Company::STATUS_PENDING, Company::STATUS_VALIDATED, Company::STATUS_REJECTED])],
            'prestataire_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('id', $accessiblePrestataireIds)],
        ], [
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'prestataire_id.exists' => 'Le prestataire sélectionné est invalide.',
        ]);

        $companies = Company::query()
            ->visibleToManager()
            ->whereHas('prestataire', fn (Builder $query) => $query->whereIn('users.id', $manager->accessiblePrestataires()->select('users.id')))
            ->with('prestataire:id,name')
            ->withCount([
                'documents as required_documents_uploaded_count' => fn (Builder $query) => $query
                    ->whereIn('type', CompanyDocument::requiredTypes())
                    ->whereNotNull('file_path')
                    ->where('file_path', '!=', ''),
            ])
            ->when(isset($filters['search']) && $filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery->where('name', 'like', $term)
                        ->orWhereHas('prestataire', fn (Builder $prestataireQuery) => $prestataireQuery->where('name', 'like', $term));
                });
            })
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['prestataire_id']), fn (Builder $query) => $query->where('prestataire_id', $filters['prestataire_id']))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('gestionnaire.companies.index', $this->managerViewData([
            'companies' => $companies,
            'filters' => $filters,
            'prestataires' => $manager->accessiblePrestataires()->orderBy('name')->get(['id', 'name']),
            'requiredDocumentsTotal' => count(CompanyDocument::requiredTypes()),
        ]));
    }

    public function show(
        Request $request,
        Company $company,
        CompanyListImportService $importService,
    ): View {
        $company = $this->accessibleSubmittedCompany($request, $company);
        $company->load([
            'documents' => fn (Relation $query) => $query->visibleToManager(),
            'documents.versions.uploadedBy:id,name',
            'documents.reviewHistory.user:id,name',
            'documents.validatedBy:id,name',
            'reviewHistory' => fn (Relation $query) => $query->where(function (Builder $historyQuery): void {
                $historyQuery->whereNull('company_document_id')
                    ->orWhereHas('document', fn (Builder $documentQuery) => $documentQuery->visibleToManager());
            }),
            'reviewHistory.document' => fn (Relation $query) => $query->visibleToManager()->select(['id', 'label']),
            'reviewHistory.user:id,name',
            'validatedBy:id,name',
            'prestataire:id,name',
        ]);
        $manager = $this->currentManager($request);
        $excelDataDocumentIds = $manager->hasPermission('entreprises.download')
            ? $company->documents
                ->filter(fn (CompanyDocument $document): bool => $importService->hasImportedData($company, $document))
                ->modelKeys()
            : [];

        return view('gestionnaire.companies.show', $this->managerViewData([
            'company' => $company,
            'documentsForDisplay' => $company->documents,
            'excelDataDocumentIds' => $excelDataDocumentIds,
            'approvalBlockers' => $company->approvalBlockers(),
        ]));
    }

    public function importedDocumentData(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        abort_unless($this->currentManager($request)->hasPermission('entreprises.download'), 403);
        [$company, $document] = $this->accessibleDocument($request, $company, $document);
        $data = $importService->importedData($company, $document);
        abort_if($data === null, 404);

        return view('company-list-import.data-modal-content', ['data' => $data]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company = $this->accessibleSubmittedCompany($request, $company);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('entreprises.edit'), 403);

        $validated = $request->validate([
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
        ], [
            'name.required' => 'Le nom de l’entreprise est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'contract_start_date.date' => 'La date de début du contrat doit être une date valide.',
            'contract_end_date.date' => 'La date de fin du contrat doit être une date valide.',
            'contract_end_date.after_or_equal' => 'La date de fin du contrat doit être supérieure ou égale à la date de début.',
        ]);

        DB::transaction(function () use ($company, $manager, $validated): void {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedCompany->status === Company::STATUS_PENDING, 409, 'Seule une entreprise en attente peut être complétée par le Gestionnaire.');

            $lockedCompany->update($validated);
            $lockedCompany->reviewHistory()->create([
                'user_id' => $manager->getKey(),
                'action' => 'information_updated',
                'from_status' => Company::STATUS_PENDING,
                'to_status' => Company::STATUS_PENDING,
                'reason' => 'Informations de l’entreprise complétées ou corrigées par le Gestionnaire.',
            ]);
        });

        return to_route('gestionnaire.companies.show', $company)
            ->with('status', 'Les informations de l’entreprise ont été mises à jour.');
    }

    public function approve(Request $request, Company $company): RedirectResponse
    {
        $company = $this->accessibleSubmittedCompany($request, $company);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('entreprises.validate'), 403);

        $this->approveCompanyWithValidatedDocuments($company, $manager);

        return to_route('gestionnaire.companies.show', $company)
            ->with('status', 'Les informations de l’entreprise ont été validées. Le statut de ses documents reste inchangé.');
    }

    public function approveDelegated(Request $request, User $prestataire, Company $company): RedirectResponse
    {
        $manager = $this->currentManager($request);
        abort_unless($manager->isPrimaryManager(), 403);
        abort_unless($manager->hasPermission('entreprises.validate'), 403);

        $prestataire = $manager->accessiblePrestataires()
            ->whereKey($prestataire->getKey())
            ->firstOrFail();
        abort_unless((int) $company->prestataire_id === (int) $prestataire->getKey(), 404);

        $this->approveCompanyWithValidatedDocuments($company, $manager, $prestataire->getKey());

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $company])
            ->with('status', 'Les informations de l’entreprise ont été validées. Le statut de ses documents reste inchangé.');
    }

    private function approveCompanyWithValidatedDocuments(
        Company $company,
        User $manager,
        ?int $delegatedPrestataireId = null,
    ): void {
        DB::transaction(function () use ($company, $manager, $delegatedPrestataireId): void {
            $lockedCompany = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            if ($delegatedPrestataireId !== null) {
                abort_unless(
                    $manager->isPrimaryManager()
                        && (int) $lockedCompany->prestataire_id === $delegatedPrestataireId,
                    404,
                );
            }
            abort_unless($lockedCompany->status === Company::STATUS_PENDING, 409);
            $lockedCompany->setRelation(
                'documents',
                $lockedCompany->documents()->lockForUpdate()->get(),
            );
            $approvalBlockers = $lockedCompany->approvalBlockers();
            abort_if(
                $approvalBlockers !== [],
                409,
                'Approbation impossible. Documents à régulariser : '.collect($approvalBlockers)
                    ->map(fn (array $blocker): string => $blocker['label'].' ('.$blocker['reason'].')')
                    ->implode('; ').'.',
            );

            $fromStatus = $lockedCompany->status;
            $lockedCompany->update([
                'status' => Company::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedCompany->reviewHistory()->create([
                'user_id' => $manager->getKey(),
                'action' => 'validated',
                'from_status' => $fromStatus,
                'to_status' => Company::STATUS_VALIDATED,
                'reason' => 'Validation des informations de l’entreprise par le Gestionnaire.',
            ]);
        });
    }

    public function reject(Request $request, Company $company): RedirectResponse
    {
        $company = $this->accessibleSubmittedCompany($request, $company);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('entreprises.reject'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif de rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($company, $manager, $validated): void {
            $lockedCompany = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($lockedCompany->status === Company::STATUS_PENDING, 409);

            $fromStatus = $lockedCompany->status;
            $lockedCompany->update([
                'status' => Company::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedCompany->reviewHistory()->create([
                'user_id' => $manager->getKey(),
                'action' => 'rejected',
                'from_status' => $fromStatus,
                'to_status' => Company::STATUS_REJECTED,
                'reason' => $validated['reason'],
            ]);
        });

        return to_route('gestionnaire.companies.show', $company)
            ->with('status', 'Les informations de l’entreprise ont été rejetées. Le statut de ses documents reste inchangé.');
    }

    public function approveDocument(Request $request, Company $company, CompanyDocument $document): RedirectResponse
    {
        [$company, $document] = $this->accessibleDocument($request, $company, $document);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('entreprises.validate'), 403);

        DB::transaction(function () use ($company, $document, $manager): void {
            $lockedCompany = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $lockedDocument = CompanyDocument::query()
                ->whereKey($document->getKey())
                ->where('company_id', $lockedCompany->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyDocument::STATUS_PENDING, 409);
            abort_unless(
                ! $lockedDocument->versions()->where('is_current', true)->where('uploaded_by', $manager->getKey())->exists(),
                409,
                'Vous ne pouvez pas valider un document que vous avez vous-même déposé.',
            );

            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyDocument::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedCompany->reviewHistory()->create([
                'company_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyDocument::STATUS_VALIDATED,
                'reason' => 'Validation indépendante du document '.$lockedDocument->label.'.',
            ]);
            DocumentWorkflowStatus::synchronize($lockedCompany);
        });

        return to_route('gestionnaire.companies.show', $company)
            ->with('status', 'Le document a été validé indépendamment des autres documents et des informations de l’entreprise.');
    }

    public function approveSelectedDocuments(Request $request, Company $company): RedirectResponse
    {
        $company = $this->accessibleCompany($request, $company);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('entreprises.validate'), 403);

        $count = $this->validateSelectedDocuments($request, $company, $manager);

        return to_route('gestionnaire.companies.show', $company)
            ->with('status', $count.' document'.($count > 1 ? 's validés.' : ' validé.'));
    }

    public function approveSelectedDelegatedDocuments(
        Request $request,
        User $prestataire,
        Company $company,
    ): RedirectResponse {
        $manager = $this->currentManager($request);
        abort_unless($manager->isPrimaryManager(), 403);

        $prestataire = $manager->accessiblePrestataires()
            ->whereKey($prestataire->getKey())
            ->firstOrFail();
        $company = $this->accessibleCompany($request, $company);
        abort_unless((int) $company->prestataire_id === (int) $prestataire->getKey(), 404);
        abort_unless($manager->hasPermission('entreprises.validate'), 403);

        $count = $this->validateSelectedDocuments(
            $request,
            $company,
            $manager,
            (int) $prestataire->getKey(),
        );

        return to_route('gestionnaire.delegated.companies.show', [$prestataire, $company])
            ->with('status', $count.' document'.($count > 1 ? 's validés.' : ' validé.'));
    }

    private function validateSelectedDocuments(
        Request $request,
        Company $company,
        User $manager,
        ?int $delegatedPrestataireId = null,
    ): int {
        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'document_ids.required' => 'Sélectionnez au moins un document en attente.',
            'document_ids.min' => 'Sélectionnez au moins un document en attente.',
        ]);

        $documentIds = array_values(array_unique(array_map('intval', $validated['document_ids'])));

        DB::transaction(function () use ($company, $manager, $documentIds, $delegatedPrestataireId): void {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($delegatedPrestataireId !== null) {
                abort_unless($manager->isPrimaryManager(), 403);
                abort_unless((int) $lockedCompany->prestataire_id === $delegatedPrestataireId, 404);
            }

            $documents = CompanyDocument::query()
                ->where('company_id', $lockedCompany->getKey())
                ->whereIn('id', $documentIds)
                ->lockForUpdate()
                ->get();

            abort_unless(
                $documents->count() === count($documentIds),
                422,
                'Tous les documents sélectionnés doivent appartenir à cette entreprise.',
            );

            foreach ($documents as $document) {
                abort_unless(
                    $document->status === CompanyDocument::STATUS_PENDING,
                    409,
                    'La sélection contient un document qui n’est plus en attente.',
                );
                abort_unless(
                    $manager->isPrimaryManager()
                        || ! $document->versions()->where('is_current', true)->where('uploaded_by', $manager->getKey())->exists(),
                    409,
                    'Vous ne pouvez pas valider un document que vous avez vous-même déposé.',
                );
                abort_unless(
                    $manager->hasPermission('entreprises.validate'),
                    403,
                    'Vous ne pouvez pas valider tous les documents sélectionnés.',
                );
            }

            foreach ($documents as $document) {
                $fromStatus = $document->status;
                $document->update([
                    'status' => CompanyDocument::STATUS_VALIDATED,
                    'rejection_reason' => null,
                    'validated_by' => $manager->getKey(),
                    'validated_at' => now(),
                ]);

                $lockedCompany->reviewHistory()->create([
                    'company_document_id' => $document->getKey(),
                    'user_id' => $manager->getKey(),
                    'action' => 'document_validated',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyDocument::STATUS_VALIDATED,
                    'reason' => 'Validation groupée du document '.$document->displayLabel().'.',
                ]);
            }

            DocumentWorkflowStatus::synchronize($lockedCompany);
        });

        return count($documentIds);
    }

    public function rejectDocument(Request $request, Company $company, CompanyDocument $document): RedirectResponse
    {
        [$company, $document] = $this->accessibleDocument($request, $company, $document);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('entreprises.reject'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif de rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($company, $document, $manager, $validated): void {
            $lockedCompany = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $lockedDocument = CompanyDocument::query()
                ->whereKey($document->getKey())
                ->where('company_id', $lockedCompany->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyDocument::STATUS_PENDING, 409);

            $statusBeforeRejection = DocumentWorkflowStatus::statusBeforeDocumentRejection($lockedCompany);
            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyDocument::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedCompany->reviewHistory()->create([
                'company_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyDocument::STATUS_REJECTED,
                'reason' => $validated['reason'],
                'metadata' => [
                    'dossier_status_before_document_rejection' => $statusBeforeRejection,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedCompany);
        });

        return to_route('gestionnaire.companies.show', $company)
            ->with('status', 'Le document a été rejeté.');
    }

    public function downloadDocument(Request $request, Company $company, CompanyDocument $document): StreamedResponse
    {
        [, $document] = $this->accessibleDocument($request, $company, $document);

        return $this->privateDownload($document->file_path, $document->file_name ?: 'document.pdf');
    }

    public function previewDocument(Request $request, Company $company, CompanyDocument $document): StreamedResponse
    {
        [$company, $document] = $this->accessibleDocument($request, $company, $document);

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyDocumentVersion $version,
    ): StreamedResponse {
        [$company, $document] = $this->accessibleDocument($request, $company, $document);
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyDocumentVersion $version,
    ): StreamedResponse {
        [$company, $document] = $this->accessibleDocument($request, $company, $document);
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    private function currentManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roles()->where('name', 'gestionnaire')->exists(), 403);

        return $user;
    }

    private function accessibleCompany(Request $request, Company $company): Company
    {
        $manager = $this->currentManager($request);

        return Company::query()
            ->whereKey($company->getKey())
            ->whereHas('prestataire', fn (Builder $query) => $query->whereIn('users.id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    private function accessibleSubmittedCompany(Request $request, Company $company): Company
    {
        $company = $this->accessibleCompany($request, $company);
        abort_if($company->status === Company::STATUS_DRAFT, 404);

        return $company;
    }

    /**
     * @return array{Company, CompanyDocument}
     */
    private function accessibleDocument(Request $request, Company $company, CompanyDocument $document): array
    {
        $company = $this->accessibleCompany($request, $company);
        $document = $company->documents()->visibleToManager()->findOrFail($document->getKey());

        return [$company, $document];
    }

    private function privateDownload(?string $path, string $fileName): StreamedResponse
    {
        abort_unless(is_string($path) && Storage::disk('documents_private')->exists($path), 404);

        return Storage::disk('documents_private')->download($path, $fileName);
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
}
