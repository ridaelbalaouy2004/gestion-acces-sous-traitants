<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HseRequiredDocumentsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_prestataire_can_create_and_update_own_company(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire('Prestataire 1');

        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.store'), [
                'name' => 'Société Alpha',
                'activity' => 'Construction',
                'assignment' => 'Chantier A',
                'additional_info' => 'Complémentaire',
            ]);

        $company = Company::query()->where('prestataire_id', $prestataire->id)->firstOrFail();
        $this->assertNotNull($company);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Société Alpha']);
        $this->assertCount(9, $company->documents);
        $this->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Liste exhaustive des effectifs sur site (avec numéro de série de cadenas de consignation)')
            ->assertSee('Cliniques conventionnées')
            ->assertSee('Plan de prévention');

        $this->actingAs($prestataire)
            ->put(route('prestataire.companies.update', $company), [
                'name' => 'Société Alpha Modifiée',
                'activity' => 'Bâtiment',
                'assignment' => 'Chantier B',
                'additional_info' => 'Nouvelles infos',
            ])
            ->assertRedirect(route('prestataire.companies.show', $company));

        $company->refresh();
        $this->assertSame('Société Alpha Modifiée', $company->name);
        $this->assertSame('Bâtiment', $company->activity);

        $this->get(route('prestataire.companies.index'))
            ->assertOk()
            ->assertSee('data-navigate-to="'.route('prestataire.companies.show', $company).'"', false)
            ->assertSee('Consulter');
    }

    public function test_company_contract_dates_can_be_created_updated_and_displayed_to_both_roles(): void
    {
        $prestataire = $this->makePrestataire('Prestataire dates contrat');

        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.store'), [
                'name' => 'Entreprise dates contrat',
                'activity' => 'Construction',
                'assignment' => 'Chantier contrat',
                'contract_start_date' => '2026-01-15',
                'contract_end_date' => '2026-12-31',
            ])
            ->assertRedirect();

        $company = Company::query()->where('prestataire_id', $prestataire->getKey())->sole();
        $company->refresh();
        $this->assertSame('2026-01-15', $company->contract_start_date->format('Y-m-d'));
        $this->assertSame('2026-12-31', $company->contract_end_date->format('Y-m-d'));

        $this->actingAs($prestataire)
            ->put(route('prestataire.companies.update', $company), [
                'name' => 'Entreprise dates contrat',
                'activity' => 'Construction',
                'assignment' => 'Chantier contrat',
                'contract_start_date' => '2026-02-01',
                'contract_end_date' => '2027-01-31',
            ])
            ->assertRedirect(route('prestataire.companies.show', $company));

        $company->refresh();
        $this->assertSame('2026-02-01', $company->contract_start_date->format('Y-m-d'));
        $this->assertSame('2027-01-31', $company->contract_end_date->format('Y-m-d'));

        $manager = $this->makeManager('Gestionnaire dates contrat');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company->update(['status' => Company::STATUS_PENDING]);

        $this->actingAs($manager)
            ->put(route('gestionnaire.companies.update', $company), [
                'name' => 'Entreprise dates contrat',
                'activity' => 'Construction',
                'assignment' => 'Chantier contrat',
                'contract_start_date' => '2026-04-01',
                'contract_end_date' => '2027-03-31',
            ])
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $company->refresh();
        $this->assertSame('2026-04-01', $company->contract_start_date->format('Y-m-d'));
        $this->assertSame('2027-03-31', $company->contract_end_date->format('Y-m-d'));
        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Date de début du contrat')
            ->assertSee('01/04/2026')
            ->assertSee('Date de fin du contrat')
            ->assertSee('31/03/2027');
        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertSee('Date de début du contrat')
            ->assertSee('01/04/2026')
            ->assertSee('Date de fin du contrat')
            ->assertSee('31/03/2027');
    }

    public function test_company_contract_dates_are_optional_for_new_and_existing_companies(): void
    {
        $prestataire = $this->makePrestataire('Prestataire sans dates contrat');

        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.store'), [
                'name' => 'Entreprise sans dates contrat',
                'activity' => 'Construction',
                'assignment' => 'Chantier sans dates',
            ])
            ->assertRedirect();

        $company = Company::query()->where('prestataire_id', $prestataire->getKey())->sole();
        $this->assertDatabaseHas('companies', [
            'id' => $company->getKey(),
            'contract_start_date' => null,
            'contract_end_date' => null,
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Date de début du contrat')
            ->assertSee('Date de fin du contrat')
            ->assertSee('Non renseignée');

        $manager = $this->makeManager('Gestionnaire sans dates contrat');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company->update(['status' => Company::STATUS_PENDING]);
        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertSee('Date de début du contrat')
            ->assertSee('Date de fin du contrat')
            ->assertSee('Non renseignée');
    }

    public function test_company_contract_dates_reject_invalid_dates_and_reversed_periods_with_old_input(): void
    {
        $prestataire = $this->makePrestataire('Prestataire dates invalides');
        $startDateMessage = 'La date de début du contrat doit être une date valide.';
        $endDateMessage = 'La date de fin du contrat doit être une date valide.';
        $periodMessage = 'La date de fin du contrat doit être supérieure ou égale à la date de début.';

        $this->actingAs($prestataire)
            ->followingRedirects()
            ->from(route('prestataire.companies.create'))
            ->post(route('prestataire.companies.store'), [
                'name' => 'Entreprise dates invalides',
                'contract_start_date' => 'date-invalide',
                'contract_end_date' => 'date-invalide',
            ])
            ->assertOk()
            ->assertSee($startDateMessage)
            ->assertSee($endDateMessage)
            ->assertSee('value="date-invalide"', false);
        $this->assertDatabaseMissing('companies', ['name' => 'Entreprise dates invalides']);

        $this->followingRedirects()
            ->from(route('prestataire.companies.create'))
            ->post(route('prestataire.companies.store'), [
                'name' => 'Entreprise période inversée',
                'contract_start_date' => '2026-07-01',
                'contract_end_date' => '2026-06-30',
            ])
            ->assertOk()
            ->assertSee($periodMessage)
            ->assertSee('value="2026-07-01"', false)
            ->assertSee('value="2026-06-30"', false);
        $this->assertDatabaseMissing('companies', ['name' => 'Entreprise période inversée']);

        $company = $this->makeCompany($prestataire, 'Entreprise existante dates invalides', Company::STATUS_PENDING);
        $company->update([
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2026-12-31',
        ]);
        $manager = $this->makeManager('Gestionnaire dates invalides');
        $this->assignPrestataireToManager($manager, $prestataire);

        $this->actingAs($manager)
            ->followingRedirects()
            ->from(route('gestionnaire.companies.show', $company))
            ->put(route('gestionnaire.companies.update', $company), [
                'name' => $company->name,
                'contract_start_date' => '2026-09-01',
                'contract_end_date' => '2026-08-31',
            ])
            ->assertOk()
            ->assertSee($periodMessage)
            ->assertSee('value="2026-09-01"', false)
            ->assertSee('value="2026-08-31"', false);
        $company->refresh();
        $this->assertSame('2026-01-01', $company->contract_start_date->format('Y-m-d'));
        $this->assertSame('2026-12-31', $company->contract_end_date->format('Y-m-d'));
    }

    public function test_prestataire_cannot_access_other_company(): void
    {
        $prestataireA = $this->makePrestataire('Prestataire A');
        $prestataireB = $this->makePrestataire('Prestataire B');
        $companyB = Company::create([
            'prestataire_id' => $prestataireB->id,
            'name' => 'Autre société',
            'activity' => 'Transport',
            'assignment' => 'Zone B',
            'status' => Company::STATUS_DRAFT,
        ]);

        $this->actingAs($prestataireA)
            ->get(route('prestataire.companies.show', $companyB))
            ->assertNotFound();
    }

    public function test_document_upload_is_versioned_and_manager_can_validate_company(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire('Prestataire validation');
        $manager = $this->makeManager('Gestionnaire principal');
        $this->assignPrestataireToManager($manager, $prestataire);

        $company = Company::create([
            'prestataire_id' => $prestataire->id,
            'name' => 'Société Validation',
            'activity' => 'Logistique',
            'assignment' => 'SI',
            'status' => Company::STATUS_DRAFT,
        ]);

        foreach (CompanyDocument::requiredTypes() as $type) {
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_DRAFT,
            ]);
        }

        foreach (CompanyDocument::requiredTypes() as $type) {
            $file = UploadedFile::fake()->create(str_replace('_', '-', $type).'.pdf', 200, 'application/pdf');
            $this->actingAs($prestataire)
                ->post(route('prestataire.companies.documents.store', ['company' => $company, 'documentType' => $type]), [
                    'document' => $file,
                    'expires_at' => now()->addYear()->format('Y-m-d'),
                ])
                ->assertRedirect(route('prestataire.companies.show', $company));
        }

        $document = $company->documents()->where('type', CompanyDocument::TYPE_ASSURANCE_AT)->firstOrFail();
        $this->assertNotNull($document->file_path);

        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.submit', $company))
            ->assertRedirect(route('prestataire.companies.show', $company));

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);

        $this->actingAs($manager);
        foreach (CompanyDocument::requiredTypes() as $type) {
            $document = $company->documents()->where('type', $type)->firstOrFail();
            if ($document->status !== CompanyDocument::STATUS_VALIDATED) {
                $this->post(route('gestionnaire.companies.documents.approve', [$company, $document]))
                    ->assertRedirect(route('gestionnaire.companies.show', $company));
            }
        }

        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $company->refresh();
        $this->assertSame(Company::STATUS_VALIDATED, $company->status);
        $this->assertNotNull($company->validated_at);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->fresh()->status);
        $this->assertDatabaseHas('company_document_versions', ['company_document_id' => $document->id]);
    }

    public function test_personnel_list_is_required_and_only_its_form_omits_expiration(): void
    {
        $this->assertContains(CompanyDocument::TYPE_LISTE_PERSONNEL, CompanyDocument::requiredTypes());
        $this->assertSame(
            'Liste exhaustive des effectifs sur site (avec numéro de série de cadenas de consignation)',
            CompanyDocument::labelForType(CompanyDocument::TYPE_LISTE_PERSONNEL),
        );

        $prestataire = $this->makePrestataire('Prestataire formulaire personnel');
        $company = $this->makeCompany($prestataire, 'Entreprise formulaire personnel', Company::STATUS_DRAFT);
        $html = $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->getContent();

        $personnelFormStart = strpos($html, '<form method="POST" action="'.route('prestataire.companies.documents.store', [
            'company' => $company,
            'documentType' => CompanyDocument::TYPE_LISTE_PERSONNEL,
        ]).'"');
        $this->assertNotFalse($personnelFormStart);
        $personnelFormEnd = strpos($html, '</form>', $personnelFormStart);
        $personnelForm = substr($html, $personnelFormStart, $personnelFormEnd - $personnelFormStart);
        $this->assertStringNotContainsString('name="expires_at"', $personnelForm);

        $assuranceFormStart = strpos($html, '<form method="POST" action="'.route('prestataire.companies.documents.store', [
            'company' => $company,
            'documentType' => CompanyDocument::TYPE_ASSURANCE_AT,
        ]).'"');
        $this->assertNotFalse($assuranceFormStart);
        $assuranceFormEnd = strpos($html, '</form>', $assuranceFormStart);
        $assuranceForm = substr($html, $assuranceFormStart, $assuranceFormEnd - $assuranceFormStart);
        $this->assertStringContainsString('name="expires_at"', $assuranceForm);
    }

    public function test_personnel_list_can_be_replaced_and_approved_without_using_its_old_expiration(): void
    {
        Storage::fake('documents_private');
        $manager = $this->makeManager('Gestionnaire validation liste personnel');
        $prestataire = $this->makePrestataire('Prestataire liste personnel');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise liste personnel', Company::STATUS_DRAFT);
        $company->update(['activity' => 'Construction', 'assignment' => 'Chantier A']);

        foreach (CompanyDocument::requiredTypes() as $type) {
            if ($type !== CompanyDocument::TYPE_LISTE_PERSONNEL) {
                $otherDocument = $this->makeCompanyDocument($company, $type, CompanyDocument::STATUS_VALIDATED);

                if ($type === CompanyDocument::TYPE_ASSURANCE_AT) {
                    $otherDocument->update(['expires_at' => now()->addYear()->toDateString()]);
                }
            }
        }

        $uploadRoute = route('prestataire.companies.documents.store', [
            'company' => $company,
            'documentType' => CompanyDocument::TYPE_LISTE_PERSONNEL,
        ]);
        $this->actingAs($prestataire)
            ->post($uploadRoute, [
                'document' => UploadedFile::fake()->create('effectifs-initial.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('prestataire.companies.show', $company));

        $document = $company->documents()
            ->where('type', CompanyDocument::TYPE_LISTE_PERSONNEL)
            ->firstOrFail();
        $firstVersion = $document->currentVersionData();
        $firstHistory = $company->reviewHistory()
            ->where('company_document_id', $document->getKey())
            ->where('action', 'document_updated')
            ->firstOrFail();
        $oldPath = $firstVersion->file_path;
        $expiredDate = '2000-01-01';
        $document->update(['expires_at' => $expiredDate]);

        $this->post($uploadRoute, [
            'document' => UploadedFile::fake()->create('effectifs-remplacement.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('prestataire.companies.show', $company));

        $document->refresh();
        $this->assertSame($expiredDate, $document->expires_at->format('Y-m-d'));
        $this->assertSame(2, $document->versions()->count());
        $this->assertFalse($firstVersion->fresh()->is_current);
        $this->assertSame($oldPath, $firstVersion->fresh()->file_path);
        Storage::disk('documents_private')->assertExists($oldPath);
        $this->assertDatabaseHas('company_review_histories', [
            'id' => $firstHistory->getKey(),
            'company_document_id' => $document->getKey(),
            'action' => 'document_updated',
        ]);
        $this->assertSame(2, $company->reviewHistory()
            ->where('company_document_id', $document->getKey())
            ->where('action', 'document_updated')
            ->count());

        $company->refresh();
        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.submit', $company))
            ->assertRedirect(route('prestataire.companies.show', $company));
        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);

        foreach ($company->documents()->where('type', '!=', CompanyDocument::TYPE_LISTE_PERSONNEL)->get() as $otherDocument) {
            $this->actingAs($manager)
                ->post(route('gestionnaire.companies.documents.approve', [$company, $otherDocument]))
                ->assertRedirect(route('gestionnaire.companies.show', $company));
        }

        $managerResponse = $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk();
        $managerHtml = $managerResponse->getContent();
        $this->assertSame(count(CompanyDocument::requiredTypes()) - 1, substr_count($managerHtml, 'Date d’expiration'));
        $this->assertStringNotContainsString($document->expires_at->format('d/m/Y'), $managerHtml);
        $this->assertStringNotContainsString('date d’expiration dépassée', $managerHtml);

        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText(
                'Approbation impossible. Documents à régulariser : '
                .CompanyDocument::labelForType(CompanyDocument::TYPE_LISTE_PERSONNEL)
                .' (en attente de validation).',
            );

        $this->post(route('gestionnaire.companies.documents.approve', [$company, $document]))
            ->assertRedirect(route('gestionnaire.companies.show', $company));
        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->fresh()->status);
        $this->assertSame($expiredDate, $document->fresh()->expires_at->format('Y-m-d'));
    }

    public function test_company_cannot_be_approved_without_the_required_personnel_list(): void
    {
        $manager = $this->makeManager('Gestionnaire liste personnel manquante');
        $prestataire = $this->makePrestataire('Prestataire liste personnel manquante');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise liste personnel manquante', Company::STATUS_PENDING);

        foreach (CompanyDocument::requiredTypes() as $type) {
            if ($type !== CompanyDocument::TYPE_LISTE_PERSONNEL) {
                $this->makeCompanyDocument($company, $type, CompanyDocument::STATUS_VALIDATED);
            }
        }

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText(CompanyDocument::labelForType(CompanyDocument::TYPE_LISTE_PERSONNEL))
            ->assertSeeText('document obligatoire manquant');

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
    }

    public function test_new_hse_company_documents_accept_only_pdf_and_images_while_legacy_types_keep_excel_support(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire('Prestataire formats HSE');
        $company = $this->makeCompany($prestataire, 'Entreprise formats HSE', Company::STATUS_DRAFT);

        $this->actingAs($prestataire)
            ->from(route('prestataire.companies.show', $company))
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => CompanyDocument::TYPE_PREVENTION_PLAN,
            ]), [
                'document' => UploadedFile::fake()->create(
                    'plan-prevention.xlsx',
                    100,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ])
            ->assertRedirect(route('prestataire.companies.show', $company))
            ->assertSessionHasErrors('document');

        $this->assertDatabaseMissing('company_documents', [
            'company_id' => $company->getKey(),
            'type' => CompanyDocument::TYPE_PREVENTION_PLAN,
        ]);

        $this->post(route('prestataire.companies.documents.store', [
            'company' => $company,
            'documentType' => CompanyDocument::TYPE_PREVENTION_PLAN,
        ]), [
            'document' => UploadedFile::fake()->create('plan-prevention.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('prestataire.companies.show', $company));

        $hseDocument = $company->documents()->where('type', CompanyDocument::TYPE_PREVENTION_PLAN)->firstOrFail();
        Storage::disk('documents_private')->assertExists($hseDocument->file_path);
        $this->assertMatchesRegularExpression(
            '/^plan-de-prevention-entreprise-formats-hse-\d{8}\.pdf$/',
            $hseDocument->file_name,
        );
        $this->get(route('prestataire.companies.documents.download', [$company, $hseDocument]))
            ->assertOk()
            ->assertDownload($hseDocument->file_name);
        $this->assertDatabaseHas('company_document_versions', [
            'company_document_id' => $hseDocument->getKey(),
            'version' => 1,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $hseDocument->getKey(),
            'action' => 'document_updated',
        ]);

        $uploadResponse = $this->post(route('prestataire.companies.documents.store', [
            'company' => $company,
            'documentType' => CompanyDocument::TYPE_LISTE_MATERIEL,
        ]), [
            'document' => UploadedFile::fake()->create(
                'liste-materiel.xlsx',
                100,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ]);

        $legacyDocument = $company->documents()->where('type', CompanyDocument::TYPE_LISTE_MATERIEL)->firstOrFail();
        $uploadResponse->assertRedirect(route('prestataire.companies.documents.import-preview', [$company, $legacyDocument]));
        Storage::disk('documents_private')->assertExists($legacyDocument->file_path);
        $this->assertMatchesRegularExpression(
            '/^liste-materiel-entreprise-formats-hse-\d{8}\.xlsx$/',
            $legacyDocument->file_name,
        );
    }

    public function test_hse_document_seeder_is_idempotent_and_preserves_existing_document_data(): void
    {
        $prestataire = $this->makePrestataire('Prestataire seeder HSE');
        $company = $this->makeCompany($prestataire, 'Entreprise seeder HSE', Company::STATUS_DRAFT);
        $existingCompanyDocument = $company->documents()->create([
            'type' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            'label' => 'Liste du personnel',
            'status' => CompanyDocument::STATUS_VALIDATED,
            'file_name' => 'effectifs.pdf',
            'file_path' => 'companies/'.$company->getKey().'/effectifs.pdf',
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
        $employee = $company->employees()->create([
            'first_name' => 'Mina',
            'last_name' => 'Test',
            'cin' => 'HZ12345',
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
        $existingEmployeeDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CIN,
            'label' => 'CIN',
            'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
            'file_name' => 'cin.pdf',
            'file_path' => 'company-employees/'.$employee->getKey().'/cin.pdf',
            'expires_at' => now()->addMonth()->toDateString(),
        ]);

        $this->seed(HseRequiredDocumentsSeeder::class);
        $this->seed(HseRequiredDocumentsSeeder::class);

        $existingCompanyDocument->refresh();
        $existingEmployeeDocument->refresh();
        $this->assertSame(
            'Liste exhaustive des effectifs sur site (avec numéro de série de cadenas de consignation)',
            $existingCompanyDocument->label,
        );
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $existingCompanyDocument->status);
        $this->assertSame('effectifs.pdf', $existingCompanyDocument->file_name);
        $this->assertSame(
            'companies/'.$company->getKey().'/effectifs.pdf',
            $existingCompanyDocument->file_path,
        );
        $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $existingEmployeeDocument->status);
        $this->assertSame('cin.pdf', $existingEmployeeDocument->file_name);
        $this->assertSame(
            'company-employees/'.$employee->getKey().'/cin.pdf',
            $existingEmployeeDocument->file_path,
        );
        $this->assertSame(9, $company->documents()->count());
        $this->assertSame(8, $employee->documents()->count());
        $this->assertSame(1, $company->documents()->where('type', CompanyDocument::TYPE_PREVENTION_PLAN)->count());
        $this->assertSame(1, $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_HABILITATIONS)->count());
        $this->assertDatabaseHas('company_documents', [
            'company_id' => $company->getKey(),
            'type' => CompanyDocument::TYPE_PREVENTION_PLAN,
            'status' => CompanyDocument::STATUS_DRAFT,
            'file_path' => null,
        ]);
    }

    public function test_manager_company_filters_work_individually_and_together(): void
    {
        $manager = $this->makeManager('Gestionnaire filtres');
        $prestataireA = $this->makePrestataire('Prestataire Alpha');
        $prestataireB = $this->makePrestataire('Prestataire Beta');
        $this->assignPrestataireToManager($manager, $prestataireA);
        $this->assignPrestataireToManager($manager, $prestataireB);

        $alphaPending = $this->makeCompany($prestataireA, 'Entreprise Alpha en attente', Company::STATUS_PENDING);
        $this->makeCompany($prestataireA, 'Entreprise Alpha validée', Company::STATUS_VALIDATED);
        $betaPending = $this->makeCompany($prestataireB, 'Entreprise Beta en attente', Company::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.index', ['status' => Company::STATUS_PENDING]))
            ->assertOk()
            ->assertSee($alphaPending->name)
            ->assertSee($betaPending->name)
            ->assertDontSee('Entreprise Alpha validée');

        $this->get(route('gestionnaire.companies.index', ['prestataire_id' => $prestataireB->id]))
            ->assertOk()
            ->assertSee($betaPending->name)
            ->assertDontSee($alphaPending->name);

        $this->get(route('gestionnaire.companies.index', ['search' => 'Prestataire Beta']))
            ->assertOk()
            ->assertSee($betaPending->name)
            ->assertDontSee($alphaPending->name);

        $this->get(route('gestionnaire.companies.index', [
            'search' => 'Entreprise Beta en attente',
            'status' => Company::STATUS_PENDING,
            'prestataire_id' => $prestataireB->id,
        ]))
            ->assertOk()
            ->assertSee($betaPending->name)
            ->assertDontSee($alphaPending->name)
            ->assertSee('1 résultat(s)');
    }

    public function test_manager_company_filter_isolation_and_pagination_preserve_filters(): void
    {
        $manager = $this->makeManager('Gestionnaire pagination');
        $assignedPrestataire = $this->makePrestataire('Prestataire assigné pagination');
        $unassignedPrestataire = $this->makePrestataire('Prestataire non assigné');
        $this->assignPrestataireToManager($manager, $assignedPrestataire);

        foreach (range(1, 16) as $index) {
            $this->makeCompany($assignedPrestataire, 'Entreprise pagination '.$index, Company::STATUS_PENDING);
        }
        $privateCompany = $this->makeCompany($unassignedPrestataire, 'Entreprise privée', Company::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.index', [
                'status' => Company::STATUS_PENDING,
                'prestataire_id' => $assignedPrestataire->id,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('16 résultat(s)')
            ->assertDontSee($privateCompany->name)
            ->assertSee('prestataire_id='.$assignedPrestataire->id, false)
            ->assertSee('status='.Company::STATUS_PENDING, false)
            ->assertSee('href="'.route('gestionnaire.companies.index').'"', false);

        $this->get(route('gestionnaire.companies.index', ['prestataire_id' => $unassignedPrestataire->id]))
            ->assertSessionHasErrors('prestataire_id');
    }

    public function test_company_details_show_independent_actions_and_accessible_row_link(): void
    {
        $manager = $this->makeManager('Gestionnaire détail');
        $prestataire = $this->makePrestataire('Prestataire détail');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise à consulter', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.index'))
            ->assertOk()
            ->assertSee('data-navigate-to="'.route('gestionnaire.companies.show', $company).'"', false)
            ->assertSee('tabindex="0"', false)
            ->assertSee('aria-label="Consulter l’entreprise '.$company->name.'"', false);

        $this->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertSee('Informations générales de l’entreprise')
            ->assertSee('Documents de l’entreprise')
            ->assertSee('Valider les informations')
            ->assertSee('Rejeter les informations')
            ->assertSee('Valider ce document')
            ->assertSee('Motif du rejet (obligatoire)')
            ->assertSee($document->label)
            ->assertDontSee('data-navigate-to=', false)
            ->assertSee('data-confirm=', false);
    }

    public function test_company_information_validation_does_not_change_document_statuses(): void
    {
        $manager = $this->makeManager('Gestionnaire validation indépendante');
        $prestataire = $this->makePrestataire('Prestataire validation indépendante');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise validation indépendante', Company::STATUS_PENDING);

        foreach (CompanyDocument::requiredTypes() as $type) {
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_VALIDATED,
                'file_path' => 'companies/'.$company->id.'/'.$type.'.pdf',
                'file_name' => $type.'.pdf',
            ]);
        }

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.approve', $company))
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->assertSame(
            [CompanyDocument::STATUS_VALIDATED],
            $company->documents()->pluck('status')->unique()->all(),
        );
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->id,
            'action' => 'validated',
            'from_status' => Company::STATUS_PENDING,
            'to_status' => Company::STATUS_VALIDATED,
        ]);
    }

    public function test_manager_cannot_approve_a_company_with_a_missing_required_document(): void
    {
        $manager = $this->makeManager('Gestionnaire document manquant');
        $prestataire = $this->makePrestataire('Prestataire document manquant');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise document manquant', Company::STATUS_PENDING);

        foreach (CompanyDocument::requiredTypes() as $type) {
            if ($type !== CompanyDocument::TYPE_ASSURANCE_AT) {
                $this->makeCompanyDocument($company, $type, CompanyDocument::STATUS_VALIDATED);
            }
        }

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertSee('Approbation finale impossible')
            ->assertSee('Assurance AT')
            ->assertSee('document obligatoire manquant')
            ->assertSee('disabled', false);

        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText('Assurance AT')
            ->assertSeeText('document obligatoire manquant');

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
        $this->assertDatabaseMissing('company_review_histories', [
            'company_id' => $company->getKey(),
            'action' => 'validated',
        ]);
    }

    public function test_manager_cannot_approve_a_company_with_a_rejected_required_document(): void
    {
        $manager = $this->makeManager('Gestionnaire document rejeté');
        $prestataire = $this->makePrestataire('Prestataire document rejeté');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise document rejeté', Company::STATUS_PENDING);
        $this->createRequiredCompanyDocuments($company, [
            CompanyDocument::TYPE_ASSURANCE_RC => CompanyDocument::STATUS_REJECTED,
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText('Assurance RC')
            ->assertSeeText('rejeté');

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
    }

    public function test_manager_cannot_approve_a_company_with_a_required_document_pending_review(): void
    {
        $manager = $this->makeManager('Gestionnaire document en attente');
        $prestataire = $this->makePrestataire('Prestataire document en attente');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise document en attente', Company::STATUS_PENDING);
        $this->createRequiredCompanyDocuments($company, [
            CompanyDocument::TYPE_LISTE_PERSONNEL => CompanyDocument::STATUS_PENDING,
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText('Liste du personnel')
            ->assertSeeText('en attente de validation');

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
    }

    public function test_manager_can_approve_company_after_required_and_submitted_optional_documents_are_validated(): void
    {
        $manager = $this->makeManager('Gestionnaire documents complets');
        $prestataire = $this->makePrestataire('Prestataire documents complets');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise documents complets', Company::STATUS_PENDING);
        $this->createRequiredCompanyDocuments($company);
        $expiredRequiredDocument = $company->documents()
            ->where('type', CompanyDocument::TYPE_ASSURANCE_AT)
            ->firstOrFail();
        $expiredRequiredDocument->update(['expires_at' => now()->subDay()->toDateString()]);
        $optionalDocument = $company->documents()->create([
            'type' => 'attestation_optionnelle',
            'label' => 'Attestation facultative déposée',
            'status' => CompanyDocument::STATUS_PENDING,
            'file_name' => 'attestation.pdf',
            'file_path' => 'companies/'.$company->getKey().'/attestation.pdf',
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText('Assurance AT')
            ->assertSeeText('date d’expiration dépassée')
            ->assertSeeText('Attestation facultative déposée')
            ->assertSeeText('en attente de validation');

        $expiredRequiredDocument->update(['expires_at' => now()->addYear()->toDateString()]);
        $optionalDocument->update([
            'status' => CompanyDocument::STATUS_VALIDATED,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertStatus(409)
            ->assertSeeText('Attestation facultative déposée')
            ->assertSeeText('date d’expiration dépassée');

        $optionalDocument->update(['expires_at' => now()->addYear()->toDateString()]);

        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $optionalDocument->fresh()->status);
    }

    public function test_company_information_rejection_requires_a_reason_and_preserves_document_statuses(): void
    {
        $manager = $this->makeManager('Gestionnaire rejet entreprise');
        $prestataire = $this->makePrestataire('Prestataire rejet entreprise');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise à rejeter', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.reject', $company))
            ->assertSessionHasErrors('reason');
        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);

        $this->post(route('gestionnaire.companies.reject', $company), [
            'reason' => 'Les informations doivent être corrigées.',
        ])->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(Company::STATUS_REJECTED, $company->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->fresh()->status);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->id,
            'action' => 'rejected',
            'from_status' => Company::STATUS_PENDING,
            'to_status' => Company::STATUS_REJECTED,
            'reason' => 'Les informations doivent être corrigées.',
        ]);
    }

    public function test_manager_can_decide_each_document_independently_and_rejection_requires_a_reason(): void
    {
        $manager = $this->makeManager('Gestionnaire documents indépendants');
        $prestataire = $this->makePrestataire('Prestataire documents indépendants');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise documents indépendants', Company::STATUS_PENDING);
        $documentToValidate = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);
        $documentToReject = $this->makeCompanyDocument($company, 'assurance_rc', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.approve', [$company, $documentToValidate]))
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $documentToValidate->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $documentToReject->fresh()->status);
        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);

        $this->post(route('gestionnaire.companies.documents.reject', [$company, $documentToReject]))
            ->assertSessionHasErrors('reason');
        $this->assertSame(CompanyDocument::STATUS_PENDING, $documentToReject->fresh()->status);

        $this->post(route('gestionnaire.companies.documents.reject', [$company, $documentToReject]), [
            'reason' => 'Le document est illisible, merci de le remplacer.',
        ])->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(CompanyDocument::STATUS_REJECTED, $documentToReject->fresh()->status);
        $this->assertSame('Le document est illisible, merci de le remplacer.', $documentToReject->fresh()->rejection_reason);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $documentToValidate->fresh()->status);
        $this->assertSame(Company::STATUS_REJECTED, $company->fresh()->status);
        $this->assertNull($company->fresh()->rejection_reason);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->id,
            'company_document_id' => $documentToReject->id,
            'action' => 'document_rejected',
            'from_status' => CompanyDocument::STATUS_PENDING,
            'to_status' => CompanyDocument::STATUS_REJECTED,
            'reason' => 'Le document est illisible, merci de le remplacer.',
        ]);

        $documentToValidate->update(['rejection_reason' => 'Ancien motif d’un document validé.']);
        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('data-document-rejection-reason="'.$documentToReject->getKey().'"', false)
            ->assertSee('Le document est illisible, merci de le remplacer.')
            ->assertDontSee('data-document-rejection-reason="'.$documentToValidate->getKey().'"', false);

        $documentToValidate->update(['status' => CompanyDocument::STATUS_PENDING]);
        $this->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertDontSee('data-document-rejection-reason="'.$documentToValidate->getKey().'"', false);

        $documentToReject->update(['rejection_reason' => null]);
        $this->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('data-document-rejection-reason="'.$documentToReject->getKey().'"', false)
            ->assertSee('Aucun motif de rejet renseigné');

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.approve', [$company, $documentToValidate]))
            ->assertStatus(409);
        $this->assertDatabaseCount('company_review_histories', 2);
    }

    public function test_document_rejection_updates_only_its_company_and_resubmission_returns_it_to_pending(): void
    {
        Storage::fake('documents_private');
        $manager = $this->makeManager('Gestionnaire propagation rejet', ['entreprises.view', 'entreprises.reject']);
        $prestataire = $this->makePrestataire('Prestataire propagation rejet');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise rejet propagé', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, CompanyDocument::TYPE_ASSURANCE_AT, CompanyDocument::STATUS_PENDING);
        $this->makeCompanyDocument($company, CompanyDocument::TYPE_ASSURANCE_RC, CompanyDocument::STATUS_PENDING);
        $otherCompany = $this->makeCompany($prestataire, 'Entreprise sans rejet', Company::STATUS_PENDING);
        $otherDocument = $this->makeCompanyDocument($otherCompany, CompanyDocument::TYPE_ASSURANCE_AT, CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.reject', [$company, $document]), [
                'reason' => 'Le document doit être remplacé.',
            ])
            ->assertRedirect(route('gestionnaire.companies.show', $company));

        $this->assertSame(Company::STATUS_REJECTED, $company->fresh()->status);
        $this->assertSame(Company::STATUS_PENDING, $otherCompany->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $otherDocument->fresh()->status);
        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Rejeté');
        $this->get(route('prestataire.companies.index'))
            ->assertOk()
            ->assertSee('Rejeté');

        $this->post(route('prestataire.companies.documents.store', [$company, $document->type]), [
            'document' => UploadedFile::fake()->create('assurance-at-corrige.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('prestataire.companies.show', $company));

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->fresh()->status);
        $this->assertSame(Company::STATUS_PENDING, $otherCompany->fresh()->status);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $document->getKey(),
            'action' => 'document_rejected',
        ]);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $document->getKey(),
            'action' => 'document_updated',
        ]);
    }

    public function test_manager_can_bulk_validate_pending_documents_of_the_same_company(): void
    {
        $manager = $this->makeManager('Gestionnaire validation groupée');
        $prestataire = $this->makePrestataire('Prestataire validation groupée');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise validation groupée', Company::STATUS_PENDING);
        $firstDocument = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);
        $secondDocument = $this->makeCompanyDocument($company, 'assurance_rc', CompanyDocument::STATUS_PENDING);

        $response = $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk();
        $html = $response->getContent();
        $buttonPosition = strpos($html, 'Valider la sélection (0)');
        $this->assertGreaterThan(
            max(strpos($html, $firstDocument->displayLabel()), strpos($html, $secondDocument->displayLabel())),
            $buttonPosition,
        );

        $this->post(route('gestionnaire.companies.documents.approve-selected', $company), [
            'document_ids' => [$firstDocument->getKey(), $secondDocument->getKey()],
        ])
            ->assertRedirect(route('gestionnaire.companies.show', $company))
            ->assertSessionHas('status');

        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $firstDocument->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $secondDocument->fresh()->status);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $firstDocument->getKey(),
            'action' => 'document_validated',
            'to_status' => CompanyDocument::STATUS_VALIDATED,
        ]);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $secondDocument->getKey(),
            'action' => 'document_validated',
            'to_status' => CompanyDocument::STATUS_VALIDATED,
        ]);
    }

    public function test_bulk_validation_rejects_documents_from_another_company_without_partial_validation(): void
    {
        $manager = $this->makeManager('Gestionnaire sélection entre entreprises');
        $prestataire = $this->makePrestataire('Prestataire sélection entre entreprises');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise sélection principale', Company::STATUS_PENDING);
        $otherCompany = $this->makeCompany($prestataire, 'Entreprise sélection étrangère', Company::STATUS_PENDING);
        $firstDocument = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);
        $foreignDocument = $this->makeCompanyDocument($otherCompany, 'assurance_rc', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.approve-selected', $company), [
                'document_ids' => [$firstDocument->getKey(), $foreignDocument->getKey()],
            ])
            ->assertStatus(422);

        $this->assertSame(CompanyDocument::STATUS_PENDING, $firstDocument->fresh()->status);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $foreignDocument->fresh()->status);
        $this->assertDatabaseMissing('company_review_histories', [
            'company_document_id' => $firstDocument->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_bulk_validation_rejects_changed_statuses_and_changes_nothing_on_failure(): void
    {
        $manager = $this->makeManager('Gestionnaire sélection statuts');
        $prestataire = $this->makePrestataire('Prestataire sélection statuts');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise sélection statuts', Company::STATUS_PENDING);

        foreach ([CompanyDocument::STATUS_DRAFT, CompanyDocument::STATUS_VALIDATED, CompanyDocument::STATUS_REJECTED] as $status) {
            $company->documents()->create([
                'type' => 'status_'.$status,
                'label' => 'Document '.$status,
                'status' => $status,
                'file_name' => 'document-'.$status.'.pdf',
                'file_path' => 'companies/'.$company->getKey().'/document-'.$status.'.pdf',
            ]);
        }
        $pendingDocument = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);

        foreach ($company->documents()->whereIn('status', [
            CompanyDocument::STATUS_DRAFT,
            CompanyDocument::STATUS_VALIDATED,
            CompanyDocument::STATUS_REJECTED,
        ])->get() as $changedDocument) {
            $this->actingAs($manager)
                ->post(route('gestionnaire.companies.documents.approve-selected', $company), [
                    'document_ids' => [$pendingDocument->getKey(), $changedDocument->getKey()],
                ])
                ->assertStatus(409);

            $this->assertSame(CompanyDocument::STATUS_PENDING, $pendingDocument->fresh()->status);
            $this->assertDatabaseMissing('company_review_histories', [
                'company_document_id' => $pendingDocument->getKey(),
                'action' => 'document_validated',
            ]);
        }
    }

    public function test_manager_without_validation_permission_cannot_bulk_validate_documents(): void
    {
        $manager = $this->makeManager('Gestionnaire sans validation groupée', [
            'entreprises.view',
            'entreprises.download',
        ]);
        $prestataire = $this->makePrestataire('Prestataire validation refusée');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise validation refusée', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.approve-selected', $company), [
                'document_ids' => [$document->getKey()],
            ])
            ->assertForbidden();

        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->fresh()->status);
    }

    public function test_company_document_version_download_is_private_and_scoped(): void
    {
        Storage::fake('documents_private');
        $manager = $this->makeManager('Gestionnaire versions');
        $prestataire = $this->makePrestataire('Prestataire versions');
        $unassignedPrestataire = $this->makePrestataire('Prestataire autre versions');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise versions', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);
        $versionPath = 'companies/'.$company->id.'/document-v1.pdf';
        Storage::disk('documents_private')->put($versionPath, 'contenu privé');
        $version = $document->versions()->create([
            'version' => 1,
            'file_name' => 'document-v1.pdf',
            'file_path' => $versionPath,
            'uploaded_by' => $prestataire->id,
            'is_current' => false,
        ]);
        $otherCompany = $this->makeCompany($unassignedPrestataire, 'Entreprise non affectée', Company::STATUS_PENDING);
        $otherDocument = $this->makeCompanyDocument($otherCompany, 'assurance_at', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.documents.versions.download', [$company, $document, $version]))
            ->assertDownload('document-v1.pdf');

        $this->get(route('gestionnaire.companies.documents.versions.download', [$otherCompany, $otherDocument, $version]))
            ->assertNotFound();
    }

    public function test_employee_manager_without_update_permission_cannot_see_or_use_company_decisions(): void
    {
        $manager = $this->makeManager('Gestionnaire consultation seule', ['entreprises.view']);
        $prestataire = $this->makePrestataire('Prestataire consultation seule');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise consultation seule', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertDontSee('Valider les informations')
            ->assertDontSee('Valider ce document');

        $this->post(route('gestionnaire.companies.documents.approve', [$company, $document]))
            ->assertForbidden();
    }

    public function test_manager_cannot_open_an_unassigned_company_from_its_detail_url(): void
    {
        $manager = $this->makeManager('Gestionnaire affectation');
        $prestataire = $this->makePrestataire('Prestataire non affecté');
        $company = $this->makeCompany($prestataire, 'Entreprise inaccessible', Company::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertNotFound();
    }

    public function test_prestataire_can_replace_a_rejected_document_and_resubmit_it(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire('Prestataire remplacement document');
        $company = $this->makeCompany($prestataire, 'Entreprise remplacement document', Company::STATUS_PENDING);
        $document = $this->makeCompanyDocument($company, 'assurance_at', CompanyDocument::STATUS_REJECTED);
        $oldPath = 'companies/'.$company->id.'/assurance_at/ancienne-version.pdf';
        Storage::disk('documents_private')->put($oldPath, 'ancienne version');
        $oldVersion = $document->versions()->create([
            'version' => 1,
            'file_name' => 'ancienne-version.pdf',
            'file_path' => $oldPath,
            'uploaded_by' => $prestataire->id,
            'is_current' => true,
        ]);
        $document->update(['file_path' => $oldPath, 'file_name' => $oldVersion->file_name]);

        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => $document->type,
            ]), [
                'document' => UploadedFile::fake()->create('nouvelle-version.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('prestataire.companies.show', $company));

        $document->refresh();
        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->status);
        $this->assertNull($document->rejection_reason);
        $this->assertSame(2, $document->versions()->count());
        $this->assertFalse($oldVersion->fresh()->is_current);
        $this->assertTrue($document->currentVersionData()->is_current);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->id,
            'company_document_id' => $document->id,
            'action' => 'document_updated',
            'from_status' => CompanyDocument::STATUS_REJECTED,
            'to_status' => CompanyDocument::STATUS_PENDING,
        ]);
        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertDontSee('data-document-rejection-reason="'.$document->getKey().'"', false);
    }

    protected function makePrestataire(string $name): User
    {
        $prestataire = User::factory()->create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test']);
        $role = Role::query()->where('name', 'prestataire')->firstOrFail();
        $prestataire->roles()->syncWithoutDetaching($role->id);

        return $prestataire;
    }

    protected function makeManager(string $name, array $permissions = [
        'entreprises.view',
        'entreprises.edit',
        'entreprises.validate',
        'entreprises.reject',
        'entreprises.download',
    ]): User
    {
        $manager = User::factory()->create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test']);
        $role = Role::query()->where('name', 'gestionnaire')->firstOrFail();
        $manager->roles()->syncWithoutDetaching($role->id);
        $manager->permissions()->syncWithoutDetaching(Permission::query()->whereIn('name', $permissions)->pluck('id'));

        return $manager;
    }

    protected function makeCompany(User $prestataire, string $name, string $status): Company
    {
        return Company::query()->create([
            'prestataire_id' => $prestataire->getKey(),
            'name' => $name,
            'status' => $status,
        ]);
    }

    protected function makeCompanyDocument(Company $company, string $type, string $status): CompanyDocument
    {
        return $company->documents()->create([
            'type' => $type,
            'label' => CompanyDocument::labelForType($type),
            'status' => $status,
            'file_name' => 'document-'.$type.'.pdf',
            'file_path' => 'companies/'.$company->getKey().'/document-'.$type.'.pdf',
        ]);
    }

    /**
     * @param  array<string, string>  $statuses
     */
    private function createRequiredCompanyDocuments(Company $company, array $statuses = []): void
    {
        foreach (CompanyDocument::requiredTypes() as $type) {
            $this->makeCompanyDocument(
                $company,
                $type,
                $statuses[$type] ?? CompanyDocument::STATUS_VALIDATED,
            );
        }
    }

    protected function assignPrestataireToManager(User $manager, User $prestataire): void
    {
        $manager->assignedPrestataires()->syncWithoutDetaching([
            $prestataire->id => ['assigned_by' => $manager->id],
        ]);
    }
}
