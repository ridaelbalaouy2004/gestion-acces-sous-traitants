<?php

namespace App\Http\Controllers\Prestataire;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\User;
use App\Services\CompanyListImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $prestataire = $this->currentPrestataire($request);

        $companies = $prestataire->companies()
            ->withCount([
                'documents as required_documents_uploaded_count' => fn (Builder $query) => $query
                    ->whereIn('type', CompanyDocument::requiredTypes())
                    ->whereNotNull('file_path')
                    ->where('file_path', '!=', ''),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return $this->page('prestataire.companies.index', [
            'companies' => $companies,
            'requiredDocumentsTotal' => count(CompanyDocument::requiredTypes()),
        ]);
    }

    public function create(): View
    {
        return $this->page('prestataire.companies.form', [
            'company' => null,
            'documentTypes' => CompanyDocument::allTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
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

        $company = DB::transaction(function () use ($prestataire, $validated): Company {
            $company = $prestataire->companies()->create([
                'name' => $validated['name'],
                'activity' => $validated['activity'] ?? null,
                'assignment' => $validated['assignment'] ?? null,
                'contract_start_date' => $validated['contract_start_date'] ?? null,
                'contract_end_date' => $validated['contract_end_date'] ?? null,
                'additional_info' => $validated['additional_info'] ?? null,
                'status' => Company::STATUS_DRAFT,
            ]);

            foreach (CompanyDocument::requiredTypes() as $type) {
                $company->documents()->create([
                    'type' => $type,
                    'label' => CompanyDocument::labelForType($type),
                    'status' => CompanyDocument::STATUS_DRAFT,
                    'file_name' => null,
                    'file_path' => null,
                ]);
            }

            $company->reviewHistory()->create([
                'user_id' => $prestataire->id,
                'action' => 'created',
                'from_status' => null,
                'to_status' => Company::STATUS_DRAFT,
                'reason' => 'Création de l’entreprise en brouillon.',
            ]);

            return $company;
        });

        return to_route('prestataire.companies.show', $company)
            ->with('status', 'L’entreprise a été créée.');
    }

    public function show(
        Request $request,
        Company $company,
        CompanyListImportService $importService,
    ): View {
        $company = $this->ownedCompany($request, $company);
        $company->load([
            'documents.versions' => fn ($query) => $query->orderByDesc('version'),
            'documents.versions.uploadedBy:id,name',
            'reviewHistory.user:id,name',
        ]);
        $documentsByType = $company->documents->keyBy('type');
        $documentsForDisplay = collect(CompanyDocument::allTypes())
            ->map(fn (string $type): CompanyDocument => $documentsByType->get($type)
                ?? new CompanyDocument([
                    'company_id' => $company->getKey(),
                    'type' => $type,
                    'label' => CompanyDocument::labelForType($type),
                    'status' => CompanyDocument::STATUS_DRAFT,
                ]))
            ->concat($company->documents->reject(
                fn (CompanyDocument $document): bool => in_array($document->type, CompanyDocument::allTypes(), true),
            ));
        $excelDataDocumentIds = $company->documents
            ->filter(fn (CompanyDocument $document): bool => $importService->hasImportedData($company, $document))
            ->modelKeys();

        return $this->page('prestataire.companies.show', [
            'company' => $company,
            'documentTypes' => CompanyDocument::allTypes(),
            'documentsForDisplay' => $documentsForDisplay,
            'excelDataDocumentIds' => $excelDataDocumentIds,
        ]);
    }

    public function edit(Request $request, Company $company): View
    {
        $company = $this->ownedCompany($request, $company);

        return $this->page('prestataire.companies.form', [
            'company' => $company,
            'documentTypes' => CompanyDocument::allTypes(),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company = $this->ownedCompany($request, $company);
        abort_unless(in_array($company->status, [Company::STATUS_DRAFT, Company::STATUS_REJECTED], true), 403, 'Seule une entreprise en brouillon ou rejetée peut être modifiée.');

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

        DB::transaction(function () use ($company, $validated, $request): void {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                in_array($lockedCompany->status, [Company::STATUS_DRAFT, Company::STATUS_REJECTED], true),
                403,
                'Seule une entreprise en brouillon ou rejetée peut être modifiée.',
            );
            $fromStatus = $lockedCompany->status;
            $lockedCompany->update([
                'name' => $validated['name'],
                'activity' => $validated['activity'] ?? null,
                'assignment' => $validated['assignment'] ?? null,
                'contract_start_date' => $validated['contract_start_date'] ?? null,
                'contract_end_date' => $validated['contract_end_date'] ?? null,
                'additional_info' => $validated['additional_info'] ?? null,
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
                'reason' => 'Mise à jour des informations de l’entreprise.',
            ]);
        });

        return to_route('prestataire.companies.show', $company)
            ->with('status', 'Les informations de l’entreprise ont été enregistrées.');
    }

    public function submit(Request $request, Company $company): RedirectResponse
    {
        $company = $this->ownedCompany($request, $company);
        $prestataire = $this->currentPrestataire($request);
        $isReady = DB::transaction(function () use ($company, $prestataire): bool {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                in_array($lockedCompany->status, [Company::STATUS_DRAFT, Company::STATUS_REJECTED], true),
                403,
                'Cette entreprise ne peut pas être soumise dans son état actuel.',
            );
            DocumentWorkflowStatus::lockDocuments($lockedCompany);

            if (! $lockedCompany->isReadyForSubmission()) {
                return false;
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
                if (filled($document->file_path)) {
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
                'user_id' => $prestataire->getKey(),
                'action' => 'submitted',
                'from_status' => $fromStatus,
                'to_status' => Company::STATUS_PENDING,
                'reason' => 'Soumission de l’entreprise pour validation.',
            ]);

            return true;
        });

        if (! $isReady) {
            return back()->withErrors([
                'company' => 'Veuillez compléter les informations obligatoires et téléverser tous les documents requis avant la soumission.',
            ]);
        }

        return to_route('prestataire.companies.show', $company)
            ->with('status', 'Votre entreprise a été soumise pour validation.');
    }

    public function storeDocument(
        Request $request,
        Company $company,
        string $documentType,
        CompanyListImportService $importService,
    ): RedirectResponse {
        $company = $this->ownedCompany($request, $company);
        abort_unless(in_array($documentType, CompanyDocument::allTypes(), true), 404);
        $allowedMimeTypes = in_array($documentType, CompanyDocument::hseTypes(), true)
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

        if (in_array($documentType, CompanyDocument::hseTypes(), true)) {
            $documentRules[] = 'mimes:pdf,jpg,jpeg,png';
        }

        $documentRules[] = 'mimetypes:'.implode(',', $allowedMimeTypes);
        $documentRules[] = 'max:10240';
        $validationRules = [
            'document' => $documentRules,
        ];

        if ($documentType !== CompanyDocument::TYPE_LISTE_PERSONNEL) {
            $validationRules['expires_at'] = ['nullable', 'date'];
        }

        $validated = $request->validate($validationRules, [
            'document.required' => 'Veuillez sélectionner un fichier.',
            'document.mimes' => 'Les documents HSE doivent être au format PDF, JPG, JPEG ou PNG.',
            'document.mimetypes' => 'Le type de fichier envoyé n’est pas autorisé.',
            'document.max' => 'Le document ne peut pas dépasser 10 Mo.',
        ]);

        $file = $request->file('document');
        $isSpreadsheetImport = $importService->isSpreadsheetUpload($documentType, $file);
        $fileName = $this->generatedDocumentFileName(
            $file,
            CompanyDocument::labelForType($documentType),
            $company->name ?: (string) $company->getKey(),
        );
        $extension = $file->guessExtension();

        if (! is_string($extension)) {
            throw new RuntimeException('Le type du fichier n’a pas pu être déterminé.');
        }

        $path = $file->storeAs(
            'companies/'.$company->id.'/'.$documentType,
            Str::uuid()->toString().'.'.$extension,
            'documents_private',
        );

        if (! is_string($path)) {
            throw new RuntimeException('Le fichier n’a pas pu être enregistré.');
        }

        $prestataire = $this->currentPrestataire($request);
        [$document, $fromStatus] = DB::transaction(function () use ($company, $documentType, $file, $fileName, $path, $validated, $prestataire): array {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $document = $lockedCompany->documents()
                ->where('type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                $document = $lockedCompany->documents()->create([
                    'type' => $documentType,
                    'label' => CompanyDocument::labelForType($documentType),
                    'status' => CompanyDocument::STATUS_DRAFT,
                ]);
            }

            $fromStatus = $document->status;
            $documentAttributes = [
                'file_name' => $fileName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'status' => CompanyDocument::STATUS_PENDING,
                'rejection_reason' => null,
                'submitted_at' => now(),
                'validated_by' => null,
                'validated_at' => null,
            ];

            if ($documentType !== CompanyDocument::TYPE_LISTE_PERSONNEL) {
                $documentAttributes['expires_at'] = $validated['expires_at'] ?? null;
            }

            $previousVersion = $document->versions()
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();
            $document->update($documentAttributes);

            if ($previousVersion !== null && $previousVersion->file_path !== null) {
                $previousVersion->update(['is_current' => false]);
            }

            $versionNumber = ($document->versions()->max('version') ?? 0) + 1;
            $document->versions()->create([
                'version' => $versionNumber,
                'file_name' => $fileName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $prestataire->id,
                'uploaded_at' => now(),
                'notes' => 'Téléversement initial ou remplacement du document.',
                'is_current' => true,
            ]);

            DocumentWorkflowStatus::synchronize($lockedCompany);

            return [$document, $fromStatus];
        });

        $company->reviewHistory()->create([
            'company_document_id' => $document->id,
            'user_id' => $this->currentPrestataire($request)->id,
            'action' => 'document_updated',
            'from_status' => $fromStatus,
            'to_status' => CompanyDocument::STATUS_PENDING,
            'reason' => 'Mise à jour du document '.$document->displayLabel().'.',
        ]);

        if ($isSpreadsheetImport) {
            return to_route('prestataire.companies.documents.import-preview', [$company, $document])
                ->with('status', 'La liste Excel a été déposée. Vérifiez les données avant de confirmer l’importation.');
        }

        return to_route('prestataire.companies.show', $company)
            ->with('status', 'Le document a été téléversé avec succès.');
    }

    public function downloadDocument(Request $request, Company $company, CompanyDocument $document): StreamedResponse
    {
        $company = $this->ownedCompany($request, $company);
        $document = $company->documents()->findOrFail($document->id);
        $path = $document->file_path;

        abort_unless(is_string($path) && Storage::disk('documents_private')->exists($path), 404);

        return Storage::disk('documents_private')->download($path, $document->file_name ?: 'document.pdf');
    }

    public function previewDocument(Request $request, Company $company, CompanyDocument $document): StreamedResponse
    {
        $company = $this->ownedCompany($request, $company);
        $document = $company->documents()->findOrFail($document->getKey());

        return $this->streamPrivateDocumentInline($document->file_path, $document->file_name, $document->mime_type);
    }

    public function downloadVersion(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyDocumentVersion $version,
    ): StreamedResponse {
        $company = $this->ownedCompany($request, $company);
        $document = $company->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        abort_unless(is_string($version->file_path) && Storage::disk('documents_private')->exists($version->file_path), 404);

        return Storage::disk('documents_private')->download($version->file_path, $version->file_name ?: 'document.pdf');
    }

    public function previewVersion(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyDocumentVersion $version,
    ): StreamedResponse {
        $company = $this->ownedCompany($request, $company);
        $document = $company->documents()->findOrFail($document->getKey());
        $version = $document->versions()->findOrFail($version->getKey());

        return $this->streamPrivateDocumentInline($version->file_path, $version->file_name, $version->mime_type);
    }

    public function removeDocument(Request $request, Company $company, CompanyDocument $document): RedirectResponse
    {
        $company = $this->ownedCompany($request, $company);
        $prestataire = $this->currentPrestataire($request);

        DB::transaction(function () use ($company, $document, $prestataire): void {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = $lockedCompany->documents()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedDocument->status !== CompanyDocument::STATUS_VALIDATED, 409, 'Un document validé ne peut pas être retiré.');
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
                'status' => CompanyDocument::STATUS_DRAFT,
                'rejection_reason' => null,
                'submitted_at' => null,
                'validated_by' => null,
                'validated_at' => null,
            ]);
            $lockedCompany->reviewHistory()->create([
                'company_document_id' => $lockedDocument->getKey(),
                'user_id' => $prestataire->getKey(),
                'action' => 'document_removed',
                'from_status' => $fromStatus,
                'to_status' => CompanyDocument::STATUS_DRAFT,
                'reason' => 'Retrait du fichier courant; les versions historiques restent conservées.',
                'metadata' => [
                    'file_name' => $fileName,
                    'version' => $currentVersion?->version,
                ],
            ]);
            DocumentWorkflowStatus::synchronize($lockedCompany);
            $this->deletePrivateDocumentFile($filePath);
        });

        return to_route('prestataire.companies.show', $company)
            ->with('status', 'Le fichier courant a été retiré. Les versions historiques restent disponibles.');
    }

    private function currentPrestataire(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->roles()->where('name', 'prestataire')->exists(), 403);

        return $user;
    }

    private function ownedCompany(Request $request, Company $company): Company
    {
        $prestataire = $this->currentPrestataire($request);

        return $prestataire->companies()->whereKey($company->getKey())->firstOrFail();
    }

    private function page(string $view, array $data = []): View
    {
        return view($view, $data + [
            'roleLabel' => 'Prestataire',
            'routePrefix' => 'prestataire',
            'navigation' => DashboardController::navigationForRole('prestataire'),
        ]);
    }
}
