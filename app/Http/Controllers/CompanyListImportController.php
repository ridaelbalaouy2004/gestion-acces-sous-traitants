<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\User;
use App\Services\CompanyListImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyListImportController extends Controller
{
    public function providerPreview(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $company = $this->providerCompany($request, $company);
        $document = $company->documents()->findOrFail($document->getKey());

        return $this->previewView(
            $request,
            $company,
            $document,
            $importService,
            route('prestataire.companies.documents.import-confirm', [$company, $document]),
            route('prestataire.companies.show', $company),
        );
    }

    public function providerData(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $company = $this->providerCompany($request, $company);
        $document = $company->documents()->findOrFail($document->getKey());
        $data = $importService->importedData($company, $document);
        abort_if($data === null, 404);

        return view('company-list-import.data-modal-content', ['data' => $data]);
    }

    public function delegatedPreview(
        Request $request,
        User $prestataire,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $prestataire->companies()->whereKey($company->getKey())->firstOrFail();
        $document = $company->documents()->findOrFail($document->getKey());

        return $this->previewView(
            $request,
            $company,
            $document,
            $importService,
            route('gestionnaire.delegated.companies.documents.import-confirm', [$prestataire, $company, $document]),
            route('gestionnaire.delegated.companies.show', [$prestataire, $company]),
        );
    }

    public function providerConfirm(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $company = $this->providerCompany($request, $company);
        $document = $company->documents()->findOrFail($document->getKey());

        return $this->confirm($request, $company, $document, $importService);
    }

    public function delegatedConfirm(
        Request $request,
        User $prestataire,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $prestataire = $this->accessiblePrestataire($request, $prestataire);
        $company = $prestataire->companies()->whereKey($company->getKey())->firstOrFail();
        $document = $company->documents()->findOrFail($document->getKey());

        return $this->confirm($request, $company, $document, $importService);
    }

    private function providerCompany(Request $request, Company $company): Company
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user->companies()->whereKey($company->getKey())->firstOrFail();
    }

    private function accessiblePrestataire(Request $request, User $prestataire): User
    {
        $manager = $request->user();

        abort_unless($manager instanceof User && $manager->isPrimaryManager(), 403);

        return $manager->accessiblePrestataires()
            ->whereKey($prestataire->getKey())
            ->firstOrFail();
    }

    private function previewView(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
        string $confirmUrl,
        string $cancelUrl,
    ): View {
        $mapping = $request->session()->getOldInput('mapping');
        $preview = $importService->preview($company, $document, is_array($mapping) ? $mapping : null);

        return view('company-list-import.preview', $this->authenticatedPageData($request, [
            'company' => $company,
            'document' => $document,
            'preview' => $preview,
            'confirmUrl' => $confirmUrl,
            'cancelUrl' => $cancelUrl,
            'selectedRows' => $request->session()->getOldInput('selected_rows'),
        ]));
    }

    private function confirm(
        Request $request,
        Company $company,
        CompanyDocument $document,
        CompanyListImportService $importService,
    ): View {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['refresh', 'import'])],
            'source_hash' => ['required', 'string', 'size:64'],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:3'],
            'selected_rows' => ['sometimes', 'array', 'min:1'],
            'selected_rows.*' => ['integer', 'min:1'],
        ]);

        if ($validated['action'] === 'refresh') {
            $preview = $importService->preview($company, $document, $validated['mapping']);

            return view('company-list-import.preview', $this->authenticatedPageData($request, [
                'company' => $company,
                'document' => $document,
                'preview' => $preview,
                'confirmUrl' => $request->url(),
                'cancelUrl' => $this->cancelUrl($request, $company),
                'selectedRows' => $validated['selected_rows'] ?? [],
            ]));
        }

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $summary = $importService->import(
            $company,
            $document,
            $actor,
            $validated['mapping'],
            $validated['selected_rows'] ?? [],
            $validated['source_hash'],
        );

        return view('company-list-import.result', $this->authenticatedPageData($request, [
            'company' => $company,
            'document' => $document,
            'summary' => $summary,
            'cancelUrl' => $this->cancelUrl($request, $company),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function authenticatedPageData(Request $request, array $data): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $isProvider = $user->roles()->where('name', 'prestataire')->exists();

        return $data + [
            'roleLabel' => $isProvider ? 'Prestataire' : 'Gestionnaire',
            'routePrefix' => $isProvider ? 'prestataire' : 'gestionnaire',
            'navigation' => $isProvider
                ? DashboardController::navigationForRole('prestataire')
                : DashboardController::navigationForUser($user),
        ];
    }

    private function cancelUrl(Request $request, Company $company): string
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user->roles()->where('name', 'prestataire')->exists()
            ? route('prestataire.companies.show', $company)
            : route('gestionnaire.delegated.companies.show', [$company->prestataire, $company]);
    }
}
