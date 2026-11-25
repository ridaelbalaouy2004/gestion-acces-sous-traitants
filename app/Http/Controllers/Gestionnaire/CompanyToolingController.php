<?php

namespace App\Http\Controllers\Gestionnaire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\CompanyToolingDocumentVersion;
use App\Models\User;
use App\Notifications\CompanyToolingWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyToolingController extends Controller
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
        $accessibleToolings = CompanyTooling::query()
            ->visibleToManager()
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')));
        $toolingCount = (clone $accessibleToolings)
            ->when(isset($filters['prestataire_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('company', fn (Builder $companyQuery) => $companyQuery
                    ->where('prestataire_id', $filters['prestataire_id']));
            })
            ->when(isset($filters['company_id']), fn (Builder $query) => $query->where('company_id', $filters['company_id']))
            ->count();
        $toolingQuery = (clone $accessibleToolings)
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

        return $this->page('gestionnaire.toolings.index', [
            'toolings' => $toolingQuery->paginate(15)->withQueryString(),
            'toolingCount' => $toolingCount,
            'prestataires' => $manager->accessiblePrestataires()->orderBy('name')->get(['id', 'name']),
            'companies' => Company::query()
                ->visibleToManager()
                ->whereIn('prestataire_id', $accessiblePrestataireIds)
                ->orderBy('name')
                ->get(['id', 'name', 'prestataire_id']),
            'categories' => (clone $accessibleToolings)
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, CompanyTooling $tooling): View
    {
        $tooling = $this->accessibleSubmittedTooling($request, $tooling);
        $tooling->load([
            'company:id,name,prestataire_id,status',
            'company.prestataire:id,name',
            'documents' => fn (Relation $query) => $query->visibleToManager(),
            'documents.versions.uploadedBy:id,name',
            'documents.validatedBy:id,name',
            'history' => fn (Relation $query) => $query->where(function (Builder $historyQuery): void {
                $historyQuery->whereNull('company_tooling_document_id')
                    ->orWhereHas('document', fn (Builder $documentQuery) => $documentQuery->visibleToManager());
            }),
            'history.user:id,name',
            'history.document' => fn (Relation $query) => $query->visibleToManager()->select(['id', 'type', 'label']),
            'validatedBy:id,name',
        ]);

        return $this->page('gestionnaire.toolings.show', [
            'tooling' => $tooling,
        ]);
    }

    public function photo(Request $request, CompanyTooling $tooling): StreamedResponse
    {
        $tooling = $this->accessibleSubmittedTooling($request, $tooling);

        return $this->streamPrivateImageInline($tooling->photo_path);
    }

    public function update(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->accessibleSubmittedTooling($request, $tooling);
        $manager = $this->currentManager($request);
        $validated = $request->validate($this->toolingInputRules() + [
            'company_id' => ['prohibited'],
        ]);

        DB::transaction(function () use ($tooling, $validated, $manager): void {
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedTooling->status !== CompanyTooling::STATUS_VALIDATED, 409);
            $before = $lockedTooling->only(array_keys($this->toolingAttributes($validated)));
            $lockedTooling->fill($this->toolingAttributes($validated));
            $changed = $lockedTooling->getDirty();

            if ($changed === []) {
                return;
            }

            $lockedTooling->save();
            $lockedTooling->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'information_completed',
                'from_status' => $lockedTooling->status,
                'to_status' => $lockedTooling->status,
                'reason' => 'Complément des informations par le Gestionnaire.',
                'metadata' => [
                    'before' => array_intersect_key($before, $changed),
                    'after' => $changed,
                ],
            ]);
        });

        return to_route('gestionnaire.toolings.show', $tooling)
            ->with('status', 'Les informations de l’outillage ont été mises à jour.');
    }

    public function approve(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->accessibleSubmittedTooling($request, $tooling);
        $manager = $this->currentManager($request);

        DB::transaction(function () use ($tooling, $manager): void {
            $this->lockValidatedCompany($tooling->company_id);
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedTooling->status === CompanyTooling::STATUS_PENDING, 409);

            $fromStatus = $lockedTooling->status;
            $lockedTooling->update([
                'status' => CompanyTooling::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedTooling->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyTooling::STATUS_VALIDATED,
                'reason' => 'Validation de l’outillage par le Gestionnaire.',
            ]);

            $lockedTooling->loadMissing('company.prestataire');
            $lockedTooling->company->prestataire->notify(new CompanyToolingWorkflowNotification(
                $lockedTooling,
                CompanyToolingWorkflowNotification::EVENT_TOOLING_VALIDATED,
            ));
        });

        return to_route('gestionnaire.toolings.show', $tooling)
            ->with('status', 'L’outillage a été validé. Le statut de ses documents reste inchangé.');
    }

    public function reject(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->accessibleSubmittedTooling($request, $tooling);
        $manager = $this->currentManager($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($tooling, $manager, $validated): void {
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedTooling->status === CompanyTooling::STATUS_PENDING, 409);

            $fromStatus = $lockedTooling->status;
            $lockedTooling->update([
                'status' => CompanyTooling::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedTooling->history()->create([
                'user_id' => $manager->getKey(),
                'action' => 'rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyTooling::STATUS_REJECTED,
                'reason' => $validated['reason'],
            ]);

            $lockedTooling->loadMissing('company.prestataire');
            $lockedTooling->company->prestataire->notify(new CompanyToolingWorkflowNotification(
                $lockedTooling,
                CompanyToolingWorkflowNotification::EVENT_TOOLING_REJECTED,
                reason: $validated['reason'],
            ));
        });

        return to_route('gestionnaire.toolings.show', $tooling)
            ->with('status', 'L’outillage a été rejeté.');
    }

    public function approveDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): RedirectResponse {
        [$tooling, $document] = $this->accessibleDocument($request, $tooling, $document);
        $manager = $this->currentManager($request);

        DB::transaction(function () use ($tooling, $document, $manager): void {
            $this->lockValidatedCompany($tooling->company_id);
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = CompanyToolingDocument::query()
                ->whereKey($document->getKey())
                ->where('company_tooling_id', $lockedTooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyToolingDocument::STATUS_PENDING, 409);
            abort_unless(
                ! $lockedDocument->versions()->where('is_current', true)->where('uploaded_by', $manager->getKey())->exists(),
                409,
                'Vous ne pouvez pas valider un document que vous avez vous-même déposé.',
            );
            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyToolingDocument::STATUS_VALIDATED,
                'rejection_reason' => null,
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedTooling->history()->create([
                'company_tooling_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'from_status' => $fromStatus,
                'to_status' => CompanyToolingDocument::STATUS_VALIDATED,
                'reason' => 'Validation indépendante du document '.$lockedDocument->label.'.',
            ]);
            DocumentWorkflowStatus::synchronize($lockedTooling);

            $lockedTooling->loadMissing('company.prestataire');
            $lockedTooling->company->prestataire->notify(new CompanyToolingWorkflowNotification(
                $lockedTooling,
                CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                $lockedDocument,
            ));
        });

        return to_route('gestionnaire.toolings.show', $tooling)
            ->with('status', 'Le document a été validé indépendamment de l’outillage et des autres documents.');
    }

    public function approveSelectedDocuments(Request $request, CompanyTooling $tooling): RedirectResponse
    {
        $tooling = $this->accessibleTooling($request, $tooling);
        $manager = $this->currentManager($request);
        abort_unless($manager->hasPermission('toolings.approve'), 403);

        $validated = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'document_ids.required' => 'Sélectionnez au moins un document en attente.',
            'document_ids.min' => 'Sélectionnez au moins un document en attente.',
        ]);
        $documentIds = array_values(array_unique(array_map('intval', $validated['document_ids'])));

        $documents = DB::transaction(function () use ($tooling, $manager, $documentIds) {
            $this->lockValidatedCompany($tooling->company_id);
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $documents = CompanyToolingDocument::query()
                ->where('company_tooling_id', $lockedTooling->getKey())
                ->whereIn('id', $documentIds)
                ->lockForUpdate()
                ->get();

            abort_unless(
                $documents->count() === count($documentIds),
                422,
                'Tous les documents sélectionnés doivent appartenir à cet outillage.',
            );

            foreach ($documents as $document) {
                abort_unless(
                    $manager->hasPermission('toolings.approve'),
                    403,
                    'Vous ne pouvez pas valider tous les documents sélectionnés.',
                );
                abort_unless(
                    $document->status === CompanyToolingDocument::STATUS_PENDING,
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
                    'status' => CompanyToolingDocument::STATUS_VALIDATED,
                    'rejection_reason' => null,
                    'validated_by' => $manager->getKey(),
                    'validated_at' => now(),
                ]);
                $lockedTooling->history()->create([
                    'company_tooling_document_id' => $document->getKey(),
                    'user_id' => $manager->getKey(),
                    'action' => 'document_validated',
                    'from_status' => $fromStatus,
                    'to_status' => CompanyToolingDocument::STATUS_VALIDATED,
                    'reason' => 'Validation groupée du document '.$document->label.'.',
                ]);
            }

            DocumentWorkflowStatus::synchronize($lockedTooling);

            return $documents;
        });

        $tooling->refresh();
        $tooling->loadMissing('company.prestataire');
        foreach ($documents as $document) {
            $tooling->company->prestataire->notify(new CompanyToolingWorkflowNotification(
                $tooling,
                CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                $document,
            ));
        }

        $count = count($documentIds);

        return to_route('gestionnaire.toolings.show', $tooling)
            ->with('status', $count.' document'.($count > 1 ? 's validés.' : ' validé.'));
    }

    public function rejectDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): RedirectResponse {
        [$tooling, $document] = $this->accessibleDocument($request, $tooling, $document);
        $manager = $this->currentManager($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères.',
        ]);

        DB::transaction(function () use ($tooling, $document, $manager, $validated): void {
            $this->lockValidatedCompany($tooling->company_id);
            $lockedTooling = CompanyTooling::query()
                ->whereKey($tooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = CompanyToolingDocument::query()
                ->whereKey($document->getKey())
                ->where('company_tooling_id', $lockedTooling->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedDocument->status === CompanyToolingDocument::STATUS_PENDING, 409);
            $statusBeforeRejection = DocumentWorkflowStatus::statusBeforeDocumentRejection($lockedTooling);

            $fromStatus = $lockedDocument->status;
            $lockedDocument->update([
                'status' => CompanyToolingDocument::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'validated_by' => $manager->getKey(),
                'validated_at' => now(),
            ]);
            $lockedTooling->history()->create([
                'company_tooling_document_id' => $lockedDocument->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_rejected',
                'from_status' => $fromStatus,
                'to_status' => CompanyToolingDocument::STATUS_REJECTED,
                'reason' => $validated['reason'],
                'metadata' => [
                    'dossier_status_before_document_rejection' => $statusBeforeRejection,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedTooling);

            $lockedTooling->loadMissing('company.prestataire');
            $lockedTooling->company->prestataire->notify(new CompanyToolingWorkflowNotification(
                $lockedTooling,
                CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                $lockedDocument,
                $validated['reason'],
            ));
        });

        return to_route('gestionnaire.toolings.show', $tooling)
            ->with('status', 'Le document a été rejeté et le statut global de l’outillage a été actualisé.');
    }

    public function downloadDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): StreamedResponse {
        [, $document] = $this->accessibleDocument($request, $tooling, $document);

        return $this->privateDownload($document->file_path, $document->file_name);
    }

    public function previewDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): StreamedResponse {
        [$tooling, $document] = $this->accessibleDocument($request, $tooling, $document);

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
        CompanyToolingDocumentVersion $version,
    ): StreamedResponse {
        [, $document] = $this->accessibleDocument($request, $tooling, $document);
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->privateDownload($version->file_path, $version->file_name);
    }

    public function previewVersion(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
        CompanyToolingDocumentVersion $version,
    ): StreamedResponse {
        [$tooling, $document] = $this->accessibleDocument($request, $tooling, $document);
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

    private function accessibleTooling(Request $request, CompanyTooling $tooling): CompanyTooling
    {
        $manager = $this->currentManager($request);

        return CompanyTooling::query()
            ->whereKey($tooling->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    private function accessibleSubmittedTooling(Request $request, CompanyTooling $tooling): CompanyTooling
    {
        $manager = $this->currentManager($request);

        return CompanyTooling::query()
            ->visibleToManager()
            ->whereKey($tooling->getKey())
            ->whereHas('company', fn (Builder $query) => $query
                ->whereIn('prestataire_id', $manager->accessiblePrestataires()->select('users.id')))
            ->firstOrFail();
    }

    /**
     * @return array{CompanyTooling, CompanyToolingDocument}
     */
    private function accessibleDocument(
        Request $request,
        CompanyTooling $tooling,
        CompanyToolingDocument $document,
    ): array {
        $tooling = $this->accessibleTooling($request, $tooling);
        $document = $tooling->documents()->visibleToManager()->findOrFail($document->getKey());

        return [$tooling, $document];
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

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            CompanyTooling::STATUS_PENDING,
            CompanyTooling::STATUS_VALIDATED,
            CompanyTooling::STATUS_REJECTED,
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
