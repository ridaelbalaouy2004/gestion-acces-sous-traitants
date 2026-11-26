<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyMachinery;
use App\Models\ManagerSettings;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CompanyListImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        parent::tearDown();
    }

    public function test_provider_can_preview_and_import_employees_from_xlsx_only_after_confirmation(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire import employés');
        $otherProvider = $this->makeUserWithRole('prestataire', 'Prestataire autre import employés');
        $company = $this->makeCompany($provider, 'Entreprise import employés');
        $otherCompany = $this->makeCompany($otherProvider, 'Entreprise extérieure');
        $this->makeEmployee($otherCompany, 'Meryem', 'Alaoui', 'AB10001', 'Technicienne');
        $file = $this->spreadsheetUpload('liste-personnel.xlsx', [
            ['NOM', 'Prénom', 'N° CIN', 'Fonction', 'N° cadenas'],
            ['Amrani', 'Salma', 'ab10001', 'Technicienne', 'CAD-01'],
            ['Bennani', 'Nadia', 'CD10002', 'Conductrice', 'CAD-02'],
        ]);

        $this->actingAs($provider)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            ]), ['document' => $file])
            ->assertRedirect();

        $document = $company->documents()
            ->where('type', CompanyDocument::TYPE_LISTE_PERSONNEL)
            ->firstOrFail();
        $previewUrl = route('prestataire.companies.documents.import-preview', [$company, $document]);
        $previewResponse = $this->get($previewUrl)
            ->assertOk()
            ->assertSee('Aperçu de l’importation')
            ->assertSee($document->file_name)
            ->assertSee('N° CIN')
            ->assertSee('Salma')
            ->assertSee('Annuler');
        $this->assertMatchesRegularExpression(
            '/^liste-exhaustive-des-effectifs-sur-site-avec-numero-de-serie-de-cadenas-de-consignation-entreprise-import-employes-\d{8}\.xlsx$/',
            $document->file_name,
        );
        $this->assertStringContainsString(
            'title="'.$document->file_name.'"',
            $previewResponse->getContent(),
        );
        $previewResponse->assertViewHas('preview', function (array $preview): bool {
            return $preview['total_rows'] === 2
                && $preview['valid_rows'] === 2
                && $preview['invalid_rows'] === 0
                && $preview['mappings']['last_name'] === 'A'
                && $preview['mappings']['first_name'] === 'B'
                && $preview['mappings']['cin'] === 'C'
                && $preview['mappings']['locker_number'] === 'E';
        });
        $this->assertDatabaseCount('company_employees', 1);
        $this->assertDatabaseMissing('company_employees', [
            'company_id' => $company->getKey(),
            'cin' => 'AB10001',
        ]);
        Storage::disk('documents_private')->assertExists($document->file_path);

        $preview = $previewResponse->viewData('preview');
        $this->post(route('prestataire.companies.documents.import-confirm', [$company, $document]), [
            'action' => 'import',
            'source_hash' => $preview['source_hash'],
            'mapping' => $preview['mappings'],
            'selected_rows' => [2, 3],
        ])
            ->assertOk()
            ->assertViewIs('company-list-import.result')
            ->assertSee('2 employés créés');

        $employees = CompanyEmployee::query()->where('company_id', $company->getKey())->orderBy('cin')->get();
        $this->assertCount(2, $employees);
        $this->assertSame('AB10001', $employees[0]->cin);
        $this->assertSame('CAD-01', $employees[0]->locker_number);
        $this->assertSame('CD10002', $employees[1]->cin);
        $employees[0]->update(['position' => 'Fonction mise à jour en base']);
        $this->assertSame(CompanyEmployee::STATUS_DRAFT, $employees[0]->status);
        $this->assertSame(count(CompanyEmployeeDocument::allTypes()) * 2, $company->employees()->withCount('documents')->get()->sum('documents_count'));
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employees[0]->getKey(),
            'user_id' => $provider->getKey(),
            'action' => 'created',
        ]);

        $this->actingAs($provider)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Voir les données Excel');
        $this->actingAs($provider)
            ->get(route('prestataire.companies.documents.imported-data', [$company, $document]))
            ->assertOk()
            ->assertSee('Amrani')
            ->assertSee('Salma')
            ->assertSee('AB10001')
            ->assertSee('CAD-01')
            ->assertSee('Fonction mise à jour en base');
        $this->actingAs($otherProvider)
            ->get(route('prestataire.companies.documents.imported-data', [$company, $document]))
            ->assertNotFound();
    }

    public function test_ambiguous_identifier_header_stays_unmapped_until_the_user_confirms_its_mapping(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire colonnes ambiguës');
        $company = $this->makeCompany($provider, 'Entreprise colonnes ambiguës');
        $this->actingAs($provider)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            ]), [
                'document' => $this->spreadsheetUpload('identifiants.xlsx', [
                    ['Nom', 'Prénom', 'Identifiant', 'Poste'],
                    ['Idrissi', 'Youssef', 'EF12345', 'Électricien'],
                ]),
            ])
            ->assertRedirect();
        $document = $company->documents()->where('type', CompanyDocument::TYPE_LISTE_PERSONNEL)->firstOrFail();
        $previewUrl = route('prestataire.companies.documents.import-preview', [$company, $document]);

        $initialPreview = $this->get($previewUrl)
            ->assertOk()
            ->assertSee('Identifiant')
            ->assertSee('Colonne non reconnue');
        $initialPreview->assertViewHas('preview', fn (array $preview): bool => $preview['mappings']['cin'] === null
            && $preview['invalid_rows'] === 1);

        $preview = $initialPreview->viewData('preview');
        $previewUrl = route('prestataire.companies.documents.import-confirm', [$company, $document]);
        $mappedPreview = $this->post($previewUrl, [
            'action' => 'refresh',
            'source_hash' => $preview['source_hash'],
            'mapping' => [
                'last_name' => 'A',
                'first_name' => 'B',
                'cin' => 'C',
                'position' => 'D',
                'locker_number' => null,
            ],
        ])
            ->assertOk()
            ->assertViewIs('company-list-import.preview');
        $mappedPreview->assertViewHas('preview', fn (array $updatedPreview): bool => $updatedPreview['valid_rows'] === 1
            && $updatedPreview['mappings']['cin'] === 'C');
        $this->assertDatabaseCount('company_employees', 0);
    }

    public function test_outillage_list_import_creates_toolings_and_ignores_identifier_duplicates(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire import outillage');
        $company = $this->makeCompany($provider, 'Entreprise import outillage');
        $existing = $company->toolings()->create([
            'designation' => 'Clé existante',
            'category' => 'Clés',
            'internal_reference' => 'REF-OLD',
            'serial_number' => 'SER-OLD',
            'quantity' => 3,
        ]);
        $this->actingAs($provider)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => CompanyDocument::TYPE_LISTE_OUTILLAGE,
            ]), [
                'document' => $this->spreadsheetUpload('liste-outillage.xls', [
                    ['Désignation', 'Référence', 'N° de série', 'Marque', 'Quantité', 'Catégorie', 'Description'],
                    ['Clé dynamométrique', 'REF-001', 'SER-001', 'Facom', 2, 'Mesure', 'Lot A'],
                    ['Clé dynamométrique', 'REF-002', 'SER-002', 'Facom', 1, 'Mesure', 'Lot B'],
                    ['Autre clé', 'REF-OLD', 'SER-003', 'Beta', 4, 'Clés', 'Doublon de référence'],
                ], 'Xls'),
            ])
            ->assertRedirect();

        $document = $company->documents()->where('type', CompanyDocument::TYPE_LISTE_OUTILLAGE)->firstOrFail();
        $previewUrl = route('prestataire.companies.documents.import-preview', [$company, $document]);
        $previewResponse = $this->get($previewUrl)
            ->assertOk()
            ->assertSee('2 nouveaux enregistrements')
            ->assertSee('1 doublon');
        $previewResponse->assertViewHas('preview', fn (array $preview): bool => $preview['total_rows'] === 3
            && $preview['valid_rows'] === 2
            && $preview['duplicate_rows'] === 1
            && $preview['mappings']['internal_reference'] === 'B'
            && $preview['mappings']['serial_number'] === 'C');
        $this->assertDatabaseCount('company_toolings', 1);
        $preview = $previewResponse->viewData('preview');

        $this->post(route('prestataire.companies.documents.import-confirm', [$company, $document]), [
            'action' => 'import',
            'source_hash' => $preview['source_hash'],
            'mapping' => $preview['mappings'],
            'selected_rows' => [2, 3],
        ])
            ->assertOk()
            ->assertViewIs('company-list-import.result')
            ->assertSee('2 outillages créés')
            ->assertSee('1 doublon ignoré');

        $this->assertDatabaseCount('company_toolings', 3);
        $this->assertDatabaseHas('company_toolings', [
            'id' => $existing->getKey(),
            'designation' => 'Clé existante',
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('company_toolings', [
            'company_id' => $company->getKey(),
            'internal_reference' => 'REF-002',
            'serial_number' => 'SER-002',
        ]);
        $this->assertDatabaseMissing('company_toolings', [
            'company_id' => $company->getKey(),
            'serial_number' => 'SER-003',
        ]);
        $this->assertDatabaseHas('company_tooling_histories', [
            'user_id' => $provider->getKey(),
            'action' => 'created',
        ]);
    }

    public function test_material_list_import_creates_machinery_without_creating_toolings(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire import matériel');
        $company = $this->makeCompany($provider, 'Entreprise import matériel');
        $this->actingAs($provider)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => CompanyDocument::TYPE_LISTE_MATERIEL,
            ]), [
                'document' => $this->spreadsheetUpload('liste-materiel.xlsx', [
                    ['Équipement', 'Catégorie', 'Numéro de série', 'N° immatriculation', 'Affectation', 'Date mise en service'],
                    ['Compresseur', 'Air', 'MAT-001', 'IMM-001', 'Atelier', '2025-02-14'],
                ]),
            ])
            ->assertRedirect();

        $document = $company->documents()->where('type', CompanyDocument::TYPE_LISTE_MATERIEL)->firstOrFail();
        $previewUrl = route('prestataire.companies.documents.import-preview', [$company, $document]);
        $previewResponse = $this->get($previewUrl)
            ->assertOk()
            ->assertSee('1 nouvel équipement');
        $previewResponse->assertViewHas('preview', fn (array $preview): bool => $preview['mappings']['designation'] === 'A'
            && $preview['mappings']['serial_number'] === 'C'
            && $preview['mappings']['registration_number'] === 'D'
            && $preview['valid_rows'] === 1);
        $preview = $previewResponse->viewData('preview');

        $this->post(route('prestataire.companies.documents.import-confirm', [$company, $document]), [
            'action' => 'import',
            'source_hash' => $preview['source_hash'],
            'mapping' => $preview['mappings'],
            'selected_rows' => [2],
        ])
            ->assertOk()
            ->assertViewIs('company-list-import.result')
            ->assertSee('1 équipement créé');

        $machinery = CompanyMachinery::query()->where('company_id', $company->getKey())->sole();
        $this->assertSame('Compresseur', $machinery->designation);
        $this->assertSame('2025-02-14', $machinery->commissioned_at->format('Y-m-d'));
        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->status);
        $this->assertDatabaseCount('company_toolings', 0);
        $this->assertDatabaseHas('company_machinery_histories', [
            'company_machinery_id' => $machinery->getKey(),
            'user_id' => $provider->getKey(),
            'action' => 'created',
        ]);
    }

    public function test_invalid_employee_rows_are_reported_and_only_selected_valid_rows_are_imported(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire lignes invalides');
        $company = $this->makeCompany($provider, 'Entreprise lignes invalides');
        $this->actingAs($provider)
            ->post(route('prestataire.companies.documents.store', [
                'company' => $company,
                'documentType' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            ]), [
                'document' => $this->spreadsheetUpload('personnel-invalide.xlsx', [
                    ['Last Name', 'First Name', 'CIN', 'Job Title'],
                    ['Ligne', 'Invalide', '', 'Aide'],
                    ['Ligne', 'Valide', 'GH45678', 'Électricienne'],
                ]),
            ])
            ->assertRedirect();

        $document = $company->documents()->where('type', CompanyDocument::TYPE_LISTE_PERSONNEL)->firstOrFail();
        $previewUrl = route('prestataire.companies.documents.import-preview', [$company, $document]);
        $previewResponse = $this->get($previewUrl)
            ->assertOk()
            ->assertSee('Ligne Excel 2')
            ->assertSee('CIN obligatoire');
        $previewResponse->assertViewHas('preview', fn (array $preview): bool => $preview['total_rows'] === 2
            && $preview['valid_rows'] === 1
            && $preview['invalid_rows'] === 1);
        $this->assertDatabaseCount('company_employees', 0);
        $preview = $previewResponse->viewData('preview');

        $this->post(route('prestataire.companies.documents.import-confirm', [$company, $document]), [
            'action' => 'import',
            'source_hash' => $preview['source_hash'],
            'mapping' => $preview['mappings'],
            'selected_rows' => [3],
        ])
            ->assertOk()
            ->assertViewIs('company-list-import.result')
            ->assertSee('1 employé créé')
            ->assertSee('1 ligne rejetée');

        $this->assertDatabaseCount('company_employees', 1);
        $this->assertDatabaseHas('company_employees', [
            'company_id' => $company->getKey(),
            'cin' => 'GH45678',
        ]);
    }

    public function test_primary_manager_can_import_for_an_assigned_provider_and_secondary_manager_cannot(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire gestion déléguée import');
        $manager = $this->makePrimaryManager('Gestionnaire principal import');
        $manager->assignedPrestataires()->attach($provider, ['assigned_by' => $manager->getKey()]);
        $company = $this->makeCompany($provider, 'Entreprise gestion déléguée');
        $upload = $this->spreadsheetUpload('personnel-delegue.xlsx', [
            ['Nom', 'Prénom', 'CIN', 'Poste'],
            ['El Fassi', 'Mina', 'JK65432', 'Technicienne'],
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.delegated.companies.documents.store', [
                'prestataire' => $provider,
                'company' => $company,
                'type' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            ]), ['document' => $upload])
            ->assertRedirect();
        $document = $company->documents()->where('type', CompanyDocument::TYPE_LISTE_PERSONNEL)->firstOrFail();
        $previewUrl = route('gestionnaire.delegated.companies.documents.import-preview', [
            $provider,
            $company,
            $document,
        ]);
        $previewResponse = $this->get($previewUrl)->assertOk();
        $this->assertSame(
            $provider->getKey(),
            $previewResponse->viewData('delegatedPrestataire')->getKey(),
        );
        $preview = $previewResponse->viewData('preview');

        $resultResponse = $this->post(route('gestionnaire.delegated.companies.documents.import-confirm', [
            $provider,
            $company,
            $document,
        ]), [
            'action' => 'import',
            'source_hash' => $preview['source_hash'],
            'mapping' => $preview['mappings'],
            'selected_rows' => [2],
        ])->assertOk()->assertViewIs('company-list-import.result');
        $this->assertSame(
            $provider->getKey(),
            $resultResponse->viewData('delegatedPrestataire')->getKey(),
        );

        $employee = CompanyEmployee::query()->where('company_id', $company->getKey())->sole();
        $this->assertSame($provider->getKey(), $company->prestataire_id);
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'created',
        ]);
        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertSee('Voir les données Excel');
        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.documents.imported-data', [$company, $document]))
            ->assertOk()
            ->assertSee('El Fassi')
            ->assertSee('Mina')
            ->assertSee('JK65432');
        $this->actingAs($manager)
            ->get(route('gestionnaire.delegated.companies.show', [$provider, $company]))
            ->assertOk()
            ->assertSee('Voir les données Excel');
        $delegatedDataUrl = route('gestionnaire.delegated.companies.documents.imported-data', [
            $provider,
            $company,
            $document,
        ]);
        $this->actingAs($manager)
            ->get($delegatedDataUrl)
            ->assertOk()
            ->assertSee('El Fassi')
            ->assertSee('JK65432');

        $secondaryManager = $this->makeUserWithRole('gestionnaire', 'Gestionnaire secondaire import');
        $this->actingAs($secondaryManager)
            ->get($previewUrl)
            ->assertForbidden();
        $this->actingAs($secondaryManager)
            ->get($delegatedDataUrl)
            ->assertForbidden();
        $this->actingAs($secondaryManager)
            ->get(route('gestionnaire.companies.documents.imported-data', [$company, $document]))
            ->assertForbidden();
    }

    public function test_excel_data_button_is_hidden_for_non_imported_files_and_non_list_documents(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire fichiers non importés');
        $company = $this->makeCompany($provider, 'Entreprise fichiers non importés');
        $documents = [
            [CompanyDocument::TYPE_LISTE_PERSONNEL, 'liste-personnel.pdf', 'application/pdf'],
            [CompanyDocument::TYPE_LISTE_MATERIEL, 'liste-materiel.jpg', 'image/jpeg'],
            [CompanyDocument::TYPE_LISTE_OUTILLAGE, 'liste-outillage.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            [CompanyDocument::TYPE_ASSURANCE_RC, 'assurance.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];

        foreach ($documents as [$type, $fileName, $mimeType]) {
            $document = $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_PENDING,
                'file_name' => $fileName,
                'file_path' => 'companies/'.$company->getKey().'/'.$fileName,
                'mime_type' => $mimeType,
            ]);
            Storage::disk('documents_private')->put($document->file_path, 'test-file');
        }

        $this->actingAs($provider)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertDontSee('Voir les données Excel');
    }

    public function test_provider_cannot_preview_another_providers_list_and_corrupt_workbooks_show_a_clear_error(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeUserWithRole('prestataire', 'Prestataire import isolé');
        $otherProvider = $this->makeUserWithRole('prestataire', 'Prestataire import propriétaire');
        $company = $this->makeCompany($provider, 'Entreprise import isolé');
        $otherCompany = $this->makeCompany($otherProvider, 'Entreprise import propriétaire');
        $document = $otherCompany->documents()->create([
            'type' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            'label' => CompanyDocument::labelForType(CompanyDocument::TYPE_LISTE_PERSONNEL),
            'status' => CompanyDocument::STATUS_PENDING,
            'file_name' => 'corrompu.xlsx',
            'file_path' => 'companies/'.$otherCompany->getKey().'/corrompu.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
        Storage::disk('documents_private')->put($document->file_path, 'not an Excel workbook');

        $this->actingAs($provider)
            ->get(route('prestataire.companies.documents.import-preview', [$otherCompany, $document]))
            ->assertNotFound();

        $this->actingAs($otherProvider)
            ->get(route('prestataire.companies.documents.import-preview', [$otherCompany, $document]))
            ->assertOk()
            ->assertSee('Le fichier Excel est illisible ou endommagé.')
            ->assertSee('Annuler');
        $this->assertDatabaseCount('company_employees', 0);
    }

    private function makeUserWithRole(string $roleName, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function makePrimaryManager(string $name): User
    {
        $manager = $this->makeUserWithRole('gestionnaire', $name);

        ManagerSettings::query()->updateOrCreate(
            ['id' => ManagerSettings::SINGLETON_ID],
            ['principal_user_id' => $manager->getKey()],
        );

        return $manager;
    }

    private function makeCompany(User $provider, string $name): Company
    {
        return $provider->companies()->create([
            'name' => $name,
            'activity' => 'Construction',
            'assignment' => 'Chantier test',
            'status' => Company::STATUS_VALIDATED,
        ]);
    }

    private function makeEmployee(
        Company $company,
        string $firstName,
        string $lastName,
        string $cin,
        string $position,
    ): CompanyEmployee {
        return $company->employees()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'cin' => $cin,
            'position' => $position,
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
    }

    /**
     * @param  array<int, array<int, int|float|string|null>>  $rows
     */
    private function spreadsheetUpload(string $name, array $rows, string $format = 'Xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'company-list-import-');

        if (! is_string($path)) {
            throw new \RuntimeException('Impossible de créer le fichier Excel de test.');
        }

        $this->temporaryFiles[] = $path;
        $writer = $format === 'Xls'
            ? new Xls($spreadsheet)
            : new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $mimeType = $format === 'Xls'
            ? 'application/vnd.ms-excel'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return new UploadedFile($path, $name, $mimeType, UPLOAD_ERR_OK, true);
    }
}
