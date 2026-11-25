<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyEmployeeDocumentVersion;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyMachineryDocumentVersion;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\CompanyToolingDocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrivateDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_provider_can_preview_only_an_owned_private_document_inline(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('provider@example.test');
        $otherProvider = $this->provider('other-provider@example.test');
        $company = $this->company($provider);
        $document = $this->companyDocument($company);
        $this->storeCurrentVersion($provider, $company, $document);

        $this->actingAs($provider)
            ->get(route('prestataire.companies.documents.preview', [$company, $document]))
            ->assertOk()
            ->assertStreamed()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderContains('Content-Disposition', 'inline')
            ->assertHeaderContains('Cache-Control', 'no-store');

        $this->actingAs($otherProvider)
            ->get(route('prestataire.companies.documents.preview', [$company, $document]))
            ->assertNotFound();
    }

    public function test_manager_preview_requires_download_permission_and_provider_assignment(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('assigned-provider@example.test');
        $otherProvider = $this->provider('unassigned-provider@example.test');
        $company = $this->company($provider);
        $document = $this->companyDocument($company);
        $this->storeCurrentVersion($provider, $company, $document);
        $managerWithoutPermission = $this->manager('limited@example.test', ['entreprises.view']);
        $managerWithoutAssignment = $this->manager('unassigned-manager@example.test', ['entreprises.download']);
        $managerWithoutAssignment->assignedPrestataires()->attach($otherProvider, [
            'assigned_by' => $managerWithoutAssignment->getKey(),
        ]);
        $manager = $this->manager('reviewer@example.test', ['entreprises.view', 'entreprises.download']);
        $manager->assignedPrestataires()->attach($provider, ['assigned_by' => $manager->getKey()]);

        $this->actingAs($managerWithoutPermission)
            ->get(route('gestionnaire.companies.documents.preview', [$company, $document]))
            ->assertForbidden();

        $this->actingAs($managerWithoutAssignment)
            ->get(route('gestionnaire.companies.documents.preview', [$company, $document]))
            ->assertNotFound();

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.documents.preview', [$company, $document]))
            ->assertOk()
            ->assertStreamed()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_preview_rejects_a_file_whose_actual_mime_type_differs_from_its_metadata(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('mime-check@example.test');
        $company = $this->company($provider);
        $document = $this->companyDocument($company, [
            'file_name' => 'misleading.pdf',
            'file_path' => 'companies/private/misleading.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('documents_private')->put($document->file_path, 'plain text, not a PDF');

        $this->actingAs($provider)
            ->get(route('prestataire.companies.documents.preview', [$company, $document]))
            ->assertNotFound();
    }

    public function test_provider_can_preview_private_jpeg_and_png_files(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('image-preview@example.test');
        $company = $this->company($provider);
        $imageFiles = [
            'photo.jpeg' => [
                'mime_type' => 'image/jpeg',
                'contents' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEA8WFhUVFRUVFRUVFRUVFRUVFRUWFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy8lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAAQAR/9oADAMBAAIQAxAAAAH/xAAVEAEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAQABBQJ//8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oACAEDAQE/AYf/xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAgEBPwGP/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oACAEDAQE/EH//xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
            ],
            'photo.png' => [
                'mime_type' => 'image/png',
                'contents' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/rqsAAAAASUVORK5CYII=',
            ],
        ];

        foreach ($imageFiles as $fileName => $image) {
            $document = $company->documents()->create([
                'type' => 'preview_'.pathinfo($fileName, PATHINFO_EXTENSION),
                'label' => $fileName,
                'status' => CompanyDocument::STATUS_PENDING,
                'file_name' => $fileName,
                'file_path' => 'companies/'.$company->getKey().'/'.$fileName,
                'mime_type' => $image['mime_type'],
            ]);
            $contents = base64_decode($image['contents'], true);
            $this->assertIsString($contents);
            Storage::disk('documents_private')->put($document->file_path, $contents);

            $this->actingAs($provider)
                ->get(route('prestataire.companies.documents.preview', [$company, $document]))
                ->assertOk()
                ->assertStreamed()
                ->assertHeader('Content-Type', $image['mime_type'])
                ->assertHeaderContains('Content-Disposition', 'inline');
        }
    }

    #[DataProvider('removableDocumentStatuses')]
    public function test_provider_can_remove_current_files_in_nonvalidated_states(string $status): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('removal-'.str_replace('_', '-', $status).'@example.test');
        $company = $this->company($provider);
        $document = $this->companyDocument($company);
        $olderVersion = $this->storeCurrentVersion($provider, $company, $document);
        $currentFile = UploadedFile::fake()->createWithContent(
            'current.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
        );
        $currentPath = $currentFile->store('companies/'.$company->getKey().'/'.$document->type, 'documents_private');
        $this->assertIsString($currentPath);
        $document->update([
            'file_name' => $currentFile->getClientOriginalName(),
            'file_path' => $currentPath,
            'mime_type' => 'application/pdf',
            'status' => $status,
        ]);
        $olderVersion->update(['is_current' => false]);
        $currentVersion = $document->versions()->create([
            'version' => 2,
            'file_name' => $currentFile->getClientOriginalName(),
            'file_path' => $currentPath,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $provider->getKey(),
            'uploaded_at' => now(),
            'is_current' => true,
        ]);

        $this->actingAs($provider)
            ->delete(route('prestataire.companies.documents.remove', [$company, $document]))
            ->assertRedirect(route('prestataire.companies.show', $company));

        $this->assertDatabaseHas('company_documents', [
            'id' => $document->getKey(),
            'status' => CompanyDocument::STATUS_DRAFT,
            'file_name' => null,
            'file_path' => null,
            'mime_type' => null,
        ]);
        $this->assertDatabaseHas('company_document_versions', [
            'id' => $olderVersion->getKey(),
            'file_path' => $olderVersion->file_path,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('company_document_versions', [
            'id' => $currentVersion->getKey(),
            'file_path' => '',
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('company_review_histories', [
            'company_id' => $company->getKey(),
            'company_document_id' => $document->getKey(),
            'action' => 'document_removed',
        ]);
        Storage::disk('documents_private')->assertExists($olderVersion->file_path);
        Storage::disk('documents_private')->assertMissing($currentPath);

        $this->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Fichier retiré du stockage')
            ->assertDontSee(route('prestataire.companies.documents.versions.preview', [$company, $document, $currentVersion]));
    }

    public static function removableDocumentStatuses(): array
    {
        return [
            'draft' => [CompanyDocument::STATUS_DRAFT],
            'pending' => [CompanyDocument::STATUS_PENDING],
            'rejected' => [CompanyDocument::STATUS_REJECTED],
        ];
    }

    public function test_provider_can_upload_a_new_version_after_removing_the_current_file(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('upload-after-removal@example.test');
        $company = $this->company($provider);
        $document = $this->companyDocument($company);
        $oldVersion = $this->storeCurrentVersion($provider, $company, $document);
        $oldPath = $oldVersion->file_path;

        $this->actingAs($provider)
            ->delete(route('prestataire.companies.documents.remove', [$company, $document]))
            ->assertRedirect(route('prestataire.companies.show', $company));

        $this->post(route('prestataire.companies.documents.store', [
            'company' => $company,
            'documentType' => $document->type,
        ]), [
            'document' => UploadedFile::fake()->createWithContent(
                'replacement.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
            ),
        ])->assertRedirect(route('prestataire.companies.show', $company));

        $this->assertDatabaseHas('company_document_versions', [
            'id' => $oldVersion->getKey(),
            'version' => 1,
            'file_path' => '',
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('company_document_versions', [
            'company_document_id' => $document->getKey(),
            'version' => 2,
            'is_current' => true,
        ]);
        $this->assertSame(CompanyDocument::STATUS_PENDING, $document->fresh()->status);
        Storage::disk('documents_private')->assertMissing($oldPath);
        Storage::disk('documents_private')->assertExists($document->fresh()->file_path);
    }

    public function test_provider_can_remove_current_files_from_employee_machinery_and_tooling_documents(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('resource-removal@example.test');
        $company = $this->company($provider);
        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->getKey(),
            'first_name' => 'Employé',
            'last_name' => 'Test',
            'status' => 'draft',
        ]);
        $employeeDocument = $employee->documents()->create([
            'type' => 'cin',
            'label' => 'Carte d’identité',
            'status' => 'pending',
        ]);
        $machinery = CompanyMachinery::query()->create([
            'company_id' => $company->getKey(),
            'designation' => 'Engin test',
            'category' => 'Test',
            'status' => 'draft',
        ]);
        $machineryDocument = $machinery->documents()->create([
            'type' => 'insurance',
            'label' => 'Assurance',
            'status' => 'rejected',
        ]);
        $tooling = CompanyTooling::query()->create([
            'company_id' => $company->getKey(),
            'designation' => 'Outillage test',
            'category' => 'Test',
            'status' => 'draft',
        ]);
        $toolingDocument = $tooling->documents()->create([
            'type' => 'technical_sheet',
            'label' => 'Fiche technique',
            'status' => 'pending',
        ]);
        $resources = [
            [$employee, $employeeDocument, 'prestataire.employees.documents.remove'],
            [$machinery, $machineryDocument, 'prestataire.machinery.documents.remove'],
            [$tooling, $toolingDocument, 'prestataire.toolings.documents.remove'],
        ];

        foreach ($resources as [$resource, $document, $routeName]) {
            $version = $this->storeResourceVersion($document, $provider);
            $currentPath = $version->file_path;

            $this->actingAs($provider)
                ->delete(route($routeName, [$resource, $document]))
                ->assertRedirect();

            $this->assertDatabaseHas($document->getTable(), [
                'id' => $document->getKey(),
                'status' => 'draft',
                'file_path' => null,
            ]);
            $this->assertDatabaseHas($version->getTable(), [
                'id' => $version->getKey(),
                'file_path' => '',
                'is_current' => false,
            ]);
            Storage::disk('documents_private')->assertMissing($currentPath);
        }
    }

    public function test_provider_cannot_remove_a_validated_document(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('validated-removal@example.test');
        $company = $this->company($provider);
        $document = $this->companyDocument($company, ['status' => CompanyDocument::STATUS_VALIDATED]);
        $version = $this->storeCurrentVersion($provider, $company, $document);

        $this->actingAs($provider)
            ->delete(route('prestataire.companies.documents.remove', [$company, $document]))
            ->assertConflict();

        $this->assertSame(CompanyDocument::STATUS_VALIDATED, $document->fresh()->status);
        $this->assertSame($version->getKey(), $document->fresh()->versions()->where('is_current', true)->value('id'));
        Storage::disk('documents_private')->assertExists($version->file_path);
    }

    public function test_only_the_document_owner_can_remove_a_current_file(): void
    {
        Storage::fake('documents_private');
        $provider = $this->provider('owner-remove@example.test');
        $otherProvider = $this->provider('other-owner-remove@example.test');
        $manager = $this->manager('manager-remove@example.test', ['entreprises.view']);
        $company = $this->company($provider);
        $document = $this->companyDocument($company);
        $version = $this->storeCurrentVersion($provider, $company, $document);

        $this->actingAs($otherProvider)
            ->delete(route('prestataire.companies.documents.remove', [$company, $document]))
            ->assertNotFound();

        $this->actingAs($manager)
            ->delete(route('prestataire.companies.documents.remove', [$company, $document]))
            ->assertForbidden();

        $this->assertSame($version->file_path, $document->fresh()->file_path);
        Storage::disk('documents_private')->assertExists($version->file_path);
    }

    private function provider(string $email): User
    {
        $provider = User::factory()->create(['email' => $email]);
        $provider->roles()->attach(Role::query()->where('name', 'prestataire')->firstOrFail());

        return $provider;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function manager(string $email, array $permissions): User
    {
        $manager = User::factory()->create(['email' => $email]);
        $manager->roles()->attach(Role::query()->where('name', 'gestionnaire')->firstOrFail());
        $manager->permissions()->attach(
            Permission::query()->whereIn('name', $permissions)->pluck('id'),
        );

        return $manager;
    }

    private function company(User $provider): Company
    {
        return Company::query()->create([
            'prestataire_id' => $provider->getKey(),
            'name' => 'Entreprise '.$provider->email,
            'status' => Company::STATUS_DRAFT,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function companyDocument(Company $company, array $attributes = []): CompanyDocument
    {
        return $company->documents()->create(array_replace([
            'type' => CompanyDocument::TYPE_ASSURANCE_AT,
            'label' => CompanyDocument::labelForType(CompanyDocument::TYPE_ASSURANCE_AT),
            'status' => CompanyDocument::STATUS_PENDING,
        ], $attributes));
    }

    private function storeCurrentVersion(User $provider, Company $company, CompanyDocument $document): CompanyDocumentVersion
    {
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
        );
        $path = $file->store('companies/'.$company->getKey().'/'.$document->type, 'documents_private');

        $document->update([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => 'application/pdf',
        ]);

        return $document->versions()->create([
            'version' => 1,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $provider->getKey(),
            'uploaded_at' => now(),
            'is_current' => true,
        ]);
    }

    private function storeResourceVersion(
        CompanyEmployeeDocument|CompanyMachineryDocument|CompanyToolingDocument $document,
        User $provider,
    ): CompanyEmployeeDocumentVersion|CompanyMachineryDocumentVersion|CompanyToolingDocumentVersion {
        $filePath = 'private/'.$document->getTable().'/'.$document->getKey().'/current.pdf';
        Storage::disk('documents_private')->put(
            $filePath,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
        );
        $document->update([
            'file_name' => 'current.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
        ]);

        return $document->versions()->create([
            'version' => 1,
            'file_name' => 'current.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $provider->getKey(),
            'uploaded_at' => now(),
            'is_current' => true,
        ]);
    }
}
