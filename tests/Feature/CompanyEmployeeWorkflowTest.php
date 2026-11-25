<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyEmployeeDocumentVersion;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SafetyTeamMember;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyEmployeeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('documents_private');
    }

    public function test_prestataire_can_create_multiple_employees_and_cin_is_unique_per_company(): void
    {
        $prestataire = $this->makePrestataire('Prestataire Alpha');
        $firstCompany = $this->makeCompany($prestataire, 'Entreprise Alpha');
        $secondCompany = $this->makeCompany($prestataire, 'Entreprise Beta');

        $firstEmployee = $this->createEmployee($prestataire, $firstCompany, [
            'first_name' => 'Salma',
            'last_name' => 'Amrani',
            'cin' => 'ab12345',
            'position' => 'Technicienne',
        ]);
        $this->createEmployee($prestataire, $firstCompany, [
            'first_name' => 'Youssef',
            'last_name' => 'Idrissi',
            'cin' => 'CD98765',
            'position' => 'Conducteur',
        ]);
        $sameCinOtherCompany = $this->createEmployee($prestataire, $secondCompany, [
            'first_name' => 'Salma',
            'last_name' => 'Amrani',
            'cin' => 'AB12345',
            'position' => 'Technicienne',
        ]);

        $this->assertSame($firstCompany->id, $firstEmployee->company_id);
        $this->assertSame($secondCompany->id, $sameCinOtherCompany->company_id);
        $this->assertSame('AB12345', $firstEmployee->cin);
        $this->assertCount(2, $firstCompany->employees);
        $this->assertCount(1, $secondCompany->employees);
        $this->assertCount(8, $firstEmployee->documents);

        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.create'))
            ->post(route('prestataire.employees.store'), [
                'company_id' => $firstCompany->id,
                'first_name' => 'Autre',
                'last_name' => 'Personne',
                'cin' => 'ab12345',
                'position' => 'Agent',
            ])
            ->assertRedirect(route('prestataire.employees.create'))
            ->assertSessionHasErrors('cin');

        $this->assertSame(2, $firstCompany->employees()->count());
    }

    public function test_prestataire_can_create_an_employee_with_a_private_photo_visible_in_the_team_and_employee_views(): void
    {
        $prestataire = $this->makePrestataire('Prestataire photo');
        $company = $this->makeCompany($prestataire, 'Entreprise photo');
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Salma',
            'last_name' => 'Amrani',
            'profile_photo' => $this->profilePhoto(),
        ]);
        $photoPath = $employee->profile_photo_path;

        $this->assertIsString($photoPath);
        $this->assertStringStartsWith('company-employees/'.$employee->getKey().'/profile/', $photoPath);
        $this->assertDatabaseHas('company_employees', [
            'id' => $employee->getKey(),
            'profile_photo_path' => $photoPath,
        ]);
        Storage::disk('documents_private')->assertExists($photoPath);

        $photoUrl = route('prestataire.employees.photo', $employee);
        $teamResponse = $this->actingAs($prestataire)
            ->get(route('prestataire.employees.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false)
            ->assertSee('alt="Photo de Salma Amrani"', false)
            ->assertDontSee($photoPath);
        $teamHtml = $teamResponse->getContent();
        $employeeCellMatch = preg_match(
            '/<td class="px-5 py-4 text-sm font-semibold text-slate-900">(.*?)<\/td>/s',
            $teamHtml,
            $employeeCell,
        );

        $this->assertSame(6, substr_count($teamHtml, '<th class='));
        $this->assertSame(1, $employeeCellMatch);
        $this->assertStringContainsString('gap-3', $employeeCell[1]);
        $this->assertStringContainsString('src="'.$photoUrl.'"', $employeeCell[1]);
        $this->assertStringContainsString('Salma Amrani', $employeeCell[1]);
        $this->assertStringContainsString('size-11 shrink-0 rounded-full object-cover', $employeeCell[1]);
        $this->assertStringContainsString('data-profile-photo-error-fallback', $employeeCell[1]);
        $this->assertStringContainsString('shrink-0 whitespace-nowrap', $employeeCell[1]);

        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false);
        $photoResponse = $this->get($photoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(
            Storage::disk('documents_private')->get($photoPath),
            $photoResponse->streamedContent(),
        );
    }

    public function test_employee_without_a_photo_gets_a_default_avatar_in_the_team_and_employee_views(): void
    {
        $prestataire = $this->makePrestataire('Prestataire sans photo');
        $employee = $this->createEmployee(
            $prestataire,
            $this->makeCompany($prestataire, 'Entreprise sans photo'),
            ['first_name' => 'Nadia', 'last_name' => 'Bennani'],
        );

        $this->actingAs($prestataire)
            ->get(route('prestataire.employees.index'))
            ->assertOk()
            ->assertSee('data-profile-photo-placeholder', false)
            ->assertSee('aria-label="Aucune photo pour Nadia Bennani"', false)
            ->assertDontSee('src="'.route('prestataire.employees.photo', $employee).'"', false);

        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('data-profile-photo-placeholder', false);
        $this->get(route('prestataire.employees.photo', $employee))->assertNotFound();
    }

    public function test_prestataire_can_replace_and_remove_an_employee_photo_without_deleting_a_shared_file(): void
    {
        $prestataire = $this->makePrestataire('Prestataire remplacement photo');
        $company = $this->makeCompany($prestataire, 'Entreprise remplacement photo');
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Youssef',
            'last_name' => 'Idrissi',
            'profile_photo' => $this->profilePhoto('premiere-photo.png'),
        ]);
        $firstPhotoPath = $employee->profile_photo_path;

        $this->actingAs($prestataire)
            ->put(route('prestataire.employees.update', $employee), [
                'first_name' => 'Youssef',
                'last_name' => 'Idrissi',
                'profile_photo' => $this->profilePhoto('photo-remplacee.png'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee));

        $replacementPhotoPath = $employee->fresh()->profile_photo_path;
        $this->assertNotSame($firstPhotoPath, $replacementPhotoPath);
        Storage::disk('documents_private')->assertMissing($firstPhotoPath);
        Storage::disk('documents_private')->assertExists($replacementPhotoPath);

        $otherEmployee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Employée',
            'last_name' => 'Secondaire',
        ]);
        $otherEmployee->update(['profile_photo_path' => $replacementPhotoPath]);

        $this->put(route('prestataire.employees.update', $employee), [
            'first_name' => 'Youssef',
            'last_name' => 'Idrissi',
            'remove_profile_photo' => '1',
        ])->assertRedirect(route('prestataire.employees.show', $employee));

        $this->assertNull($employee->fresh()->profile_photo_path);
        $this->assertSame($replacementPhotoPath, $otherEmployee->fresh()->profile_photo_path);
        Storage::disk('documents_private')->assertExists($replacementPhotoPath);

        $this->put(route('prestataire.employees.update', $employee), [
            'first_name' => 'Youssef',
            'last_name' => 'Idrissi',
            'profile_photo' => $this->profilePhoto('photo-a-supprimer.png'),
        ])->assertRedirect(route('prestataire.employees.show', $employee));
        $photoToRemove = $employee->fresh()->profile_photo_path;

        $this->put(route('prestataire.employees.update', $employee), [
            'first_name' => 'Youssef',
            'last_name' => 'Idrissi',
            'remove_profile_photo' => '1',
        ])->assertRedirect(route('prestataire.employees.show', $employee));

        $this->assertNull($employee->fresh()->profile_photo_path);
        Storage::disk('documents_private')->assertMissing($photoToRemove);
    }

    public function test_employee_photo_upload_rejects_unsupported_and_over_five_megabyte_files(): void
    {
        $prestataire = $this->makePrestataire('Prestataire validation photo');
        $employee = $this->createEmployee(
            $prestataire,
            $this->makeCompany($prestataire, 'Entreprise validation photo'),
            ['first_name' => 'Imane'],
        );

        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.edit', $employee))
            ->put(route('prestataire.employees.update', $employee), [
                'first_name' => 'Imane',
                'profile_photo' => UploadedFile::fake()->create('photo.gif', 20, 'image/gif'),
            ])
            ->assertRedirect(route('prestataire.employees.edit', $employee))
            ->assertSessionHasErrors([
                'profile_photo' => 'La photo doit être au format JPG, JPEG, PNG ou WEBP.',
            ]);

        $this->put(route('prestataire.employees.update', $employee), [
            'first_name' => 'Imane',
            'profile_photo' => UploadedFile::fake()->create('photo-trop-grande.png', 5121, 'image/png'),
        ])->assertSessionHasErrors([
            'profile_photo' => 'La photo ne peut pas dépasser 5 Mo.',
        ]);

        $this->assertNull($employee->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('documents_private')->allFiles('company-employees'));
    }

    public function test_employee_photo_access_respects_provider_ownership_and_manager_view_permission(): void
    {
        $owner = $this->makePrestataire('Prestataire propriétaire photo');
        $otherPrestataire = $this->makePrestataire('Autre prestataire photo');
        $company = $this->makeCompany($owner, 'Entreprise propriétaire photo');
        $employee = $this->createEmployee($owner, $company, [
            'first_name' => 'Samir',
            'profile_photo' => $this->profilePhoto(),
        ]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);

        $this->actingAs($otherPrestataire)
            ->get(route('prestataire.employees.photo', $employee))
            ->assertNotFound();

        $manager = $this->makeManager('Gestionnaire photo', ['employees.view']);
        $this->assignPrestataireToManager($manager, $owner);
        $managerPhotoUrl = route('gestionnaire.employees.photo', $employee);

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('src="'.$managerPhotoUrl.'"', false);
        $this->get($managerPhotoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('prestataire.employees.photo', $employee))->assertForbidden();

        $managerWithoutViewPermission = $this->makeManager('Gestionnaire sans lecture photo', []);
        $this->assignPrestataireToManager($managerWithoutViewPermission, $owner);
        $this->actingAs($managerWithoutViewPermission)
            ->get($managerPhotoUrl)
            ->assertForbidden();
    }

    public function test_prestataire_cannot_access_another_provider_employee_or_transfer_employee_between_companies(): void
    {
        $owner = $this->makePrestataire('Propriétaire');
        $otherPrestataire = $this->makePrestataire('Autre Prestataire');
        $ownerCompany = $this->makeCompany($owner, 'Entreprise propriétaire');
        $otherCompany = $this->makeCompany($owner, 'Autre entreprise');
        $foreignCompany = $this->makeCompany($otherPrestataire, 'Entreprise étrangère');
        $employee = $this->createEmployee($owner, $ownerCompany, [
            'first_name' => 'Nadia',
            'last_name' => 'Bennani',
            'cin' => 'EF12345',
            'position' => 'Agent',
        ]);
        $foreignEmployee = $this->createEmployee($otherPrestataire, $foreignCompany, [
            'first_name' => 'Employé',
            'last_name' => 'Privé',
            'cin' => 'GH12345',
            'position' => 'Agent',
        ]);

        $this->actingAs($otherPrestataire)
            ->get(route('prestataire.employees.show', $employee))
            ->assertNotFound();

        $this->actingAs($owner)
            ->from(route('prestataire.employees.edit', $employee))
            ->put(route('prestataire.employees.update', $employee), [
                'company_id' => $otherCompany->id,
                'first_name' => 'Nadia',
                'last_name' => 'Bennani',
                'cin' => 'EF12345',
                'position' => 'Agent',
            ])
            ->assertRedirect(route('prestataire.employees.edit', $employee))
            ->assertSessionHasErrors('company_id');

        $this->assertSame($ownerCompany->id, $employee->fresh()->company_id);
        $this->actingAs($owner)
            ->get(route('prestataire.employees.show', $foreignEmployee))
            ->assertNotFound();
    }

    public function test_submission_requires_information_and_documents_without_changing_company_status(): void
    {
        $prestataire = $this->makePrestataire('Prestataire soumission');
        $manager = $this->makeManager('Gestionnaire affecté');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise validée', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company);

        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.show', $employee))
            ->post(route('prestataire.employees.submit', $employee))
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHasErrors('employee');

        $this->actingAs($prestataire)
            ->put(route('prestataire.employees.update', $employee), [
                'first_name' => 'Imane',
                'last_name' => 'Berrada',
                'cin' => 'IJ12345',
                'position' => 'Électricienne',
                'locker_number' => null,
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee));

        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $this->uploadDocument($prestataire, $employee, $type);
        }

        $this->actingAs($prestataire)
            ->get(route('prestataire.employees.index'))
            ->assertOk()
            ->assertSee('Imane Berrada')
            ->assertSee('data-navigate-to="'.route('prestataire.employees.show', $employee).'"', false)
            ->assertSee('Consulter');
        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Documents de l’employé');

        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.submit', $employee))
            ->assertRedirect(route('prestataire.employees.show', $employee));

        $employee->refresh();
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->status);
        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $this->assertSame(
                CompanyEmployeeDocument::STATUS_PENDING,
                $employee->documents()->where('type', $type)->value('status'),
            );
        }
        $this->assertSame(
            CompanyEmployeeDocument::STATUS_DRAFT,
            $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_PERMIT)->value('status'),
        );
        $this->assertSame(1, $manager->notifications()
            ->where('data->event', CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED)
            ->count());

        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nouvel employé à examiner')
            ->assertSee(route('gestionnaire.employees.show', $employee));
    }

    public function test_employee_can_be_submitted_without_optional_permit_at_or_habilitations(): void
    {
        $prestataire = $this->makePrestataire('Prestataire documents facultatifs absents');
        $company = $this->makeCompany($prestataire, 'Entreprise documents facultatifs absents', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Rim',
            'last_name' => 'El Mansouri',
            'cin' => 'OP24680',
            'position' => 'Agent HSE',
        ]);

        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $this->uploadDocument($prestataire, $employee, $type);
        }

        $this->actingAs($prestataire)
            ->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Permis')
            ->assertSee('AT nominative')
            ->assertSee('Habilitations')
            ->assertSee('Facultatif');

        $this->post(route('prestataire.employees.submit', $employee))
            ->assertRedirect(route('prestataire.employees.show', $employee));

        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        foreach (CompanyEmployeeDocument::optionalTypes() as $type) {
            $this->assertSame(
                CompanyEmployeeDocument::STATUS_DRAFT,
                $employee->documents()->where('type', $type)->value('status'),
            );
        }
    }

    public function test_document_replacement_preserves_private_versions_and_checks_owner_on_download(): void
    {
        $prestataire = $this->makePrestataire('Prestataire fichiers');
        $otherPrestataire = $this->makePrestataire('Prestataire externe');
        $manager = $this->makeManager('Téléchargeur');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise fichiers');
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Hajar',
            'last_name' => 'El Fassi',
            'cin' => 'KL12345',
            'position' => 'Comptable',
        ]);
        $otherEmployee = $this->createEmployee($otherPrestataire, $this->makeCompany($otherPrestataire, 'Entreprise externe'), [
            'first_name' => 'Autre',
            'last_name' => 'Employé',
            'cin' => 'MN12345',
            'position' => 'Agent',
        ]);

        $document = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CIN)->firstOrFail();
        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.show', $employee))
            ->post(route('prestataire.employees.documents.store', [$employee, $document->type]), [
                'document' => UploadedFile::fake()->create('not-allowed.txt', 100, 'text/plain'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHasErrors('document');
        $this->post(route('prestataire.employees.documents.store', [$employee, $document->type]), [
            'document' => UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('document');
        $this->assertSame(0, $document->versions()->count());

        $this->uploadDocument($prestataire, $employee, $document->type, 'cin-v1.pdf');
        $firstVersion = $document->versions()->sole();
        $this->uploadDocument($prestataire, $employee, $document->type, 'cin-v2.pdf');
        $document->refresh();
        $versions = $document->versions()->orderBy('version')->get();

        $this->assertCount(2, $versions);
        $this->assertFalse($versions[0]->is_current);
        $this->assertTrue($versions[1]->is_current);
        Storage::disk('documents_private')->assertExists($firstVersion->file_path);
        Storage::disk('documents_private')->assertExists($versions[1]->file_path);

        $this->actingAs($prestataire)
            ->get(route('prestataire.employees.documents.download', [$employee, $document]))
            ->assertOk()
            ->assertDownload($document->file_name);
        $this->get(route('prestataire.employees.documents.versions.download', [$employee, $document, $firstVersion]))
            ->assertOk()
            ->assertDownload($firstVersion->file_name);
        $this->get(route('prestataire.employees.documents.download', [$otherEmployee, $document]))
            ->assertNotFound();

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.documents.versions.download', [$employee, $document, $firstVersion]))
            ->assertOk();

        $this->assertSame(2, CompanyEmployeeDocumentVersion::query()->count());
    }

    public function test_employee_document_upload_shows_persistent_feedback_without_inline_image(): void
    {
        $prestataire = $this->makePrestataire('Prestataire aperçu document');
        $otherPrestataire = $this->makePrestataire('Prestataire externe aperçu');
        $employee = $this->createEmployee(
            $prestataire,
            $this->makeCompany($prestataire, 'Entreprise aperçu document'),
            ['first_name' => 'Nadia', 'last_name' => 'Bennani'],
        );
        $document = $employee->documents()
            ->where('type', CompanyEmployeeDocument::TYPE_ANTHROPOMETRIC_SHEET)
            ->firstOrFail();

        $this->actingAs($prestataire)
            ->from(route('prestataire.employees.show', $employee))
            ->post(route('prestataire.employees.documents.store', [$employee, $document->type]), [
                'document' => UploadedFile::fake()->create('fiche-invalide.txt', 100, 'text/plain'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHasErrors('document');

        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertDontSee('data-file-uploaded-state', false);

        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.documents.store', [$employee, $document->type]), [
                'document' => $this->profilePhoto('fiche-anthropometrique.png'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee))
            ->assertSessionHas('status');

        $document->refresh();
        $this->assertMatchesRegularExpression(
            '/^fiche-anthropometrique-[a-z0-9-]+-\d{8}\.png$/',
            $document->file_name,
        );
        $previewUrl = route('prestataire.employees.documents.preview', [$employee, $document]);
        $showResponse = $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('data-file-uploaded-state', false)
            ->assertSee('Fichier déposé')
            ->assertSee($document->file_name)
            ->assertSee('href="'.$previewUrl.'"', false)
            ->assertSee('Fiche anthropométrique')
            ->assertSee('Aperçu');
        $showHtml = $showResponse->getContent();
        $this->assertStringNotContainsString('src="'.$previewUrl.'"', $showHtml);
        $this->assertStringNotContainsString('data-document-image-preview', $showHtml);
        $this->assertStringNotContainsString($document->file_path, $showHtml);
        $this->assertStringContainsString('title="'.$document->file_name.'"', $showHtml);
        $this->assertStringContainsString('truncate max-w-[200px] sm:max-w-xs block', $showHtml);
        $this->assertStringContainsString('Télécharger le document', $showHtml);
        $this->assertStringContainsString('Retirer le fichier courant', $showHtml);
        Storage::disk('documents_private')->assertExists($document->file_path);
        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Fichier déposé')
            ->assertSee($document->file_name);

        $imageResponse = $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'max-age=0');
        $this->assertSame(
            Storage::disk('documents_private')->get($document->file_path),
            $imageResponse->streamedContent(),
        );

        $this->actingAs($otherPrestataire)->get($previewUrl)->assertNotFound();

        $jpegContents = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEA8WFhUVFRUVFRUVFRUVFRUVFRUWFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy8lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAABAR/9oADAMBAAIQAxAAAAH/xAAVEAEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAQABBQJ//8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oACAEDAQE/AYf/xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAgEBPwGP/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oACAEDAQE/EH//xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
            true,
        );
        $this->assertIsString($jpegContents);
        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.documents.store', [$employee, $document->type]), [
                'document' => UploadedFile::fake()->createWithContent('fiche-remplacee.jpg', $jpegContents),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee));

        $document->refresh();
        $this->assertSame('image/jpeg', $document->mime_type);
        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Fichier déposé')
            ->assertSee($document->file_name)
            ->assertDontSee('src="'.$previewUrl.'"', false);
        $this->get($previewUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->delete(route('prestataire.employees.documents.remove', [$employee, $document]))
            ->assertRedirect(route('prestataire.employees.show', $employee));
        $document->refresh();
        $this->assertNull($document->file_path);
        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertDontSee('data-file-uploaded-state', false)
            ->assertDontSee('src="'.$previewUrl.'"', false);
        $this->get($previewUrl)->assertNotFound();

        $pdfDocument = $this->uploadDocument(
            $prestataire,
            $employee,
            CompanyEmployeeDocument::TYPE_CIN,
        );
        $pdfPreviewUrl = route('prestataire.employees.documents.preview', [$employee, $pdfDocument]);
        $this->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Fichier déposé')
            ->assertSee($pdfDocument->file_name)
            ->assertSee('href="'.$pdfPreviewUrl.'"', false)
            ->assertDontSee('src="'.$pdfPreviewUrl.'"', false);
    }

    public function test_manager_can_bulk_validate_pending_documents_for_one_employee_after_all_document_cards(): void
    {
        $prestataire = $this->makePrestataire('Prestataire validation groupée employé');
        $manager = $this->makeManager('Gestionnaire validation groupée employé');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise validation groupée employé', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Nour',
            'last_name' => 'El Amrani',
            'cin' => 'EM12345',
            'position' => 'Technicien',
        ]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $firstDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CIN)->firstOrFail();
        $secondDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CNSS)->firstOrFail();
        $optionalDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_PERMIT)->firstOrFail();
        $firstDocument->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);
        $secondDocument->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);
        $optionalDocument->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);

        $response = $this->actingAs($manager)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk();
        $html = $response->getContent();
        $buttonPosition = strpos($html, 'Valider la sélection (0)');

        $this->assertSame(3, substr_count($html, 'form="bulk-document-approval-form"'));
        $this->assertGreaterThan(
            max(
                strpos($html, $firstDocument->displayLabel()),
                strpos($html, $secondDocument->displayLabel()),
                strpos($html, $optionalDocument->displayLabel()),
            ),
            $buttonPosition,
        );

        $this->post(route('gestionnaire.employees.documents.approve-selected', $employee), [
            'document_ids' => [$firstDocument->getKey(), $secondDocument->getKey(), $optionalDocument->getKey()],
        ])
            ->assertRedirect(route('gestionnaire.employees.show', $employee))
            ->assertSessionHas('status', '3 documents validés.');

        foreach ([$firstDocument, $secondDocument, $optionalDocument] as $document) {
            $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $document->fresh()->status);
            $this->assertDatabaseHas('company_employee_histories', [
                'company_employee_id' => $employee->getKey(),
                'company_employee_document_id' => $document->getKey(),
                'action' => 'document_validated',
                'to_status' => CompanyEmployeeDocument::STATUS_VALIDATED,
            ]);
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $prestataire->getKey(),
                'type' => CompanyEmployeeWorkflowNotification::class,
                'data->event' => CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                'data->document_id' => $document->getKey(),
            ]);
        }
    }

    public function test_employee_bulk_validation_rejects_another_employee_and_stale_documents_atomically(): void
    {
        $prestataire = $this->makePrestataire('Prestataire sélection employés');
        $manager = $this->makeManager('Gestionnaire sélection employés');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise sélection employés', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Employé',
            'last_name' => 'Principal',
            'cin' => 'EP12345',
            'position' => 'Agent',
        ]);
        $otherEmployee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Employé',
            'last_name' => 'Secondaire',
            'cin' => 'ES12345',
            'position' => 'Agent',
        ]);
        $pendingDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CIN)->firstOrFail();
        $foreignDocument = $otherEmployee->documents()->where('type', CompanyEmployeeDocument::TYPE_CIN)->firstOrFail();
        $staleDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CNSS)->firstOrFail();
        $pendingDocument->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);
        $foreignDocument->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);
        $staleDocument->update(['status' => CompanyEmployeeDocument::STATUS_DRAFT]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.employees.documents.approve-selected', $employee), [
                'document_ids' => [$pendingDocument->getKey(), $foreignDocument->getKey()],
            ])
            ->assertStatus(422);
        $this->post(route('gestionnaire.employees.documents.approve-selected', $employee), [
            'document_ids' => [$pendingDocument->getKey(), $staleDocument->getKey()],
        ])->assertStatus(409);

        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $pendingDocument->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $foreignDocument->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_DRAFT, $staleDocument->fresh()->status);
        $this->assertDatabaseMissing('company_employee_histories', [
            'company_employee_document_id' => $pendingDocument->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_manager_without_employee_validation_permission_cannot_bulk_validate_documents(): void
    {
        $prestataire = $this->makePrestataire('Prestataire sans droit employé');
        $manager = $this->makeManager('Gestionnaire sans droit employé', ['employees.view']);
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise sans droit employé', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Employé',
            'last_name' => 'Protégé',
            'cin' => 'EU12345',
            'position' => 'Agent',
        ]);
        $document = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CIN)->firstOrFail();
        $document->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.employees.documents.approve-selected', $employee), [
                'document_ids' => [$document->getKey()],
            ])
            ->assertForbidden();

        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $document->fresh()->status);
    }

    public function test_manager_reviews_employee_and_documents_independently_and_rejection_requires_a_reason(): void
    {
        $prestataire = $this->makePrestataire('Prestataire décisions');
        $manager = $this->makeManager('Gestionnaire décisions');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise décisions', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Omar',
            'last_name' => 'Alaoui',
            'cin' => 'OP12345',
            'position' => 'Soudeur',
        ]);
        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $this->uploadDocument($prestataire, $employee, $type);
        }
        $this->actingAs($prestataire)->post(route('prestataire.employees.submit', $employee));
        $otherEmployee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Sara',
            'last_name' => 'Benali',
            'cin' => 'WX12345',
            'position' => 'Mécanicienne',
        ]);
        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $this->uploadDocument($prestataire, $otherEmployee, $type);
        }
        $this->actingAs($prestataire)->post(route('prestataire.employees.submit', $otherEmployee));

        $cinDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CIN)->firstOrFail();
        $cnssDocument = $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_CNSS)->firstOrFail();

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Historique des modifications et décisions')
            ->assertSee('Valider ce document');

        $this->actingAs($manager)
            ->post(route('gestionnaire.employees.documents.reject', [$employee, $cnssDocument]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cnssDocument->fresh()->status);

        $this->post(route('gestionnaire.employees.documents.approve', [$employee, $cinDocument]))
            ->assertRedirect(route('gestionnaire.employees.show', $employee));
        $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $cinDocument->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cnssDocument->fresh()->status);
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $otherEmployee->fresh()->status);
        $this->assertSame(
            [CompanyEmployeeDocument::STATUS_PENDING],
            $otherEmployee->documents()
                ->whereIn('type', CompanyEmployeeDocument::requiredTypes())
                ->pluck('status')
                ->unique()
                ->all(),
        );

        $this->post(route('gestionnaire.employees.documents.reject', [$employee, $cnssDocument]), [
            'reason' => 'Le document est illisible.',
        ])->assertRedirect(route('gestionnaire.employees.show', $employee));
        $this->assertSame(CompanyEmployeeDocument::STATUS_REJECTED, $cnssDocument->fresh()->status);
        $this->assertSame(CompanyEmployee::STATUS_REJECTED, $employee->fresh()->status);
        $this->actingAs($prestataire)
            ->get(route('prestataire.employees.show', $employee))
            ->assertOk()
            ->assertSee('data-document-rejection-reason="'.$cnssDocument->getKey().'"', false)
            ->assertSee('Le document est illisible.');

        $this->uploadDocument($prestataire, $employee, CompanyEmployeeDocument::TYPE_CNSS, 'cnss-corrige-1.pdf');
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cnssDocument->fresh()->status);
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->getKey(),
            'company_employee_document_id' => $cnssDocument->getKey(),
            'action' => 'document_rejected',
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.employees.approve', $otherEmployee))
            ->assertStatus(409)
            ->assertSeeText('en attente de validation');
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $otherEmployee->fresh()->status);

        foreach ($otherEmployee->documents()->whereIn('type', CompanyEmployeeDocument::requiredTypes())->get() as $document) {
            $this->post(route('gestionnaire.employees.documents.approve', [$otherEmployee, $document]))
                ->assertRedirect(route('gestionnaire.employees.show', $otherEmployee));
        }

        $this->post(route('gestionnaire.employees.approve', $otherEmployee))
            ->assertRedirect(route('gestionnaire.employees.show', $otherEmployee));
        $this->assertSame(CompanyEmployee::STATUS_VALIDATED, $otherEmployee->fresh()->status);
        $this->assertSame(
            [CompanyEmployeeDocument::STATUS_VALIDATED],
            $otherEmployee->documents()
                ->whereIn('type', CompanyEmployeeDocument::requiredTypes())
                ->pluck('status')
                ->unique()
                ->all(),
        );

        $this->post(route('gestionnaire.employees.reject', $employee), [
            'reason' => 'Informations à corriger.',
        ])->assertRedirect(route('gestionnaire.employees.show', $employee));
        $employee->refresh();
        $this->assertSame(CompanyEmployee::STATUS_REJECTED, $employee->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $cinDocument->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cnssDocument->fresh()->status);
        $this->assertSame(CompanyEmployee::STATUS_VALIDATED, $otherEmployee->fresh()->status);

        $this->actingAs($prestataire)
            ->put(route('prestataire.employees.update', $employee), [
                'first_name' => 'Omar',
                'last_name' => 'Alaoui',
                'cin' => 'OP12345',
                'position' => 'Soudeur qualifié',
                'locker_number' => null,
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee));
        $this->assertSame(CompanyEmployee::STATUS_REJECTED, $employee->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cnssDocument->fresh()->status);

        $this->uploadDocument($prestataire, $employee, CompanyEmployeeDocument::TYPE_CNSS, 'cnss-corrige.pdf');
        $this->post(route('prestataire.employees.submit', $employee))
            ->assertRedirect(route('prestataire.employees.show', $employee));

        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cnssDocument->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $cinDocument->fresh()->status);
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->id,
            'action' => 'resubmitted',
        ]);
        $this->assertSame(2, $manager->notifications()
            ->where('data->employee_id', $employee->getKey())
            ->whereIn('data->event', [
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED,
            ])->count());
    }

    public function test_employee_and_document_decisions_wait_for_company_validation(): void
    {
        $prestataire = $this->makePrestataire('Prestataire entreprise en attente');
        $manager = $this->makeManager('Gestionnaire entreprise en attente', [
            'entreprises.validate',
            'employees.view',
            'employees.edit',
            'employees.validate',
            'employees.reject',
            'employees.download',
        ]);
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise en attente', Company::STATUS_PENDING);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Nour',
            'last_name' => 'El Idrissi',
            'cin' => 'AA12345',
            'position' => 'Technicienne',
        ]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $this->validateRequiredEmployeeDocuments($employee);
        $cinDocument = $employee->documents()
            ->where('type', CompanyEmployeeDocument::TYPE_CIN)
            ->firstOrFail();
        $cinDocument->update(['status' => CompanyEmployeeDocument::STATUS_PENDING]);
        $blockedMessage = "La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.";

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Entreprise non validée')
            ->assertSeeText($blockedMessage)
            ->assertSee('Valider l’employé')
            ->assertSee('Valider ce document')
            ->assertSee('disabled', false);

        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText($blockedMessage);
        $this->post(route('gestionnaire.employees.reject', $employee), [
            'reason' => 'Les informations de l’employé sont à corriger.',
        ])->assertStatus(409)
            ->assertSeeText($blockedMessage);
        $this->post(route('gestionnaire.employees.documents.approve', [$employee, $cinDocument]))
            ->assertStatus(409)
            ->assertSeeText($blockedMessage);

        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cinDocument->fresh()->status);

        foreach (CompanyDocument::requiredTypes() as $type) {
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => 'companies/'.$company->getKey().'/'.$type.'.pdf',
            ]);
        }

        $this->post(route('gestionnaire.companies.approve', $company))
            ->assertRedirect(route('gestionnaire.companies.show', $company));
        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $cinDocument->fresh()->status);

        $this->post(route('gestionnaire.employees.documents.approve', [$employee, $cinDocument]))
            ->assertRedirect(route('gestionnaire.employees.show', $employee));
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertRedirect(route('gestionnaire.employees.show', $employee));

        $this->assertSame(CompanyEmployee::STATUS_VALIDATED, $employee->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_VALIDATED, $cinDocument->fresh()->status);
    }

    public function test_manager_cannot_approve_employee_with_missing_pending_or_expired_required_documents(): void
    {
        $prestataire = $this->makePrestataire('Prestataire vérification documentaire');
        $manager = $this->makeManager('Gestionnaire vérification documentaire');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise vérification documentaire', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Nawal',
            'last_name' => 'Ziani',
            'cin' => 'BC12345',
            'position' => 'Technicienne',
        ]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $this->validateRequiredEmployeeDocuments($employee);
        $missingDocument = $employee->documents()
            ->where('type', CompanyEmployeeDocument::TYPE_RULES_COMMITMENT)
            ->firstOrFail();
        $missingDocument->delete();

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('Approbation finale impossible')
            ->assertSee('Engagement au règlement')
            ->assertSee('document obligatoire manquant');

        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('Engagement au règlement')
            ->assertSeeText('document obligatoire manquant');
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);

        $missingDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_RULES_COMMITMENT,
            'label' => CompanyEmployeeDocument::labelForType(CompanyEmployeeDocument::TYPE_RULES_COMMITMENT),
            'status' => CompanyEmployeeDocument::STATUS_PENDING,
            'file_name' => 'engagement-reglement.pdf',
            'file_path' => 'company-employees/'.$employee->getKey().'/engagement-reglement.pdf',
        ]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('en attente de validation');

        $missingDocument->update([
            'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('date d’expiration dépassée');

        $missingDocument->update(['expires_at' => now()->addYear()->toDateString()]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertRedirect(route('gestionnaire.employees.show', $employee));

        $this->assertSame(CompanyEmployee::STATUS_VALIDATED, $employee->fresh()->status);
    }

    public function test_uploaded_optional_habilitations_must_be_validated_and_not_expired_for_employee_approval(): void
    {
        $prestataire = $this->makePrestataire('Prestataire habilitations');
        $manager = $this->makeManager('Gestionnaire habilitations');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise habilitations', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Salim',
            'last_name' => 'Khalfi',
            'cin' => 'DE12345',
            'position' => 'Électricien',
        ]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $this->validateRequiredEmployeeDocuments($employee);
        $optionalDocument = $employee->documents()
            ->where('type', CompanyEmployeeDocument::TYPE_HABILITATIONS)
            ->firstOrFail();
        $optionalDocument->update([
            'status' => CompanyEmployeeDocument::STATUS_PENDING,
            'file_name' => 'habilitations.pdf',
            'file_path' => 'company-employees/'.$employee->getKey().'/habilitations.pdf',
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('Habilitations')
            ->assertSeeText('en attente de validation');

        $optionalDocument->update([
            'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('date d’expiration dépassée');

        $optionalDocument->update(['expires_at' => now()->addYear()->toDateString()]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertRedirect(route('gestionnaire.employees.show', $employee));

        $this->assertSame(CompanyEmployee::STATUS_VALIDATED, $employee->fresh()->status);
    }

    public function test_uploaded_optional_permit_and_at_follow_pending_validation_workflow(): void
    {
        $prestataire = $this->makePrestataire('Prestataire permis AT facultatifs');
        $manager = $this->makeManager('Gestionnaire permis AT facultatifs');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise permis AT facultatifs', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Yassine',
            'last_name' => 'Bennani',
            'cin' => 'QR24680',
            'position' => 'Conducteur',
        ]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $this->validateRequiredEmployeeDocuments($employee);

        $permit = $this->uploadDocument($prestataire, $employee, CompanyEmployeeDocument::TYPE_PERMIT);
        $at = $this->uploadDocument($prestataire, $employee, CompanyEmployeeDocument::TYPE_NOMINATIVE_AT);

        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $permit->fresh()->status);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $at->fresh()->status);

        $this->actingAs($manager)
            ->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('Permis')
            ->assertSeeText('en attente de validation');

        $permit->update(['status' => CompanyEmployeeDocument::STATUS_VALIDATED]);
        $at->update([
            'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertStatus(409)
            ->assertSeeText('AT nominative')
            ->assertSeeText('date d’expiration dépassée');

        $at->update(['expires_at' => now()->addYear()->toDateString()]);
        $this->post(route('gestionnaire.employees.approve', $employee))
            ->assertRedirect(route('gestionnaire.employees.show', $employee));

        $this->assertSame(CompanyEmployee::STATUS_VALIDATED, $employee->fresh()->status);
    }

    public function test_prestataire_can_upload_habilitations_when_its_document_row_is_missing(): void
    {
        $prestataire = $this->makePrestataire('Prestataire nouvel upload');
        $company = $this->makeCompany($prestataire, 'Entreprise nouvel upload', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company);
        $employee->documents()->where('type', CompanyEmployeeDocument::TYPE_HABILITATIONS)->delete();

        $document = $this->uploadDocument(
            $prestataire,
            $employee,
            CompanyEmployeeDocument::TYPE_HABILITATIONS,
            'habilitations.pdf',
        );

        $this->assertSame(CompanyEmployeeDocument::TYPE_HABILITATIONS, $document->type);
        $this->assertSame(CompanyEmployeeDocument::STATUS_PENDING, $document->status);
        Storage::disk('documents_private')->assertExists($document->file_path);
        $this->assertDatabaseHas('company_employee_document_versions', [
            'company_employee_document_id' => $document->getKey(),
            'version' => 1,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('company_employee_histories', [
            'company_employee_id' => $employee->getKey(),
            'company_employee_document_id' => $document->getKey(),
            'action' => 'document_uploaded',
        ]);
    }

    public function test_manager_cannot_complete_a_draft_outside_delegated_management(): void
    {
        $prestataire = $this->makePrestataire('Prestataire complété');
        $manager = $this->makeManager('Gestionnaire complétion');
        $this->assignPrestataireToManager($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise complétion', Company::STATUS_VALIDATED);
        $employee = $this->createEmployee($prestataire, $company);

        $this->actingAs($manager)
            ->put(route('gestionnaire.employees.update', $employee), [
                'first_name' => 'Lina',
                'last_name' => 'Mansouri',
                'cin' => 'YZ12345',
                'position' => 'Ingénieure',
                'locker_number' => 'C-12',
            ])
            ->assertNotFound();

        $this->assertSame($employee->first_name, $employee->fresh()->first_name);
        $this->assertSame(CompanyEmployee::STATUS_DRAFT, $employee->fresh()->status);
        $this->assertSame($company->id, $employee->fresh()->company_id);
        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->assertDatabaseMissing('company_employee_histories', [
            'company_employee_id' => $employee->id,
            'action' => 'information_completed',
        ]);
    }

    public function test_manager_can_only_list_and_review_employees_for_assigned_prestataires_with_existing_permissions(): void
    {
        $manager = $this->makeManager('Gestionnaire affectation');
        $unassignedManager = $this->makeManager('Gestionnaire non affecté');
        $assignedProvider = $this->makePrestataire('Prestataire affecté');
        $otherProvider = $this->makePrestataire('Prestataire non affecté');
        $this->assignPrestataireToManager($manager, $assignedProvider);
        $assignedEmployee = $this->createEmployee($assignedProvider, $this->makeCompany($assignedProvider, 'Entreprise autorisée'), [
            'first_name' => 'Meriem',
            'last_name' => 'Test',
            'cin' => 'QR12345',
            'position' => 'Agent',
        ]);
        $assignedEmployee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $foreignEmployee = $this->createEmployee($otherProvider, $this->makeCompany($otherProvider, 'Entreprise interdite'), [
            'first_name' => 'Employé',
            'last_name' => 'Inaccessible',
            'cin' => 'ST12345',
            'position' => 'Agent',
        ]);

        $this->actingAs($manager)
            ->get(route('gestionnaire.employees.index'))
            ->assertOk()
            ->assertSee('Meriem Test')
            ->assertSee('data-navigate-to="'.route('gestionnaire.employees.show', $assignedEmployee).'"', false)
            ->assertDontSee('Inaccessible');
        $this->get(route('gestionnaire.employees.show', $foreignEmployee))->assertNotFound();
        $this->actingAs($unassignedManager)
            ->get(route('gestionnaire.employees.show', $assignedEmployee))
            ->assertNotFound();

        $managerWithoutPermission = $this->makeManager('Gestionnaire sans permission', []);
        $this->assignPrestataireToManager($managerWithoutPermission, $assignedProvider);
        $this->actingAs($managerWithoutPermission)
            ->get(route('gestionnaire.employees.index'))
            ->assertForbidden();

        $downloadOnlyManager = $this->makeManager('Gestionnaire téléchargement', ['employees.download']);
        $this->assignPrestataireToManager($downloadOnlyManager, $assignedProvider);
        $this->actingAs($downloadOnlyManager)
            ->get(route('gestionnaire.employees.show', $assignedEmployee))
            ->assertForbidden();

        $viewOnlyManager = $this->makeManager('Gestionnaire sans téléchargement', ['employees.view']);
        $this->assignPrestataireToManager($viewOnlyManager, $assignedProvider);
        $assignedEmployee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $this->actingAs($viewOnlyManager)
            ->get(route('gestionnaire.employees.show', $assignedEmployee))
            ->assertOk()
            ->assertDontSee('Valider l’employé')
            ->assertDontSee('Rejeter l’employé');
        $this->get(route('gestionnaire.employees.documents.download', [
            $assignedEmployee,
            $assignedEmployee->documents()->firstOrFail(),
        ]))
            ->assertForbidden();
    }

    public function test_legacy_access_team_data_remains_for_security_review_but_provider_routes_are_removed(): void
    {
        $prestataire = $this->makePrestataire('Prestataire legacy');
        $company = $this->makeCompany($prestataire, 'Entreprise nouvelle');
        $document = Document::create([
            'prestataire_id' => $prestataire->getKey(),
            'title' => 'Dossier accès historique',
            'status' => Document::STATUS_VALIDATED,
        ]);
        $legacyMember = $document->safetyTeamMembers()->create([
            'name' => 'Membre accès historique',
            'cin' => 'LEGACY123',
            'position' => 'Responsable sécurité',
            'status' => SafetyTeamMember::STATUS_VALIDATED,
        ]);
        $employee = $this->createEmployee($prestataire, $company, [
            'first_name' => 'Employé',
            'last_name' => 'Permanent',
            'cin' => 'UV12345',
            'position' => 'Technicien',
        ]);

        $this->actingAs($prestataire)
            ->get('/prestataire/equipe-securite')
            ->assertNotFound();

        $securityLead = $this->makeSecurityLead();
        $this->actingAs($securityLead)
            ->get(route('responsable-securite.documents.show', $document))
            ->assertOk()
            ->assertSee('Membre accès historique')
            ->assertDontSee('Employé Permanent');
        $this->assertDatabaseHas('safety_team_members', ['id' => $legacyMember->id, 'document_id' => $document->id]);
        $this->assertDatabaseHas('company_employees', ['id' => $employee->id, 'company_id' => $company->id]);
    }

    private function profilePhoto(string $name = 'photo.png'): UploadedFile
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
            true,
        );

        if (! is_string($contents)) {
            throw new \RuntimeException('Le contenu image de test ne peut pas être décodé.');
        }

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function createEmployee(User $prestataire, Company $company, array $attributes = []): CompanyEmployee
    {
        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.store'), [
                'company_id' => $company->getKey(),
                ...$attributes,
            ])
            ->assertRedirect();

        return CompanyEmployee::query()
            ->where('company_id', $company->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    private function uploadDocument(
        User $prestataire,
        CompanyEmployee $employee,
        string $type,
        string $fileName = 'document.pdf',
    ): CompanyEmployeeDocument {
        $this->actingAs($prestataire)
            ->post(route('prestataire.employees.documents.store', [$employee, $type]), [
                'document' => UploadedFile::fake()->create($fileName, 100, 'application/pdf'),
                'expires_at' => now()->addYear()->format('Y-m-d'),
            ])
            ->assertRedirect(route('prestataire.employees.show', $employee));

        return $employee->documents()->where('type', $type)->firstOrFail();
    }

    private function makePrestataire(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
        $user->roles()->syncWithoutDetaching(Role::query()->where('name', 'prestataire')->firstOrFail()->getKey());

        return $user;
    }

    private function makeSecurityLead(): User
    {
        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching(Role::query()->where('name', 'responsable_securite')->firstOrFail()->getKey());

        return $user;
    }

    private function makeCompany(User $prestataire, string $name, string $status = Company::STATUS_DRAFT): Company
    {
        return Company::create([
            'prestataire_id' => $prestataire->getKey(),
            'name' => $name,
            'activity' => 'Construction',
            'assignment' => 'Chantier A',
            'status' => $status,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeManager(string $name, array $permissions = [
        'employees.view',
        'employees.edit',
        'employees.validate',
        'employees.reject',
        'employees.download',
    ]): User
    {
        $manager = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
        $manager->roles()->syncWithoutDetaching(Role::query()->where('name', 'gestionnaire')->firstOrFail()->getKey());

        if ($permissions !== []) {
            $manager->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', $permissions)->pluck('id'),
            );
        }

        return $manager;
    }

    private function assignPrestataireToManager(User $manager, User $prestataire): void
    {
        $manager->assignedPrestataires()->syncWithoutDetaching([
            $prestataire->getKey() => ['assigned_by' => $manager->getKey()],
        ]);
    }

    private function validateRequiredEmployeeDocuments(CompanyEmployee $employee): void
    {
        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $employee->documents()->where('type', $type)->update([
                'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => 'company-employees/'.$employee->getKey().'/'.$type.'.pdf',
                'expires_at' => now()->addYear()->toDateString(),
            ]);
        }
    }
}
