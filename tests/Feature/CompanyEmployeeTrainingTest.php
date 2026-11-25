<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyEmployeeTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestataire_can_add_multiple_trainings_with_private_certificates_for_one_employee(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire();
        $company = $this->makeCompany($prestataire);
        $employee = $this->makeEmployee($company);
        $otherEmployee = $this->makeEmployee($company, 'Autre');
        $cinDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CIN,
            'label' => CompanyEmployeeDocument::labelForType(CompanyEmployeeDocument::TYPE_CIN),
            'status' => CompanyEmployeeDocument::STATUS_DRAFT,
            'file_name' => 'cin-existant.pdf',
            'file_path' => 'company-employees/'.$employee->getKey().'/cin-existant.pdf',
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Aucune formation enregistrée pour cet employé.');

        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.trainings.store', $employee), [
                'title' => 'Premiers secours',
                'provider' => 'Centre de formation',
                'training_date' => '2025-03-10',
                'expires_at' => '2027-03-10',
                'description' => 'Formation initiale.',
                'certificate' => $this->pdf('secourisme.pdf'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHas('status', 'La formation a été ajoutée.');

        $this->post(route('prestataire.employees.trainings.store', $employee), [
            'title' => 'Travail en hauteur',
        ])->assertRedirect(route('prestataire.employees.show', $employee));

        $trainings = $employee->trainings()->with('versions')->reorder('id')->get();

        $this->assertCount(2, $trainings);
        $this->assertSame($employee->getKey(), $trainings[0]->company_employee_id);
        $this->assertSame('Centre de formation', $trainings[0]->provider);
        $this->assertSame(0, $otherEmployee->trainings()->count());
        $this->assertCount(1, $trainings[0]->versions);
        $this->assertSame(1, $trainings[0]->versions[0]->version);
        $this->assertMatchesRegularExpression(
            '/^certificat-premiers-secours-[a-z0-9-]+-\d{8}\.pdf$/',
            $trainings[0]->file_name,
        );
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->getKey(),
            'action' => 'ajout_formation',
        ]);
        $this->assertSame($cinDocument->getKey(), $employee->documents()->firstOrFail()->getKey());
        Storage::disk('documents_private')->assertExists($trainings[0]->file_path);
        Storage::disk('documents_private')->assertExists($trainings[0]->versions[0]->file_path);
        $this->assertStringStartsWith('company-employee-trainings/'.$employee->getKey().'/', $trainings[0]->file_path);
        $this->get(route('prestataire.employees.trainings.download', [$employee, $trainings[0]]))
            ->assertOk()
            ->assertDownload($trainings[0]->file_name);

        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Formations')
            ->assertSee('Ajouter une formation')
            ->assertSee('Premiers secours')
            ->assertSee('Travail en hauteur')
            ->assertSee('Organisme de formation')
            ->assertSee('name="expires_at"', false)
            ->assertSee('Certificat fourni')
            ->assertSee('Aucun certificat fourni')
            ->assertSee('Modifier')
            ->assertSee('Enregistrer les modifications')
            ->assertSee('Annuler')
            ->assertSee('Remplacer le justificatif')
            ->assertSee('Supprimer');
    }

    public function test_prestataire_renders_responsive_training_grid_for_zero_one_two_three_and_more_cards(): void
    {
        $prestataire = $this->makePrestataire();
        $employee = $this->makeEmployee($this->makeCompany($prestataire));
        $this->actingAs($prestataire);

        $response = $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Aucune formation enregistrée pour cet employé.');
        $html = $response->getContent();

        $this->assertStringContainsString(
            'grid-cols-1 gap-4 min-[480px]:grid-cols-2 md:grid-cols-3 2xl:grid-cols-4',
            $html,
        );
        $this->assertSame(0, substr_count($html, 'id="employee-training-card-'));

        $currentCardCount = 0;
        foreach ([1, 2, 3, 6] as $expectedCardCount) {
            while ($currentCardCount < $expectedCardCount) {
                $currentCardCount++;
                $employee->trainings()->create(['title' => 'Formation '.$currentCardCount]);
            }

            $response = $this->get(route('prestataire.employees.show', $employee))
                ->assertOk()
                ->assertSee('Ajouter une formation');
            $html = $response->getContent();

            $this->assertSame($expectedCardCount, substr_count($html, 'id="employee-training-card-'));
            $this->assertSame($expectedCardCount, substr_count($html, 'Aucun certificat fourni'));
            $this->assertSame($expectedCardCount, substr_count($html, 'data-training-edit-open'));
            $this->assertSame($expectedCardCount, substr_count($html, 'data-training-edit-cancel'));

            foreach ($employee->trainings()->pluck('id') as $trainingId) {
                $formMatch = preg_match(
                    '/<form\b[^>]*\bid="training-edit-form-'.$trainingId.'"[^>]*>/s',
                    $html,
                    $formTag,
                );
                $this->assertSame(1, $formMatch);
                $this->assertStringContainsString('hidden', $formTag[0]);
                $this->assertStringContainsString(
                    'aria-controls="training-edit-form-'.$trainingId.'"',
                    $html,
                );
            }
        }
    }

    public function test_validation_error_reopens_only_the_edited_training_form_with_its_old_values(): void
    {
        $prestataire = $this->makePrestataire();
        $employee = $this->makeEmployee($this->makeCompany($prestataire));
        $firstTraining = $employee->trainings()->create([
            'title' => 'Formation A',
            'provider' => 'Organisme A',
        ]);
        $secondTraining = $employee->trainings()->create([
            'title' => 'Formation B',
            'provider' => 'Organisme B',
        ]);

        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.show', $employee))
            ->put(route('prestataire.employees.trainings.update', [$employee, $firstTraining]), [
                '_training_edit_id' => $firstTraining->getKey(),
                'title' => '',
                'provider' => 'Organisme modifié',
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHasErrors('title');

        $html = $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->getContent();

        $firstFormMatch = preg_match(
            '/<form\b[^>]*\bid="training-edit-form-'.$firstTraining->getKey().'"[^>]*>/s',
            $html,
            $firstFormTag,
        );
        $secondFormMatch = preg_match(
            '/<form\b[^>]*\bid="training-edit-form-'.$secondTraining->getKey().'"[^>]*>/s',
            $html,
            $secondFormTag,
        );
        $firstTitleInputMatch = preg_match(
            '/<input\b[^>]*\bid="training-title-'.$firstTraining->getKey().'"[^>]*>/s',
            $html,
            $firstTitleInput,
        );
        $firstProviderInputMatch = preg_match(
            '/<input\b[^>]*\bid="training-provider-'.$firstTraining->getKey().'"[^>]*>/s',
            $html,
            $firstProviderInput,
        );
        $secondTitleInputMatch = preg_match(
            '/<input\b[^>]*\bid="training-title-'.$secondTraining->getKey().'"[^>]*>/s',
            $html,
            $secondTitleInput,
        );
        $secondProviderInputMatch = preg_match(
            '/<input\b[^>]*\bid="training-provider-'.$secondTraining->getKey().'"[^>]*>/s',
            $html,
            $secondProviderInput,
        );

        $this->assertSame(1, $firstFormMatch);
        $this->assertSame(1, $secondFormMatch);
        $this->assertSame(1, $firstTitleInputMatch);
        $this->assertSame(1, $firstProviderInputMatch);
        $this->assertSame(1, $secondTitleInputMatch);
        $this->assertSame(1, $secondProviderInputMatch);
        $this->assertStringNotContainsString('hidden', $firstFormTag[0]);
        $this->assertStringContainsString('hidden', $secondFormTag[0]);
        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringContainsString('value=""', $firstTitleInput[0]);
        $this->assertStringContainsString('value="Organisme modifié"', $firstProviderInput[0]);
        $this->assertStringContainsString('value="Formation B"', $secondTitleInput[0]);
        $this->assertStringContainsString('value="Organisme B"', $secondProviderInput[0]);
    }

    public function test_prestataire_can_update_replace_and_soft_delete_training_while_preserving_versions_and_hse_documents(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire();
        $company = $this->makeCompany($prestataire);
        $employee = $this->makeEmployee($company);
        $cinDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CIN,
            'label' => CompanyEmployeeDocument::labelForType(CompanyEmployeeDocument::TYPE_CIN),
            'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
            'file_name' => 'cin.pdf',
            'file_path' => 'company-employees/'.$employee->getKey().'/cin.pdf',
        ]);

        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.trainings.store', $employee), [
                'title' => 'HSE',
                'certificate' => $this->pdf('hse-v1.pdf'),
            ])
            ->assertRedirect();
        $training = $employee->trainings()->firstOrFail();
        $firstVersion = $training->versions()->firstOrFail();

        $this->put(route('prestataire.employees.trainings.update', [$employee, $training]), [
            'title' => 'Formation HSE avancée',
            'provider' => 'Organisme agréé',
            'training_date' => '2025-01-15',
            'expires_at' => '2028-01-15',
            'description' => 'Mise à jour des compétences.',
            'certificate' => $this->pdf('hse-v2.pdf'),
        ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHas('status', 'La formation a été mise à jour.');

        $training->refresh();
        $versions = $training->versions()->orderBy('version')->get();
        $this->assertSame('Formation HSE avancée', $training->title);
        $this->assertCount(2, $versions);
        $this->assertFalse($versions[0]->is_current);
        $this->assertTrue($versions[1]->is_current);
        $this->get(route('prestataire.employees.trainings.versions.download', [
            $employee,
            $training,
            $firstVersion,
        ]))->assertOk()->assertDownload($firstVersion->file_name);
        Storage::disk('documents_private')->assertExists($firstVersion->file_path);
        Storage::disk('documents_private')->assertExists($versions[1]->file_path);

        $this->delete(route('prestataire.employees.trainings.destroy', [$employee, $training]))
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHas('status', 'La formation a été supprimée.');

        $this->assertSoftDeleted('company_employee_trainings', ['id' => $training->getKey()]);
        $this->assertDatabaseHas('company_employee_training_versions', [
            'id' => $firstVersion->getKey(),
            'file_path' => $firstVersion->file_path,
        ]);
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->getKey(),
            'action' => 'suppression_formation',
        ]);
        $this->assertModelExists($cinDocument);
        $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $cinDocument->fresh()->status);
        Storage::disk('documents_private')->assertExists($firstVersion->file_path);
        Storage::disk('documents_private')->assertExists($versions[1]->file_path);
    }

    public function test_prestataire_cannot_upload_an_unapproved_file_type_or_a_file_over_ten_megabytes(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->makePrestataire();
        $employee = $this->makeEmployee($this->makeCompany($prestataire));

        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.show', $employee))
            ->post(route('prestataire.employees.trainings.store', $employee), [
                'title' => 'Formation non autorisée',
                'certificate' => UploadedFile::fake()->create('script.html', 20, 'text/html'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHasErrors('certificate');

        $this->post(route('prestataire.employees.trainings.store', $employee), [
            'title' => 'Formation trop volumineuse',
            'certificate' => UploadedFile::fake()->create('trop-grand.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('certificate', 'Le justificatif ne peut pas dépasser 10 Mo.');
        $this->post(route('prestataire.employees.trainings.store', $employee), [
            'title' => 'Date invalide',
            'expires_at' => 'pas-une-date',
        ])->assertSessionHasErrors('expires_at');

        $this->assertSame(0, $employee->trainings()->count());
        $this->assertDatabaseCount('company_employee_training_versions', 0);
    }

    public function test_training_routes_are_scoped_to_the_employee_and_the_provider_company(): void
    {
        Storage::fake('documents_private');
        $owner = $this->makePrestataire();
        $otherPrestataire = $this->makePrestataire('other-provider@example.test');
        $ownedCompany = $this->makeCompany($owner);
        $otherCompany = $this->makeCompany($owner, 'Entreprise secondaire');
        $foreignCompany = $this->makeCompany($otherPrestataire, 'Entreprise externe');
        $employee = $this->makeEmployee($ownedCompany);
        $sameProviderEmployee = $this->makeEmployee($otherCompany, 'Secondaire');
        $foreignEmployee = $this->makeEmployee($foreignCompany, 'Externe');

        $this->actingAs($owner)
            ->post(route('prestataire.employees.trainings.store', $employee), [
                'title' => 'Formation privée',
                'certificate' => $this->pdf('prive.pdf'),
            ])->assertRedirect();
        $training = $employee->trainings()->firstOrFail();

        $this->get(route('prestataire.employees.trainings.download', [$sameProviderEmployee, $training]))
            ->assertNotFound();
        $this->get(route('prestataire.employees.trainings.download', [$foreignEmployee, $training]))
            ->assertNotFound();
        $this->put(route('prestataire.employees.trainings.update', [$foreignEmployee, $training]), [
            'title' => 'Tentative de transfert',
        ])->assertNotFound();
        $this->assertSame('Formation privée', $training->fresh()->title);
    }

    public function test_manager_can_view_assigned_employee_trainings_and_download_only_with_existing_permission(): void
    {
        Storage::fake('documents_private');
        $this->travelTo(now()->setDate(2025, 6, 1));
        $prestataire = $this->makePrestataire();
        $manager = $this->makeManager($prestataire, ['employees.view', 'employees.download']);
        $company = $this->makeCompany($prestataire);
        $employee = $this->makeEmployee($company);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $training = $employee->trainings()->create([
            'title' => 'Sécurité incendie',
            'provider' => 'Institut de sécurité',
            'training_date' => '2024-06-01',
            'expires_at' => '2025-01-01',
            'description' => 'Formation annuelle.',
            'file_name' => 'incendie.pdf',
            'file_path' => 'company-employee-trainings/'.$employee->getKey().'/incendie.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('documents_private')->put($training->file_path, "%PDF-1.4\ntraining certificate");

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Formations')
            ->assertSee('Sécurité incendie')
            ->assertSee('Institut de sécurité')
            ->assertSee('01/01/2025')
            ->assertSee('Validité expirée')
            ->assertDontSee('Ajouter une formation');

        $this->get(route('gestionnaire.employees.trainings.download', [$employee, $training]))
            ->assertOk()
            ->assertDownload('incendie.pdf');

        $managerWithoutDownload = $this->makeManager(
            $prestataire,
            ['employees.view'],
            'limited-manager@example.test',
        );
        $this->actingAs($managerWithoutDownload)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Sécurité incendie')
            ->assertDontSee(route('gestionnaire.employees.trainings.download', [$employee, $training]));
        $this->get(route('gestionnaire.employees.trainings.download', [$employee, $training]))
            ->assertForbidden();
        $this->post('/gestionnaire/gestion-equipes/'.$employee->getKey().'/formations', [
            'title' => 'Modification interdite',
        ])->assertNotFound();

        $otherPrestataire = $this->makePrestataire('other-provider@example.test');
        $unassignedManager = $this->makeManager(
            $otherPrestataire,
            ['employees.view', 'employees.download'],
            'unassigned-manager@example.test',
        );
        $this->actingAs($unassignedManager)
            ->get(route('gestionnaire.employees.trainings.download', [$employee, $training]))
            ->assertNotFound();
    }

    public function test_training_and_expired_training_do_not_change_employee_hse_submission_or_approval_rules(): void
    {
        Storage::fake('documents_private');
        $this->travelTo(now()->setDate(2025, 6, 1));
        $prestataire = $this->makePrestataire();
        $employee = $this->makeEmployee($this->makeCompany($prestataire));

        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $employee->documents()->create([
                'type' => $type,
                'label' => CompanyEmployeeDocument::labelForType($type),
                'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => 'company-employees/'.$employee->getKey().'/'.$type.'.pdf',
                'expires_at' => '2026-01-01',
            ]);
        }

        $this->assertTrue($employee->isReadyForSubmission());
        $this->assertSame([], $employee->approvalBlockers());

        $employee->trainings()->create([
            'title' => 'Formation arrivée à expiration',
            'expires_at' => '2024-01-01',
        ]);

        $employee->refresh();
        $this->assertTrue($employee->isReadyForSubmission());
        $this->assertSame([], $employee->approvalBlockers());
        $this->assertDatabaseCount('company_employee_documents', count(CompanyEmployeeDocument::requiredTypes()));
    }

    private function makePrestataire(string $email = 'provider@example.test'): User
    {
        $this->ensureAccessCatalog();
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach(Role::query()->where('name', 'prestataire')->firstOrFail());

        return $user;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeManager(
        User $prestataire,
        array $permissions,
        string $email = 'manager@example.test',
    ): User {
        $this->ensureAccessCatalog();
        $manager = User::factory()->create(['email' => $email]);
        $manager->roles()->attach(Role::query()->where('name', 'gestionnaire')->firstOrFail());

        if ($permissions !== []) {
            $manager->permissions()->attach(
                Permission::query()->whereIn('name', $permissions)->pluck('id'),
            );
        }

        $manager->assignedPrestataires()->attach($prestataire, [
            'assigned_by' => $manager->getKey(),
        ]);

        return $manager;
    }

    private function makeCompany(User $prestataire, string $name = 'Entreprise formations'): Company
    {
        return Company::query()->create([
            'prestataire_id' => $prestataire->getKey(),
            'name' => $name,
            'activity' => 'Construction',
            'assignment' => 'Chantier principal',
            'status' => Company::STATUS_VALIDATED,
        ]);
    }

    private function makeEmployee(Company $company, string $lastName = 'Test'): CompanyEmployee
    {
        static $counter = 0;
        $counter++;

        return $company->employees()->create([
            'first_name' => 'Employé',
            'last_name' => $lastName,
            'cin' => 'TR'.str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
    }

    private function pdf(string $fileName): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $fileName,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
        );
    }

    private function ensureAccessCatalog(): void
    {
        foreach (['prestataire', 'gestionnaire'] as $name) {
            Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => ucfirst($name)],
            );
        }

        foreach (Permission::CATALOG as $name => $attributes) {
            Permission::query()->firstOrCreate(['name' => $name], $attributes);
        }
    }
}
