<?php

namespace App\Http\Controllers\Gestionnaire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyMachineryDocumentVersion;
use App\Models\User;
use App\Notifications\CompanyMachineryWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyMachineryController extends Controller
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
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ], [
            'prestataire_id.exists' => 'Le Prestataire sélectionné est inaccessible.',
            'company_id.exists' => 'L’entreprise sélectionnée est inaccessible.',
        ]);
        $machineryQuery = CompanyMachinery::query()
            ->visibleToManager()
            ->whereHas('company', fn (Builder $query) => $query->whereIn('prestataire_id', $accessiblePrestataireIds));
        $machineryCount = (clone $machineryQuery)
            ->when(isset($filters['prestataire_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $filters['prestataire_id']));
            })
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->count();

        $machineryQuery
            ->with(['company:id,name,prestataire_id', 'company.prestataire:id,name'])
            ->when(isset($filters['prestataire_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $filters['prestataire_id']));
            })
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

        return $this->page('gestionnaire.machinery.index', [
            'machineryItems' => $machineryQuery->paginate(15)->withQueryString(),
            'machineryCount' => $machineryCount,
            'prestataires' => $manager->accessiblePrestataires()->orderBy('name')->get(['id', 'name']),
            'companies' => Company::query()
                ->visibleToManager()
                ->whereIn('prestataire_id', $accessiblePrestataireIds)
                ->orderBy('name')
                ->get(['id', 'name', 'prestataire_id']),
            'categories' => (clone $machineryQuery)
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, CompanyMachinery $machinery): View
    {
        $machinery = $this->accessibleSubmittedMachinery($request, $machinery);
        $machinery->load([
            'company:id,name,prestataire_id,status',
            'company.prestataire:id,name',
            'documents' => fn (Relation $query) => $query->visibleToManager(),
            'documents.versions.uploadedBy:id,name',
            'documents.validatedBy:id,name',
            'history' => fn (Relation $query) => $query->where(function (Builder $historyQuery): void {
                $historyQuery->whereNull('company_machinery_document_id')
                    ->orWhereHas('document', fn (Builder $documentQuery) => $documentQuery->visibleToManager());
            }),
            'history.user:id,name',
            'history.document' => fn (Relation $query) => $query->visibleToManager()->select(['id', 'type', 'label']),
            'validatedBy:id,name',
        ]);

        return $this->page('gestionnaire.machinery.show', [
            'machinery' => $machinery,
        ]);
    }

    public function photo(Request $request, CompanyMachinery $machinery): StreamedResponse
    {
        $machinery = $this->accessibleSubmittedMachinery($request, $machinery);

        return $this->streamPrivateImageInline($machinery->photo_path);
    }

    public function update(Request $request, CompanyMachinery $machinery): RedirectResponse
    {
        $machinery = $this->accessibleSubmittedMachinery($request, $machinery);
        $manager = $this->currentManager($request);
        $validated = $request->validate($this->machineryInputRules() + [
            'company_id' => ['prohibited'],
        ]);

        DB::transaction(function () use ($machinery, $validated, $manager): void {
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $before = $lockedMachinery->only(array_keys($this->machineryAttributes($validated)));
            $lockedMachinery->fill($this->machineryAttributes($validated));
            $changed = $lockedMachinery->getDirty();

            if ($changed === []) {
                return;
            }

            $lockedMachinery->save();
            $lockedMachinery->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'information_completed',
                'from_status' => $lockedMachinery->status,
                'to_status' => $lockedMachinery->status,
                'reason' => 'Complément des informations par le Gestionnaire.',
                'metadata' => [
                    'before' => array_intersect_key($before, $changed),
                    'after' => $changed,
                ],
            ]);
        });

        return to_route('gestionnaire.machinery.show', $machinery)
            ->with('status', 'Les informations de l’engin ont été mises à jour.');
    }

    public function approve(Request $request, CompanyMachinery $machinery): RedirectResponse
    {
        $machinery = $this->accessibleSubmittedMachinery($request, $machinery);
        $manager = $this->currentManager($request);

        DB::transaction(function () use ($machinery, $manager): void {
            $this->lockValidatedCompany($machinery->company_id);
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedMachinery->status === CompanyMachinery::STATUS_PENDING, 409);
            $fromStatus = $lockedMachinery->status;
            $lockedMachinery->update([
                'status' => CompanyMachinery::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedMachinery->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachinery::STATUS_VALIDATED,
                'reason' => 'Validation de l’engin par le Gestionnaire.',
            ]);

            $lockedMachinery->loadMissing('company.prestataire');
            $lockedMachinery->company->prestataire->notify(new CompanyMachineryWorkflowNotification(
                $lockedMachinery,
                CompanyMachineryWorkflowNotification::EVENT_MACHINERY_VALIDATED,
            ));
        });

        return to_route('gestionnaire.machinery.show', $machinery)
            ->with('status', 'L’engin a été validé. Le statut de ses documents reste inchangé.');
    }

    public function reject(Request $request, CompanyMachinery $machinery): RedirectResponse
    {
        $machinery = $this->accessibleSubmittedMachinery($request, $machinery);
        $manager = $this->currentManager($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($machinery, $manager, $validated): void {
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedMachinery->status === CompanyMachinery::STATUS_PENDING, 409);
            $fromStatus = $lockedMachinery->status;
            $lockedMachinery->update([
                'status' => CompanyMachinery::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedMachinery->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachinery::STATUS_REJECTED,
                'reason' => $validated['reason'],
            ]);

            $lockedMachinery->loadMissing('company.prestataire');
            $lockedMachinery->company->prestataire->notify(new CompanyMachineryWorkflowNotification(
                $lockedMachinery,
                CompanyMachineryWorkflowNotification::EVENT_MACHINERY_REJECTED,
                reason: $validated['reason'],
            ));
        });

        return to_route('gestionnaire.machinery.show', $machinery)
            ->with('status', 'L’engin a été rejeté.');
    }

    public function approveDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): RedirectResponse {
        [$machinery, $document] = $this->accessibleDocument($request, $machinery, $document);
        $manager = $this->currentManager($request);

        DB::transaction(function () use ($machinery, $document, $manager): void {
            $this->lockValidatedCompany($machinery->company_id);
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = CompanyMachineryDocument::query()
                ->whereKey($document->getKey())
                ->where('company_machinery_id', $lockedMachinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyMachineryDocument::STATUS_PENDING, 409);
            abort_unless(
                ! $lockedDocument->versions()->where('is_current', true)->where('uploaded_by', $manager->getKey())->exists(),
                409,
                'Vous ne pouvez pas valider un document que vous avez vous-même déposé.',
            );
            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyMachineryDocument::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedMachinery->history()->create([
                'company_machinery_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachineryDocument::STATUS_VALIDATED,
                'reason' => 'Validation indépendante du document '.$lockedDocument->label.'.',
            ]);
            DocumentWorkflowStatus::synchronize($lockedMachinery);

            $lockedMachinery->loadMissing('company.prestataire');
            $lockedMachinery->company->prestataire->notify(new CompanyMachineryWorkflowNotification(
                $lockedMachinery,
                CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                $lockedDocument,
            ));
        });

        return to_route('gestionnaire.machinery.show', $machinery)
            ->with('status', 'Le document a été validé indépendamment de l’engin et des autres documents.');
    }

    public function approveSelectedDocuments(Request $request, CompanyMachinery $machinery): RedirectResponse
    {
        $machinery = $this->accessibleMachinery($request, $machinery);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('machinery.approve'), 403);

        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'document_ids.required' => 'Sélectionnez au moins un document en attente.',
            'document_ids.min' => 'Sélectionnez au moins un document en attente.',
        ]);
        $documentIds = array_values(array_unique(array_map('intval', $validated['document_ids'])));

        $documents = DB::transaction(function () use ($machinery, $manager, $documentIds) {
            $this->lockValidatedCompany($machinery->company_id);
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $documents = CompanyMachineryDocument::query()
                ->where('company_machinery_id', $lockedMachinery->getKey())
                ->whereIn('id', $documentIds)
                ->lockForUpdate()
                ->get();

            abort_unless(
                $documents->count() === count($documentIds),
                422,
                'Tous les documents sélectionnés doivent appartenir à cet engin.',
            );

            foreach ($documents as $document) {
                abort_unless(
                    $manager->hasPermission('machinery.approve'),
                    403,
                    'Vous ne pouvez pas valider tous les documents sélectionnés.',
                );
                abort_unless(
                    $document->status === CompanyMachineryDocument::STATUS_PENDING,
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
                    'status' => CompanyMachineryDocument::STATUS_VALIDATED,
                    'rejection_reason' => null,
                    'validated_by' => $manager->getKey(),
                    'validated_at' => now(),
                ]);
                $lockedMachinery->history()->create([
                    'company_machinery_document_id' => $document->getKey(),
                    'user_id' => $manager->getKey(),
                    'action' => 'document_validated',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyMachineryDocument::STATUS_VALIDATED,
                    'reason' => 'Validation groupée du document '.$document->label.'.',
                ]);
            }

            DocumentWorkflowStatus::synchronize($lockedMachinery);

            return $documents;
        });

        $machinery->refresh();
        $machinery->loadMissing('company.prestataire');
        foreach ($documents as $document) {
            $machinery->company->prestataire->notify(new CompanyMachineryWorkflowNotification(
                $machinery,
                CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                $document,
            ));
        }

        $count = count($documentIds);

        return to_route('gestionnaire.machinery.show', $machinery)
            ->with('status', $count.' document'.($count > 1 ? 's validés.' : ' validé.'));
    }

    public function rejectDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): RedirectResponse {
        [$machinery, $document] = $this->accessibleDocument($request, $machinery, $document);
        $manager = $this->currentManager($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($machinery, $document, $manager, $validated): void {
            $this->lockValidatedCompany($machinery->company_id);
            $lockedMachinery = CompanyMachinery::query()
                ->whereKey($machinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = CompanyMachineryDocument::query()
                ->whereKey($document->getKey())
                ->where('company_machinery_id', $lockedMachinery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyMachineryDocument::STATUS_PENDING, 409);
            $statusBeforeRejection = DocumentWorkflowStatus::statusBeforeDocumentRejection($lockedMachinery);
            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyMachineryDocument::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedMachinery->history()->create([
                'company_machinery_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyMachineryDocument::STATUS_REJECTED,
                'reason' => $validated['reason'],
                'metadata' => [
                    'dossier_status_before_document_rejection' => $statusBeforeRejection,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedMachinery);

            $lockedMachinery->loadMissing('company.prestataire');
            $lockedMachinery->company->prestataire->notify(new CompanyMachineryWorkflowNotification(
                $lockedMachinery,
                CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                $lockedDocument,
                $validated['reason'],
            ));
        });

        return to_route('gestionnaire.machinery.show', $machinery)
            ->with('status', 'Le document a été rejeté et le statut global de l’engin a été actualisé.');
    }

    public function downloadDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): StreamedResponse {
        [, $document] = $this->accessibleDocument($request, $machinery, $document);

        return $this->privateDownload($document->file_path, $document->file_name);
    }

    public function previewDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): StreamedResponse {
        [$machinery, $document] = $this->accessibleDocument($request, $machinery, $document);

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
        CompanyMachineryDocumentVersion $version,
    ): StreamedResponse {
        [, $document] = $this->accessibleDocument($request, $machinery, $document);
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
        CompanyMachineryDocumentVersion $version,
    ): StreamedResponse {
        [$machinery, $document] = $this->accessibleDocument($request, $machinery, $document);
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

    private function accessibleMachinery(Request $request, CompanyMachinery $machinery): CompanyMachinery
    {
        $manager = $this->currentManager($request);

        return CompanyMachinery::query()
            ->whereKey($machinery->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    private function accessibleSubmittedMachinery(Request $request, CompanyMachinery $machinery): CompanyMachinery
    {
        $manager = $this->currentManager($request);

        return CompanyMachinery::query()
            ->visibleToManager()
            ->whereKey($machinery->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    /**
     * @return array{CompanyMachinery, CompanyMachineryDocument}
     */
    private function accessibleDocument(
        Request $request,
        CompanyMachinery $machinery,
        CompanyMachineryDocument $document,
    ): array {
        $machinery = $this->accessibleMachinery($request, $machinery);
        $document = $machinery->documents()->visibleToManager()->findOrFail($document->getKey());

        return [$machinery, $document];
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
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
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
            'role' => 'gestionnaire',
            'routePrefix' => 'gestionnaire',
            'roleLabel' => 'Gestionnaire',
            'navigation' => DashboardController::navigationForUser(auth()->user()),
        ], $data));
    }
}
