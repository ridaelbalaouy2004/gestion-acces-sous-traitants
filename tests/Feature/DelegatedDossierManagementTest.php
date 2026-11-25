<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\ManagerSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DelegatedDossierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_primary_manager_can_open_the_delegated_workspace_for_a_prestataire(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire délégué']);
        $company = $this->company($prestataire, 'Entreprise déléguée');

        $this->actingAs($manager)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee('Gestion déléguée des Prestataires')
            ->assertSee('href="'.route('gestionnaire.delegated.index').'"', false);

        $this->get(route('gestionnaire.delegated.index'))
            ->assertOk()
            ->assertSee('Gérer les dossiers pour le compte d’un Prestataire');

        $this->get(route('gestionnaire.delegated.show', $prestataire))
            ->assertOk()
            ->assertSee('Vous effectuez ces opérations pour le compte de')
            ->assertSee('Prestataire délégué')
            ->assertSee('Entreprise déléguée')
            ->assertDontSee('Documents entreprise');

        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertOk()
            ->assertSee('Détails de '.$company->name)
            ->assertSee('Vous effectuez ces opérations pour le compte de')
            ->assertSee('Prestataire délégué')
            ->assertSee('Documents entreprise (0/9)')
            ->assertSee('Employés')
            ->assertSee('Engins et machines')
            ->assertSee('Outillages')
            ->assertSee('Retour aux entreprises');

        $this->get(route('gestionnaire.delegated.companies.create', $prestataire))
            ->assertOk()
            ->assertSee('Vous effectuez ces opérations pour le compte de')
            ->assertSee('Prestataire délégué');

        $this->get(route('gestionnaire.delegated.employees.create', $prestataire))->assertOk();
        $this->get(route('gestionnaire.delegated.machinery.create', $prestataire))->assertOk();
        $this->get(route('gestionnaire.delegated.toolings.create', $prestataire))->assertOk();
    }

    public function test_only_the_primary_manager_can_access_the_delegated_workspace(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise protégée');
        $secondaryManager = $this->userWithRole('gestionnaire');
        $safetyManager = $this->userWithRole('responsable_securite');

        $this->actingAs($secondaryManager)
            ->get(route('gestionnaire.delegated.index'))
            ->assertForbidden();
        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertForbidden();

        $this->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertDontSee('Gestion déléguée des Prestataires');

        $this->actingAs($prestataire)
            ->get(route('gestionnaire.delegated.show', $prestataire))
            ->assertForbidden();
        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertForbidden();

        $this->actingAs($prestataire)
            ->get(route('prestataire.dashboard'))
            ->assertOk()
            ->assertDontSee('Gestion déléguée des Prestataires');

        $this->actingAs($safetyManager)
            ->get(route('gestionnaire.delegated.index'))
            ->assertForbidden();
        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertForbidden();

        $this->get(route('responsable-securite.dashboard'))
            ->assertOk()
            ->assertDontSee('Gestion déléguée des Prestataires');
    }

    public function test_primary_manager_cannot_select_an_account_that_is_not_a_prestataire(): void
    {
        $manager = $this->primaryManager();
        $otherManager = $this->userWithRole('gestionnaire');

        $this->actingAs($manager)
            ->get(route('gestionnaire.delegated.show', $otherManager))
            ->assertNotFound();
    }

    public function test_selected_prestataire_view_lists_company_summaries_and_opens_details_separately(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire liste']);
        $company = $this->company($prestataire, 'Entreprise résumé');
        $this->company($prestataire, 'Entreprise secondaire');
        $documentLabel = $company->documents()->firstOrFail()->label;
        $company->update([
            'activity' => 'Construction',
            'assignment' => 'Chantier Nord',
            'status' => Company::STATUS_PENDING,
        ]);
        $employee = $company->employees()->create([
            'first_name' => 'Employée',
            'last_name' => 'Détail',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
        $employeeDocumentType = CompanyEmployeeDocument::allTypes()[0];
        $employee->documents()->create([
            'type' => $employeeDocumentType,
            'label' => CompanyEmployeeDocument::labelForType($employeeDocumentType),
            'status' => CompanyEmployeeDocument::STATUS_DRAFT,
        ]);
        $secondEmployee = $company->employees()->create([
            'first_name' => 'Deuxième',
            'last_name' => 'Employé',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
        $machinery = $company->machinery()->create([
            'designation' => 'Machine détail',
            'category' => 'Transport',
            'status' => CompanyMachinery::STATUS_DRAFT,
        ]);
        $machineryDocumentType = CompanyMachineryDocument::allTypes()[0];
        foreach (CompanyMachineryDocument::allTypes() as $type) {
            $machinery->documents()->create([
                'type' => $type,
                'label' => CompanyMachineryDocument::labelForType($type),
                'status' => CompanyMachineryDocument::STATUS_DRAFT,
            ]);
        }
        $secondMachinery = $company->machinery()->create([
            'designation' => 'Deuxième machine',
            'category' => 'Levage',
            'status' => CompanyMachinery::STATUS_DRAFT,
        ]);
        foreach (CompanyMachineryDocument::allTypes() as $type) {
            $secondMachinery->documents()->create([
                'type' => $type,
                'label' => CompanyMachineryDocument::labelForType($type),
                'status' => CompanyMachineryDocument::STATUS_DRAFT,
            ]);
        }
        $tooling = $company->toolings()->create([
            'designation' => 'Outillage détail',
            'category' => 'Mesure',
            'quantity' => 1,
            'status' => CompanyTooling::STATUS_DRAFT,
        ]);
        $toolingDocumentType = CompanyToolingDocument::standardTypes()[0];
        foreach (CompanyToolingDocument::standardTypes() as $type) {
            $tooling->documents()->create([
                'type' => $type,
                'label' => CompanyToolingDocument::labelForType($type),
                'status' => CompanyToolingDocument::STATUS_DRAFT,
            ]);
        }
        $secondTooling = $company->toolings()->create([
            'designation' => 'Deuxième outillage',
            'category' => 'Protection',
            'quantity' => 2,
            'status' => CompanyTooling::STATUS_DRAFT,
        ]);
        foreach (CompanyToolingDocument::standardTypes() as $type) {
            $secondTooling->documents()->create([
                'type' => $type,
                'label' => CompanyToolingDocument::labelForType($type),
                'status' => CompanyToolingDocument::STATUS_DRAFT,
            ]);
        }

        $companyListResponse = $this->actingAs($manager)
            ->get(route('gestionnaire.delegated.show', $prestataire))
            ->assertOk()
            ->assertSee('Entreprise résumé')
            ->assertSee('Entreprise secondaire')
            ->assertSee('Construction')
            ->assertSee('Chantier Nord')
            ->assertSee('En attente')
            ->assertSee('href="'.route('gestionnaire.delegated.companies.show', [$prestataire, $company]).'"', false)
            ->assertDontSee($documentLabel)
            ->assertDontSee('Documents entreprise')
            ->assertDontSee('Employée Détail')
            ->assertDontSee('Machine détail')
            ->assertDontSee('Outillage détail');
        preg_match_all('/<details name="delegated-companies"[^>]*>/', $companyListResponse->getContent(), $companyAccordions);
        $this->assertCount(2, $companyAccordions[0]);
        $this->assertSame(0, preg_match('/<details name="delegated-companies"[^>]*\bopen(?:=|\s|>)/', $companyListResponse->getContent()));
        $this->assertMatchesRegularExpression('/<h3[^>]*>Entreprises<\/h3>\s*<span[^>]*>2<\/span>/', $companyListResponse->getContent());

        $detailResponse = $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertOk()
            ->assertSee('Employée Détail')
            ->assertSee('Deuxième Employé')
            ->assertSee('Machine détail')
            ->assertSee('Deuxième machine')
            ->assertSee('Outillage détail');
        $detailResponse->assertSee('Deuxième outillage');

        $html = $detailResponse->getContent();
        $companySectionGroup = 'delegated-company-sections-'.$company->id;
        preg_match_all('/<details name="'.preg_quote($companySectionGroup, '/').'"[^>]*>/', $html, $accordions);
        $this->assertCount(4, $accordions[0]);
        $this->assertSame(0, preg_match('/<details\b[^>]*\bopen(?:=|\s|>)/', $html));
        $this->assertStringContainsString('Documents entreprise (0/9)', $html);
        $this->assertStringContainsString('<span>Employés</span>', $html);
        $this->assertStringContainsString('<span>Engins et machines</span>', $html);
        $this->assertStringContainsString('<span>Outillages</span>', $html);
        $this->assertMatchesRegularExpression('/<span>Employés<\/span>\s*<span[^>]*>2<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<span>Engins et machines<\/span>\s*<span[^>]*>2<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<span>Outillages<\/span>\s*<span[^>]*>2<\/span>/', $html);
        $this->assertStringContainsString('Obligatoire pour la validation de l’entreprise', $html);
        $this->assertStringContainsString('Manquant', $html);
        $this->assertStringContainsString('Téléverser / remplacer', $html);
        $this->assertStringContainsString('Obligatoire pour la soumission et la validation', $html);
        $this->assertStringContainsString('Enregistrer le document', $html);
        $this->assertStringContainsString('Date d’émission', $html);
        $this->assertStringContainsString('Date d’expiration', $html);
        $this->assertStringContainsString('Ajouter un autre document', $html);
        foreach (CompanyDocument::allTypes() as $type) {
            $this->assertStringContainsString(CompanyDocument::labelForType($type), $html);
        }
        foreach (CompanyEmployeeDocument::allTypes() as $type) {
            $this->assertStringContainsString(CompanyEmployeeDocument::labelForType($type), $html);
        }
        $assertDetailsCount = function (string $name, int $count) use ($html): void {
            preg_match_all('/<details name="'.preg_quote($name, '/').'"[^>]*>/', $html, $matches);
            $this->assertCount($count, $matches[0], $name);
        };
        $assertDetailsCount('delegated-employees-'.$company->id, 2);
        $assertDetailsCount('delegated-machinery-'.$company->id, 2);
        $assertDetailsCount('delegated-toolings-'.$company->id, 2);
        $assertDetailsCount('delegated-company-documents-'.$company->id, count(CompanyDocument::allTypes()));
        foreach ([$employee, $secondEmployee] as $record) {
            $assertDetailsCount('delegated-employee-documents-'.$record->id, count(CompanyEmployeeDocument::allTypes()));
        }
        foreach ([$machinery, $secondMachinery] as $record) {
            $assertDetailsCount('delegated-machinery-documents-'.$record->id, count(CompanyMachineryDocument::allTypes()));
        }
        foreach ([$tooling, $secondTooling] as $record) {
            $assertDetailsCount('delegated-tooling-documents-'.$record->id, count(CompanyToolingDocument::standardTypes()));
        }
        $this->assertStringContainsString(
            route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $company->documents()->firstOrFail()->type,
            ]),
            $html,
        );
        $this->assertStringContainsString(
            route('gestionnaire.delegated.employees.documents.store', [$prestataire, $employee, $employeeDocumentType]),
            $html,
        );
        $this->assertStringContainsString(
            route('gestionnaire.delegated.machinery.documents.store', [$prestataire, $machinery, $machineryDocumentType]),
            $html,
        );
        $this->assertStringContainsString(
            route('gestionnaire.delegated.toolings.documents.store', [$prestataire, $tooling, $toolingDocumentType]),
            $html,
        );
        $this->assertStringContainsString(route('gestionnaire.delegated.employees.create', $prestataire), $html);
        $this->assertStringContainsString(route('gestionnaire.delegated.machinery.create', $prestataire), $html);
        $this->assertStringContainsString(route('gestionnaire.delegated.toolings.create', $prestataire), $html);

        $this->assertSame($company->getKey(), $employee->company_id);
        $this->assertSame($company->getKey(), $machinery->company_id);
        $this->assertSame($company->getKey(), $tooling->company_id);
    }

    public function test_company_document_counter_tracks_uploaded_types_and_preserves_entity_counts(): void
    {
        Storage::fake('documents_private');
        Notification::fake();
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise compteur documentaire');
        $detailUrl = route('gestionnaire.delegated.companies.show', [$prestataire, $company]);

        foreach (range(1, 2) as $index) {
            $company->employees()->create([
                'first_name' => 'Employé '.$index,
                'last_name' => 'Compteur',
                'status' => CompanyEmployee::STATUS_DRAFT,
            ]);
        }

        foreach (range(1, 3) as $index) {
            $company->machinery()->create([
                'designation' => 'Engin '.$index,
                'category' => 'Transport',
                'status' => CompanyMachinery::STATUS_DRAFT,
            ]);
        }

        foreach (range(1, 4) as $index) {
            $company->toolings()->create([
                'designation' => 'Outillage '.$index,
                'category' => 'Protection',
                'quantity' => 1,
                'status' => CompanyTooling::STATUS_DRAFT,
            ]);
        }

        $initialResponse = $this->actingAs($manager)
            ->get($detailUrl)
            ->assertOk()
            ->assertSee('Documents entreprise (0/9)')
            ->assertSee('Manquant');
        $this->assertMatchesRegularExpression('/<span>Employés<\/span>\s*<span[^>]*>2<\/span>/', $initialResponse->getContent());
        $this->assertMatchesRegularExpression('/<span>Engins et machines<\/span>\s*<span[^>]*>3<\/span>/', $initialResponse->getContent());
        $this->assertMatchesRegularExpression('/<span>Outillages<\/span>\s*<span[^>]*>4<\/span>/', $initialResponse->getContent());

        $uploadDocument = function (string $type, string $fileName, ?string $expiresAt = null) use ($prestataire, $company, $detailUrl): void {
            $payload = [
                'document' => UploadedFile::fake()->create($fileName, 50, 'application/pdf'),
            ];

            if ($expiresAt !== null) {
                $payload['expires_at'] = $expiresAt;
            }

            $this->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $type,
            ]), $payload)->assertRedirect($detailUrl);
        };
        $expiredDate = today()->subDay()->toDateString();
        $documentTypes = CompanyDocument::allTypes();

        foreach (array_slice($documentTypes, 0, 6) as $index => $type) {
            $uploadDocument(
                $type,
                'document-'.$index.'.pdf',
                $type === CompanyDocument::TYPE_ASSURANCE_AT ? $expiredDate : null,
            );
        }

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('Documents entreprise (6/9)')
            ->assertSee('Déposé')
            ->assertSee('Manquant')
            ->assertSee('À renouveler');

        $uploadDocument(CompanyDocument::TYPE_ASSURANCE_AT, 'assurance-at-remplacement.pdf', $expiredDate);

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('Documents entreprise (6/9)');

        foreach (array_slice($documentTypes, 6) as $index => $type) {
            $uploadDocument($type, 'document-complementaire-'.$index.'.pdf');
        }

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('Documents entreprise (9/9)')
            ->assertDontSee('Manquant');
    }

    public function test_unauthenticated_user_is_redirected_from_the_delegated_workspace(): void
    {
        $this->get(route('gestionnaire.delegated.index'))
            ->assertRedirect(route('login'));
    }

    public function test_primary_manager_creates_a_company_owned_and_audited_by_the_selected_prestataire(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');

        $response = $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.store', $prestataire), [
                'name' => 'Entreprise créée par délégation',
                'activity' => 'Construction',
                'assignment' => 'Chantier A',
            ]);

        $company = Company::query()->where('name', 'Entreprise créée par délégation')->sole();
        $response->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->assertSame($prestataire->getKey(), $company->prestataire_id);
        $this->assertSame(Company::STATUS_DRAFT, $company->status);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'created',
            'to_status' => Company::STATUS_DRAFT,
        ]);
    }

    public function test_primary_manager_creates_an_employee_for_the_selected_prestataire_company(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise employé délégué');

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.employees.store', $prestataire), [
                'company_id' => $company->getKey(),
                'first_name' => 'Salma',
                'last_name' => 'Amrani',
                'cin' => 'AB12345',
                'position' => 'Technicienne',
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $employee = CompanyEmployee::query()->where('first_name', 'Salma')->sole();

        $this->assertSame($company->getKey(), $employee->company_id);
        $this->assertSame($prestataire->getKey(), $employee->company->prestataire_id);
        $this->assertSame(CompanyEmployee::STATUS_DRAFT, $employee->status);
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'created',
            'to_status' => CompanyEmployee::STATUS_DRAFT,
        ]);
    }

    public function test_primary_manager_creates_machinery_and_tooling_for_the_selected_prestataire(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise matériel délégué');

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.machinery.store', $prestataire), [
                'company_id' => $company->getKey(),
                'designation' => 'Pelle déléguée',
                'category' => 'Terrassement',
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.toolings.store', $prestataire), [
                'company_id' => $company->getKey(),
                'designation' => 'Clé déléguée',
                'category' => 'Mesure',
                'quantity' => 1,
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $machinery = CompanyMachinery::query()->where('designation', 'Pelle déléguée')->sole();
        $tooling = CompanyTooling::query()->where('designation', 'Clé déléguée')->sole();

        $this->assertSame($company->getKey(), $machinery->company_id);
        $this->assertSame($company->getKey(), $tooling->company_id);
        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->status);
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->status);
        $this->assertDatabaseHas('company_machinery_histories', [
            'company_machinery_id' => $machinery->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'created',
        ]);
        $this->assertDatabaseHas('company_tooling_histories', [
            'company_tooling_id' => $tooling->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'created',
        ]);
    }

    public function test_primary_manager_can_update_rejected_employee_machinery_and_tooling_records(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise corrections déléguées');
        $employee = $company->employees()->create([
            'first_name' => 'Avant',
            'last_name' => 'Employé',
            'status' => CompanyEmployee::STATUS_REJECTED,
            'rejection_reason' => 'Correction demandée',
        ]);
        $machinery = $company->machinery()->create([
            'designation' => 'Avant engin',
            'category' => 'Transport',
            'status' => CompanyMachinery::STATUS_REJECTED,
            'rejection_reason' => 'Correction demandée',
        ]);
        $tooling = $company->toolings()->create([
            'designation' => 'Avant outillage',
            'category' => 'Mesure',
            'quantity' => 1,
            'status' => CompanyTooling::STATUS_REJECTED,
            'rejection_reason' => 'Correction demandée',
        ]);

        $this->actingAs($manager)
            ->put(route('gestionnaire.delegated.employees.update', [$prestataire, $employee]), [
                'first_name' => 'Après',
                'last_name' => 'Employé',
                'cin' => 'AB12345',
                'position' => 'Technicien',
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        $this->put(route('gestionnaire.delegated.machinery.update', [$prestataire, $machinery]), [
            'designation' => 'Après engin',
            'category' => 'Levage',
        ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        $this->put(route('gestionnaire.delegated.toolings.update', [$prestataire, $tooling]), [
            'designation' => 'Après outillage',
            'category' => 'Sécurité',
            'quantity' => 2,
        ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        foreach ([
            [$employee, 'Après', CompanyEmployee::STATUS_DRAFT, 'company_employee_histories', 'company_employee_id'],
            [$machinery, 'Après engin', CompanyMachinery::STATUS_DRAFT, 'company_machinery_histories', 'company_machinery_id'],
            [$tooling, 'Après outillage', CompanyTooling::STATUS_DRAFT, 'company_tooling_histories', 'company_tooling_id'],
        ] as [$record, $updatedValue, $expectedStatus, $historyTable, $foreignKey]) {
            $record->refresh();
            $this->assertSame($updatedValue, $record->designation ?? $record->first_name);
            $this->assertSame($expectedStatus, $record->status);
            $this->assertSame($prestataire->getKey(), $record->company->prestataire_id);
            $this->assertDatabaseHas($historyTable, [
                $foreignKey => $record->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'updated',
            ]);
        }
    }

    public function test_primary_manager_can_validate_a_self_uploaded_document_from_the_classic_company_page(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise document délégué');
        $document = $company->documents()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create('attestation.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $document->refresh();
        $version = $document->versions()->sole();

        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->status);
        $this->assertSame(Company::STATUS_DRAFT, $company->fresh()->status);
        $this->assertSame($manager->getKey(), $version->uploaded_by);
        $this->assertNotSame(CompanyDocument::STATUS_VALIDATED, $document->status);
        Storage::disk('documents_private')->assertExists($version->file_path);
        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertOk()
            ->assertSee(route('gestionnaire.companies.documents.preview', [$company, $document]), false)
            ->assertSee(route('gestionnaire.companies.documents.download', [$company, $document]), false)
            ->assertSee('Téléverser / remplacer')
            ->assertSee(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), false)
            ->assertSee('document_ids[]');

        $this->post(route('gestionnaire.companies.documents.approve', [$company, $document]))
            ->assertStatus(409);
        $this->post(route('gestionnaire.companies.documents.approve-selected', $company), [
            'document_ids' => [$document->getKey()],
        ])->assertRedirect(route('gestionnaire.companies.show', $company));

        $document->refresh();
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->status);
        $this->assertSame($manager->getKey(), $document->validated_by);
        $this->assertNotNull($document->validated_at);
        $this->assertSame($manager->getKey(), $document->versions()->sole()->uploaded_by);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $document->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'document_validated',
            'to_status' => CompanyDocument::STATUS_VALIDATED,
        ]);
    }

    public function test_primary_manager_can_bulk_validate_self_uploaded_documents_from_the_classic_company_page(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise validation groupée classique');
        $documents = $company->documents()->whereIn('type', [
            CompanyDocument::TYPE_ASSURANCE_AT,
            CompanyDocument::TYPE_ASSURANCE_RC,
        ])->get();

        $this->actingAs($manager);

        foreach ($documents as $document) {
            $this->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create($document->type.'.pdf', 50, 'application/pdf'),
            ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        }

        $this->post(route('gestionnaire.companies.documents.approve-selected', $company), [
            'document_ids' => $documents->modelKeys(),
        ])->assertRedirect(route('gestionnaire.companies.show', $company))
            ->assertSessionHas('status', '2 documents validés.');

        foreach ($documents as $document) {
            $document->refresh();
            $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->status);
            $this->assertSame($manager->getKey(), $document->versions()->sole()->uploaded_by);
            $this->assertSame($manager->getKey(), $document->validated_by);
            $this->assertNotNull($document->validated_at);
            $this->assertDatabaseHas('company_review_histories', [
                'company_id' => $company->getKey(),
                'company_document_id' => $document->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'to_status' => CompanyDocument::STATUS_VALIDATED,
            ]);
        }
    }

    public function test_classic_bulk_validation_rejects_documents_from_another_company_without_partial_changes(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise ciblée');
        $otherCompany = $this->company($otherPrestataire, 'Autre entreprise');
        $companyDocument = $company->documents()->where('type', CompanyDocument::TYPE_ASSURANCE_AT)->firstOrFail();
        $foreignDocument = $otherCompany->documents()->where('type', CompanyDocument::TYPE_ASSURANCE_AT)->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $companyDocument->type,
            ]), [
                'document' => UploadedFile::fake()->create('entreprise-cible.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->actingAs($otherPrestataire)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $otherCompany,
                'documentType' => $foreignDocument->type,
            ]), [
                'document' => UploadedFile::fake()->create('autre-entreprise.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('prestataire.companies.show', $otherCompany));

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.approve-selected', $company), [
                'document_ids' => [$companyDocument->getKey(), $foreignDocument->getKey()],
            ])
            ->assertUnprocessable();

        $companyDocument->refresh();
        $foreignDocument->refresh();
        $this->assertSame(CompanyDocument::STATUS_PENDING, $companyDocument->status);
        $this->assertNull($companyDocument->validated_by);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $foreignDocument->status);
        $this->assertNull($foreignDocument->validated_by);
        $this->assertDatabaseMissing('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $companyDocument->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_classic_bulk_validation_rolls_back_when_a_selected_document_is_not_pending(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise validation groupée incompatible');
        $documents = $company->documents()->whereIn('type', [
            CompanyDocument::TYPE_ASSURANCE_AT,
            CompanyDocument::TYPE_ASSURANCE_RC,
        ])->get();

        $this->actingAs($manager);

        foreach ($documents as $document) {
            $this->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create($document->type.'.pdf', 50, 'application/pdf'),
            ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        }

        $alreadyValidated = $documents->last();
        $validatedAt = now()->subDay();
        $alreadyValidated->update([
            'status' => CompanyDocument::STATUS_VALIDATED,
            'validated_by' => $prestataire->getKey(),
            'validated_at' => $validatedAt,
        ]);
        $pendingDocument = $documents->first();

        $this->post(route('gestionnaire.companies.documents.approve-selected', $company), [
            'document_ids' => $documents->modelKeys(),
        ])->assertConflict();

        $pendingDocument->refresh();
        $alreadyValidated->refresh();
        $this->assertSame(CompanyDocument::STATUS_PENDING, $pendingDocument->status);
        $this->assertNull($pendingDocument->validated_by);
        $this->assertNull($pendingDocument->validated_at);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $alreadyValidated->status);
        $this->assertSame($prestataire->getKey(), $alreadyValidated->validated_by);
        $this->assertSame($validatedAt->toDateTimeString(), $alreadyValidated->validated_at->toDateTimeString());
        $this->assertDatabaseMissing('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $pendingDocument->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_primary_manager_can_bulk_validate_multiple_documents_uploaded_in_the_delegated_workspace(): void
    {
        Storage::fake('documents_private');
        Notification::fake();
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise validation groupée déléguée');
        $documents = $company->documents()->whereIn('type', [
            CompanyDocument::TYPE_ASSURANCE_AT,
            CompanyDocument::TYPE_ASSURANCE_RC,
        ])->get();

        $this->actingAs($manager);

        foreach ($documents as $document) {
            $this->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create($document->type.'.pdf', 50, 'application/pdf'),
            ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        }

        $this->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), [
            'document_ids' => $documents->modelKeys(),
        ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertSessionHas('status', '2 documents validés.');

        foreach ($documents as $document) {
            $document->refresh();
            $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->status);
            $this->assertSame($manager->getKey(), $document->validated_by);
            $this->assertNotNull($document->validated_at);
            $this->assertSame($manager->getKey(), $document->versions()->sole()->uploaded_by);
            $this->assertDatabaseHas('company_review_histories', [
                'company_id' => $company->getKey(),
                'company_document_id' => $document->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'to_status' => CompanyDocument::STATUS_VALIDATED,
            ]);
        }

        $this->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), [
            'document_ids' => $documents->modelKeys(),
        ])->assertStatus(409);

        foreach ($documents as $document) {
            $this->assertSame(1, $document->reviewHistory()->where('action', 'document_validated')->count());
        }
    }

    public function test_primary_manager_can_bulk_validate_delegated_documents_uploaded_by_different_actors(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise documents de plusieurs déposants');
        $documents = $company->documents()->whereIn('type', [
            CompanyDocument::TYPE_ASSURANCE_AT,
            CompanyDocument::TYPE_ASSURANCE_RC,
        ])->get()->keyBy('type');
        $managerDocument = $documents->get(CompanyDocument::TYPE_ASSURANCE_AT);
        $prestataireDocument = $documents->get(CompanyDocument::TYPE_ASSURANCE_RC);

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $managerDocument->type,
            ]), [
                'document' => UploadedFile::fake()->create('assurance-at.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->actingAs($prestataire)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => $prestataireDocument->type,
            ]), [
                'document' => UploadedFile::fake()->create('assurance-rc.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('prestataire.companies.show', $company));

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), [
                'document_ids' => $documents->modelKeys(),
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->assertSame($manager->getKey(), $managerDocument->fresh()->versions()->sole()->uploaded_by);
        $this->assertSame($prestataire->getKey(), $prestataireDocument->fresh()->versions()->sole()->uploaded_by);

        foreach ($documents as $document) {
            $document->refresh();
            $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->status);
            $this->assertSame($manager->getKey(), $document->validated_by);
            $this->assertNotNull($document->validated_at);
            $this->assertDatabaseHas('company_review_histories', [
                'company_id' => $company->getKey(),
                'company_document_id' => $document->getKey(),
                'user_id' => $manager->getKey(),
                'action' => 'document_validated',
                'to_status' => CompanyDocument::STATUS_VALIDATED,
            ]);
        }
    }

    public function test_delegated_bulk_validation_leaves_all_documents_unchanged_when_selection_contains_a_validated_document(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise sélection avec statut incompatible');
        $documents = $company->documents()->whereIn('type', [
            CompanyDocument::TYPE_ASSURANCE_AT,
            CompanyDocument::TYPE_ASSURANCE_RC,
        ])->get();

        $this->actingAs($manager);

        foreach ($documents as $document) {
            $this->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create($document->type.'.pdf', 50, 'application/pdf'),
            ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        }

        $alreadyValidated = $documents->last();
        $validatedAt = now()->subDay();
        $alreadyValidated->update([
            'status' => CompanyDocument::STATUS_VALIDATED,
            'validated_by' => $prestataire->getKey(),
            'validated_at' => $validatedAt,
        ]);
        $pendingDocument = $documents->first();

        $this->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), [
            'document_ids' => $documents->modelKeys(),
        ])->assertStatus(409);

        $pendingDocument->refresh();
        $alreadyValidated->refresh();
        $this->assertSame(CompanyDocument::STATUS_PENDING, $pendingDocument->status);
        $this->assertNull($pendingDocument->validated_by);
        $this->assertNull($pendingDocument->validated_at);
        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $alreadyValidated->status);
        $this->assertSame($prestataire->getKey(), $alreadyValidated->validated_by);
        $this->assertSame($validatedAt->toDateTimeString(), $alreadyValidated->validated_at->toDateTimeString());
        $this->assertDatabaseMissing('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $pendingDocument->getKey(),
            'action' => 'document_validated',
        ]);
        $this->assertDatabaseMissing('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $alreadyValidated->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_delegated_company_approval_button_requires_all_nine_required_documents_to_be_validated(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $requiredTypes = CompanyDocument::requiredTypes();
        $this->assertCount(9, $requiredTypes);

        $completeCompany = $this->company($prestataire, 'Entreprise prête à valider');
        $completeCompany->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($completeCompany);

        $pendingCompany = $this->company($prestataire, 'Entreprise avec document en attente');
        $pendingCompany->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($pendingCompany, $requiredTypes[0]);

        $missingCompany = $this->company($prestataire, 'Entreprise avec document manquant');
        $missingCompany->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($missingCompany, missingType: $requiredTypes[0]);

        $this->actingAs($manager)
            ->get(route('gestionnaire.delegated.companies.show', [$prestataire, $completeCompany]))
            ->assertOk()
            ->assertSee(
                '<button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-700',
                false,
            )
            ->assertSee('Valider l’entreprise');

        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $pendingCompany]))
            ->assertOk()
            ->assertSee(
                '<button type="button" disabled aria-describedby="company-approval-blockers-'.$pendingCompany->id.'"',
                false,
            )
            ->assertSee('en attente de validation');

        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $missingCompany]))
            ->assertOk()
            ->assertSee(
                '<button type="button" disabled aria-describedby="company-approval-blockers-'.$missingCompany->id.'"',
                false,
            )
            ->assertSee('document obligatoire manquant');
    }

    public function test_primary_manager_can_validate_company_after_delegated_documents_are_validated_and_submitted(): void
    {
        Storage::fake('documents_private');
        Notification::fake();
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise validation finale déléguée');
        $company->update([
            'activity' => 'Construction',
            'assignment' => 'Chantier Nord',
        ]);
        $documents = $company->documents()->get();
        $this->assertCount(9, $documents);

        $this->actingAs($manager);
        foreach ($documents as $document) {
            $this->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create($document->type.'.pdf', 50, 'application/pdf'),
            ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        }

        $this->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), [
            'document_ids' => $documents->modelKeys(),
        ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertSessionHas('status', '9 documents validés.');

        foreach ($documents as $document) {
            $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->fresh()->status);
        }

        $this->post(route('gestionnaire.delegated.companies.submit', [$prestataire, $company]))
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
        foreach ($documents as $document) {
            $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->fresh()->status);
        }

        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertOk()
            ->assertSee(
                '<button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-700',
                false,
            )
            ->assertSee('Valider l’entreprise');

        $this->post(route('gestionnaire.delegated.companies.approve', [$prestataire, $company]))
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->post(route('gestionnaire.delegated.companies.approve', [$prestataire, $company]))
            ->assertStatus(409);

        $company->refresh();
        $this->assertSame(Company::STATUS_VALIDATED, $company->status);
        $this->assertSame($manager->getKey(), $company->validated_by);
        $this->assertNotNull($company->validated_at);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'validated',
            'from_status' => Company::STATUS_PENDING,
            'to_status' => Company::STATUS_VALIDATED,
        ]);
        foreach ($documents as $document) {
            $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->fresh()->status);
            $this->assertSame($manager->getKey(), $document->fresh()->versions()->sole()->uploaded_by);
        }
    }

    public function test_delegated_company_approval_endpoint_rechecks_document_blockers_and_company_scope(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $pendingCompany = $this->company($prestataire, 'Entreprise bloquée par document en attente');
        $pendingCompany->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($pendingCompany, CompanyDocument::requiredTypes()[0]);

        $missingCompany = $this->company($prestataire, 'Entreprise bloquée par document manquant');
        $missingCompany->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($missingCompany, missingType: CompanyDocument::requiredTypes()[0]);

        $outOfScopeCompany = $this->company($otherPrestataire, 'Entreprise d’un autre Prestataire');
        $outOfScopeCompany->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($outOfScopeCompany);

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.approve', [$prestataire, $pendingCompany]))
            ->assertStatus(409)
            ->assertSeeText('en attente de validation');

        $this->post(route('gestionnaire.delegated.companies.approve', [$prestataire, $missingCompany]))
            ->assertStatus(409)
            ->assertSeeText('document obligatoire manquant');

        $this->post(route('gestionnaire.delegated.companies.approve', [$prestataire, $outOfScopeCompany]))
            ->assertNotFound();

        $this->assertSame(Company::STATUS_PENDING, $pendingCompany->fresh()->status);
        $this->assertSame(Company::STATUS_PENDING, $missingCompany->fresh()->status);
        $this->assertSame(Company::STATUS_PENDING, $outOfScopeCompany->fresh()->status);
    }

    public function test_secondary_manager_cannot_use_the_delegated_company_approval_endpoint(): void
    {
        $secondaryManager = $this->userWithRole('gestionnaire');
        $secondaryManager->permissions()->attach(
            Permission::query()->where('name', 'entreprises.validate')->firstOrFail(),
        );
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise validation primaire seulement');
        $company->update(['status' => Company::STATUS_PENDING]);
        $this->prepareRequiredCompanyDocuments($company);

        $this->actingAs($secondaryManager)
            ->post(route('gestionnaire.delegated.companies.approve', [$prestataire, $company]))
            ->assertForbidden();

        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
        $this->assertNull($company->fresh()->validated_by);
    }

    public function test_secondary_manager_cannot_self_validate_or_access_delegated_bulk_validation(): void
    {
        $manager = $this->userWithRole('gestionnaire');
        $manager->permissions()->attach(
            Permission::query()->where('name', 'entreprises.validate')->firstOrFail(),
        );
        $prestataire = $this->userWithRole('prestataire');
        $manager->assignedPrestataires()->attach($prestataire, ['assigned_by' => $manager->getKey()]);
        $company = $this->company($prestataire, 'Entreprise validation gestionnaire secondaire');
        $document = $company->documents()->firstOrFail();
        $filePath = 'companies/'.$company->getKey().'/document.pdf';
        Storage::fake('documents_private');
        Storage::disk('documents_private')->put($filePath, 'contenu');
        $document->update([
            'status' => CompanyDocument::STATUS_PENDING,
            'file_name' => 'document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
        ]);
        $document->versions()->create([
            'version' => 1,
            'file_name' => 'document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $manager->getKey(),
            'is_current' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.companies.documents.approve-selected', $company), [
                'document_ids' => [$document->getKey()],
            ])->assertStatus(409);

        $this->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$prestataire, $company]), [
            'document_ids' => [$document->getKey()],
        ])->assertForbidden();

        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->fresh()->status);
        $this->assertNull($document->fresh()->validated_by);
        $this->assertDatabaseMissing('company_review_histories', [
            'company_document_id' => $document->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_primary_manager_cannot_bulk_validate_a_company_outside_the_selected_prestataire_context(): void
    {
        $manager = $this->primaryManager();
        $selectedPrestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $company = $this->company($otherPrestataire, 'Entreprise hors sélection');
        $document = $company->documents()->firstOrFail();
        $document->update([
            'status' => CompanyDocument::STATUS_PENDING,
            'file_path' => 'companies/'.$company->getKey().'/document.pdf',
        ]);
        $document->versions()->create([
            'version' => 1,
            'file_name' => 'document.pdf',
            'file_path' => $document->file_path,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $manager->getKey(),
            'is_current' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.approve-selected', [$selectedPrestataire, $company]), [
                'document_ids' => [$document->getKey()],
            ])
            ->assertNotFound();

        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->fresh()->status);
        $this->assertNull($document->fresh()->validated_by);
        $this->assertDatabaseMissing('company_review_histories', [
            'company_document_id' => $document->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_primary_manager_document_uploads_for_children_keep_the_existing_pending_workflow(): void
    {
        Storage::fake('documents_private');
        Notification::fake();
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise sous-dossiers');
        $employee = $company->employees()->create([
            'first_name' => 'Nadia',
            'last_name' => 'Test',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
        $employeeDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::allTypes()[0],
            'label' => CompanyEmployeeDocument::labelForType(CompanyEmployeeDocument::allTypes()[0]),
            'status' => CompanyEmployeeDocument::STATUS_DRAFT,
        ]);
        $machinery = $company->machinery()->create([
            'designation' => 'Camion test',
            'category' => 'Transport',
            'status' => CompanyMachinery::STATUS_DRAFT,
        ]);
        $machineryDocument = $machinery->documents()->create([
            'type' => CompanyMachineryDocument::allTypes()[0],
            'label' => CompanyMachineryDocument::labelForType(CompanyMachineryDocument::allTypes()[0]),
            'status' => CompanyMachineryDocument::STATUS_DRAFT,
        ]);
        $tooling = $company->toolings()->create([
            'designation' => 'Outil test',
            'category' => 'Mesure',
            'quantity' => 1,
            'status' => CompanyTooling::STATUS_DRAFT,
        ]);
        $toolingType = CompanyToolingDocument::standardTypes()[0];
        $toolingDocument = $tooling->documents()->create([
            'type' => $toolingType,
            'label' => CompanyToolingDocument::labelForType($toolingType),
            'status' => CompanyToolingDocument::STATUS_DRAFT,
        ]);
        $file = UploadedFile::fake()->create('piece.pdf', 30, 'application/pdf');

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.employees.documents.store', [
                $prestataire,
                $employee,
                $employeeDocument->type,
            ]), ['document' => $file])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->post(route('gestionnaire.delegated.machinery.documents.store', [
            $prestataire,
            $machinery,
            $machineryDocument->type,
        ]), ['document' => $file])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $this->post(route('gestionnaire.delegated.toolings.documents.store', [
            $prestataire,
            $tooling,
            $toolingDocument->type,
        ]), ['document' => $file])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));
        $this->post(route('gestionnaire.delegated.toolings.documents.custom.store', [$prestataire, $tooling]), [
            'label' => 'Rapport complémentaire',
            'document' => UploadedFile::fake()->create('rapport.pdf', 30, 'application/pdf'),
        ])->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        foreach ([$employeeDocument, $machineryDocument, $toolingDocument] as $document) {
            $document->refresh();
            $version = $document->versions()->sole();

            $this->assertSame('pending', $document->status);
            $this->assertSame($manager->getKey(), $version->uploaded_by);
            Storage::disk('documents_private')->assertExists($version->file_path);
        }
        $customDocument = $tooling->documents()->where('label', 'Rapport complémentaire')->sole();
        $this->assertStringStartsWith('custom_', $customDocument->type);
        $this->assertSame('pending', $customDocument->status);
        $this->assertSame($manager->getKey(), $customDocument->versions()->sole()->uploaded_by);

        $this->get(route('gestionnaire.delegated.companies.show', [$prestataire, $company]))
            ->assertOk()
            ->assertSee(route('gestionnaire.employees.documents.preview', [$employee, $employeeDocument]), false)
            ->assertSee(route('gestionnaire.employees.documents.download', [$employee, $employeeDocument]), false)
            ->assertSee(route('gestionnaire.machinery.documents.preview', [$machinery, $machineryDocument]), false)
            ->assertSee(route('gestionnaire.machinery.documents.download', [$machinery, $machineryDocument]), false)
            ->assertSee(route('gestionnaire.toolings.documents.preview', [$tooling, $toolingDocument]), false)
            ->assertSee(route('gestionnaire.toolings.documents.download', [$tooling, $toolingDocument]), false)
            ->assertSee('Date d’émission')
            ->assertSee('Ajouter un autre document');

        $company->update(['status' => Company::STATUS_VALIDATED]);
        $approvalRequests = [
            [
                route('gestionnaire.employees.documents.approve', [$employee, $employeeDocument]),
                route('gestionnaire.employees.documents.approve-selected', $employee),
                $employeeDocument,
            ],
            [
                route('gestionnaire.machinery.documents.approve', [$machinery, $machineryDocument]),
                route('gestionnaire.machinery.documents.approve-selected', $machinery),
                $machineryDocument,
            ],
            [
                route('gestionnaire.toolings.documents.approve', [$tooling, $toolingDocument]),
                route('gestionnaire.toolings.documents.approve-selected', $tooling),
                $toolingDocument,
            ],
        ];

        foreach ($approvalRequests as [$singleRoute, $bulkRoute, $document]) {
            $this->post($singleRoute)->assertStatus(409);
            $this->post($bulkRoute, ['document_ids' => [$document->getKey()]])->assertStatus(409);
            $this->assertSame('pending', $document->fresh()->status);
        }
    }

    public function test_primary_manager_cannot_change_a_record_owned_by_another_prestataire(): void
    {
        $manager = $this->primaryManager();
        $selectedPrestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $otherCompany = $this->company($otherPrestataire, 'Entreprise hors dossier');

        $this->actingAs($manager)
            ->get(route('gestionnaire.delegated.companies.show', [$selectedPrestataire, $otherCompany]))
            ->assertNotFound();

        $this->post(route('gestionnaire.delegated.employees.store', $selectedPrestataire), [
            'company_id' => $otherCompany->getKey(),
            'first_name' => 'Non',
            'last_name' => 'Autorisé',
        ])
            ->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('company_employees', ['first_name' => 'Non']);
    }

    public function test_primary_manager_can_update_a_rejected_dossier_without_changing_ownership(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise rejetée');
        $company->update([
            'status' => Company::STATUS_REJECTED,
            'rejection_reason' => 'À corriger',
        ]);

        $this->actingAs($manager)
            ->put(route('gestionnaire.delegated.companies.update', [$prestataire, $company]), [
                'name' => 'Entreprise corrigée',
                'activity' => 'Construction',
                'assignment' => 'Chantier B',
            ])
            ->assertRedirect(route('gestionnaire.delegated.companies.show', [$prestataire, $company]));

        $company->refresh();
        $this->assertSame($prestataire->getKey(), $company->prestataire_id);
        $this->assertSame('Entreprise corrigée', $company->name);
        $this->assertSame(Company::STATUS_DRAFT, $company->status);
        $this->assertNull($company->rejection_reason);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'updated',
        ]);
    }

    public function test_primary_manager_cannot_edit_a_pending_dossier_through_the_delegated_workflow(): void
    {
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise en attente');
        $company->update(['status' => Company::STATUS_PENDING]);

        $this->actingAs($manager)
            ->put(route('gestionnaire.delegated.companies.update', [$prestataire, $company]), [
                'name' => 'Modification interdite',
                'activity' => 'Construction',
                'assignment' => 'Chantier B',
            ])
            ->assertForbidden();

        $this->assertSame('Entreprise en attente', $company->fresh()->name);
        $this->assertSame(Company::STATUS_PENDING, $company->fresh()->status);
    }

    public function test_delegated_document_upload_keeps_server_side_file_validation(): void
    {
        Storage::fake('documents_private');
        $manager = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise validation upload');
        $document = $company->documents()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.store', [
                $prestataire,
                $company,
                $document->type,
            ]), [
                'document' => UploadedFile::fake()->create('fichier.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('company_document_versions', 0);
    }

    public function test_secondary_manager_permissions_are_unchanged_after_a_denied_delegated_request(): void
    {
        $secondaryManager = $this->userWithRole('gestionnaire');
        $secondaryManager->permissions()->attach(
            Permission::query()->where('name', 'entreprises.view')->firstOrFail(),
        );
        $permissionsBeforeRequest = $secondaryManager->permissions()->pluck('permissions.id')->all();
        $prestataire = $this->userWithRole('prestataire');

        $this->actingAs($secondaryManager)
            ->post(route('gestionnaire.delegated.companies.store', $prestataire), [
                'name' => 'Non autorisée',
            ])
            ->assertForbidden();

        $this->assertSame(
            $permissionsBeforeRequest,
            $secondaryManager->fresh()->permissions()->pluck('permissions.id')->all(),
        );
        $this->assertDatabaseMissing('companies', ['name' => 'Non autorisée']);
    }

    private function primaryManager(): User
    {
        $manager = $this->userWithRole('gestionnaire');
        ManagerSettings::query()->updateOrCreate(
            ['id' => ManagerSettings::SINGLETON_ID],
            ['principal_user_id' => $manager->getKey()],
        );

        return $manager;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function company(User $prestataire, string $name): Company
    {
        $company = $prestataire->companies()->create([
            'name' => $name,
            'status' => Company::STATUS_DRAFT,
        ]);

        foreach (CompanyDocument::requiredTypes() as $type) {
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_DRAFT,
            ]);
        }

        return $company;
    }

    private function prepareRequiredCompanyDocuments(
        Company $company,
        ?string $pendingType = null,
        ?string $missingType = null,
    ): void {
        foreach (CompanyDocument::requiredTypes() as $type) {
            if ($type === $missingType) {
                $company->documents()->where('type', $type)->delete();

                continue;
            }

            $company->documents()->where('type', $type)->update([
                'file_name' => $type.'.pdf',
                'file_path' => 'companies/'.$company->getKey().'/'.$type.'.pdf',
                'status' => $type === $pendingType
                    ? CompanyDocument::STATUS_PENDING
                    : CompanyDocument::STATUS_VALIDATED,
            ]);
        }
    }
}
