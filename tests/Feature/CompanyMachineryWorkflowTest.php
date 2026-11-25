<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyMachineryDocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyMachineryWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyMachineryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('documents_private');
    }

    public function test_provider_can_create_machinery_for_owned_companies_without_cross_company_leaks(): void
    {
        $provider = $this->makeProvider('Prestataire engins');
        $otherProvider = $this->makeProvider('Prestataire autre');
        $firstCompany = $this->makeCompany($provider, 'Entreprise Alpha');
        $secondCompany = $this->makeCompany($provider, 'Entreprise Beta');
        $foreignCompany = $this->makeCompany($otherProvider, 'Entreprise privée');

        $this->actingAs($provider)
            ->post(route('prestataire.machinery.store'), [
                'company_id' => $firstCompany->getKey(),
                'designation' => 'Pelle hydraulique',
                'category' => 'Terrassement',
                'serial_number' => 'SERIE-001',
            ])
            ->assertRedirect();
        $firstMachine = CompanyMachinery::query()->where('company_id', $firstCompany->getKey())->sole();

        $this->post(route('prestataire.machinery.store'), [
            'company_id' => $secondCompany->getKey(),
            'designation' => 'Grue mobile',
            'category' => 'Levage',
        ])->assertRedirect();
        $secondMachine = CompanyMachinery::query()->where('company_id', $secondCompany->getKey())->sole();

        $this->post(route('prestataire.machinery.store'), [
            'company_id' => $foreignCompany->getKey(),
            'designation' => 'Engin étranger',
            'category' => 'Transport',
        ])->assertSessionHasErrors('company_id');

        $this->get(route('prestataire.machinery.index', ['company_id' => $firstCompany->getKey()]))
            ->assertOk()
            ->assertSee('Pelle hydraulique')
            ->assertSee('data-navigate-to="'.route('prestataire.machinery.show', $firstMachine).'"', false)
            ->assertDontSee('Grue mobile');
        $this->get(route('prestataire.machinery.show', $secondMachine))->assertOk();
        $this->get(route('prestataire.machinery.show', $this->makeMachinery($foreignCompany, 'Engin étranger')))
            ->assertNotFound();

        $this->assertSame($firstCompany->getKey(), $firstMachine->company_id);
        $this->assertSame($secondCompany->getKey(), $secondMachine->company_id);
        $this->assertCount(4, $firstMachine->documents);
        $this->assertDatabaseHas('company_machinery_histories', [
            'company_machinery_id' => $firstMachine->getKey(),
            'action' => 'created',
        ]);
    }

    public function test_provider_can_create_machinery_with_a_private_photo_visible_in_its_table_cell_and_detail(): void
    {
        $provider = $this->makeProvider('Prestataire photo engin');
        $company = $this->makeCompany($provider, 'Entreprise photo engin');
        $photo = $this->profilePhoto();

        $this->actingAs($provider)
            ->post(route('prestataire.machinery.store'), [
                'company_id' => $company->getKey(),
                'designation' => 'Nacelle de chantier',
                'category' => 'Levage',
                'photo' => $photo,
            ])
            ->assertRedirect();

        $machinery = CompanyMachinery::query()
            ->where('company_id', $company->getKey())
            ->sole();
        $photoPath = $machinery->photo_path;
        $this->assertIsString($photoPath);
        $this->assertStringStartsWith('company-machinery/'.$machinery->getKey().'/photo/', $photoPath);
        $this->assertDatabaseHas('company_machinery', [
            'id' => $machinery->getKey(),
            'photo_path' => $photoPath,
        ]);
        Storage::disk('documents_private')->assertExists($photoPath);

        $photoUrl = route('prestataire.machinery.photo', $machinery);
        $listResponse = $this->get(route('prestataire.machinery.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false)
            ->assertSee('alt="Photo de Nacelle de chantier"', false);
        $listHtml = $listResponse->getContent();
        $cellMatch = preg_match(
            '/<td class="px-5 py-4 text-sm font-semibold text-slate-900">(.*?)<\/td>/s',
            $listHtml,
            $machineryCell,
        );

        $this->assertSame(6, substr_count($listHtml, '<th class='));
        $this->assertSame(1, $cellMatch);
        $this->assertStringContainsString('gap-3', $machineryCell[1]);
        $this->assertStringContainsString('src="'.$photoUrl.'"', $machineryCell[1]);
        $this->assertStringContainsString('Nacelle de chantier', $machineryCell[1]);
        $this->assertStringContainsString('size-11 shrink-0 rounded-full object-cover', $machineryCell[1]);
        $this->assertStringContainsString('data-machinery-photo-error-fallback', $machineryCell[1]);

        $this->get(route('prestataire.machinery.show', $machinery))
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

    public function test_machinery_without_a_photo_shows_the_default_avatar_and_missing_photo_fallback(): void
    {
        $provider = $this->makeProvider('Prestataire sans photo engin');
        $company = $this->makeCompany($provider, 'Entreprise sans photo engin');

        $this->actingAs($provider)
            ->post(route('prestataire.machinery.store'), [
                'company_id' => $company->getKey(),
                'designation' => 'Compresseur',
                'category' => 'Air comprimé',
            ])
            ->assertRedirect();
        $machinery = CompanyMachinery::query()
            ->where('company_id', $company->getKey())
            ->sole();

        $this->get(route('prestataire.machinery.index'))
            ->assertOk()
            ->assertSee('data-machinery-photo-placeholder', false)
            ->assertSee('data-machinery-tractor-icon', false)
            ->assertSee('aria-label="Aucune photo pour Compresseur"', false)
            ->assertDontSee('src="'.route('prestataire.machinery.photo', $machinery).'"', false);
        $this->get(route('prestataire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('data-machinery-photo-placeholder', false)
            ->assertSee('data-machinery-tractor-icon', false);
        $this->get(route('prestataire.machinery.photo', $machinery))->assertNotFound();

        $machinery->update([
            'photo_path' => 'company-machinery/'.$machinery->getKey().'/photo/absente.png',
        ]);
        $this->get(route('prestataire.machinery.index'))
            ->assertOk()
            ->assertSee('data-company-machinery-photo', false)
            ->assertSee('data-machinery-photo-error-fallback', false)
            ->assertSee('data-machinery-tractor-icon', false);
        $this->get(route('prestataire.machinery.photo', $machinery))->assertNotFound();
    }

    public function test_provider_can_replace_and_remove_machinery_photos_without_deleting_a_shared_file(): void
    {
        $provider = $this->makeProvider('Prestataire remplacement photo engin');
        $company = $this->makeCompany($provider, 'Entreprise remplacement photo engin');
        $machinery = $this->makeMachinery($company, 'Grue mobile');
        $documentCount = CompanyMachineryDocument::query()->count();

        $this->actingAs($provider)
            ->put(route('prestataire.machinery.update', $machinery), [
                'designation' => 'Grue mobile',
                'category' => 'Levage',
                'photo' => $this->profilePhoto('premiere-photo.png'),
            ])
            ->assertRedirect(route('prestataire.machinery.show', $machinery));
        $firstPhotoPath = $machinery->fresh()->photo_path;

        $this->put(route('prestataire.machinery.update', $machinery), [
            'designation' => 'Grue mobile',
            'category' => 'Levage',
            'photo' => $this->profilePhoto('photo-remplacee.png'),
        ])->assertRedirect(route('prestataire.machinery.show', $machinery));
        $replacementPhotoPath = $machinery->fresh()->photo_path;

        $this->assertNotSame($firstPhotoPath, $replacementPhotoPath);
        Storage::disk('documents_private')->assertMissing($firstPhotoPath);
        Storage::disk('documents_private')->assertExists($replacementPhotoPath);

        $otherMachinery = $this->makeMachinery($company, 'Chargeuse');
        $otherMachinery->update(['photo_path' => $replacementPhotoPath]);
        $this->put(route('prestataire.machinery.update', $machinery), [
            'designation' => 'Grue mobile',
            'category' => 'Levage',
            'remove_photo' => '1',
        ])->assertRedirect(route('prestataire.machinery.show', $machinery));

        $this->assertNull($machinery->fresh()->photo_path);
        $this->assertSame($replacementPhotoPath, $otherMachinery->fresh()->photo_path);
        Storage::disk('documents_private')->assertExists($replacementPhotoPath);

        $this->put(route('prestataire.machinery.update', $machinery), [
            'designation' => 'Grue mobile',
            'category' => 'Levage',
            'photo' => $this->profilePhoto('photo-a-supprimer.png'),
        ])->assertRedirect(route('prestataire.machinery.show', $machinery));
        $photoToRemove = $machinery->fresh()->photo_path;
        $this->put(route('prestataire.machinery.update', $machinery), [
            'designation' => 'Grue mobile',
            'category' => 'Levage',
            'remove_photo' => '1',
        ])->assertRedirect(route('prestataire.machinery.show', $machinery));

        $this->assertNull($machinery->fresh()->photo_path);
        Storage::disk('documents_private')->assertMissing($photoToRemove);
        $this->assertSame($documentCount + count(CompanyMachineryDocument::allTypes()), CompanyMachineryDocument::query()->count());
        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->fresh()->status);
    }

    public function test_machinery_photo_upload_rejects_unsupported_and_over_five_megabyte_files(): void
    {
        $provider = $this->makeProvider('Prestataire validation photo engin');
        $company = $this->makeCompany($provider, 'Entreprise validation photo engin');
        $machinery = $this->makeMachinery($company, 'Chariot élévateur');

        $this->actingAs($provider)
            ->from(route('prestataire.machinery.edit', $machinery))
            ->put(route('prestataire.machinery.update', $machinery), [
                'designation' => 'Chariot élévateur',
                'category' => 'Manutention',
                'photo' => UploadedFile::fake()->create('photo.gif', 20, 'image/gif'),
            ])
            ->assertRedirect(route('prestataire.machinery.edit', $machinery))
            ->assertSessionHasErrors([
                'photo' => 'La photo doit être au format JPG, JPEG, PNG ou WEBP.',
            ]);

        $this->put(route('prestataire.machinery.update', $machinery), [
            'designation' => 'Chariot élévateur',
            'category' => 'Manutention',
            'photo' => UploadedFile::fake()->create('photo-trop-grande.png', 5121, 'image/png'),
        ])->assertSessionHasErrors([
            'photo' => 'La photo ne peut pas dépasser 5 Mo.',
        ]);

        $this->assertNull($machinery->fresh()->photo_path);
        $this->assertSame([], Storage::disk('documents_private')->allFiles('company-machinery'));
    }

    public function test_machinery_photo_routes_enforce_provider_ownership_and_manager_view_permission(): void
    {
        $provider = $this->makeProvider('Prestataire propriétaire photo engin');
        $otherProvider = $this->makeProvider('Autre prestataire photo engin');
        $machinery = $this->makeMachinery(
            $this->makeCompany($provider, 'Entreprise propriétaire photo engin'),
            'Engin privé',
        );
        $this->actingAs($provider)
            ->put(route('prestataire.machinery.update', $machinery), [
                'designation' => 'Engin privé',
                'category' => 'Engin test',
                'photo' => $this->profilePhoto(),
            ])
            ->assertRedirect();
        $machinery->update(['status' => CompanyMachinery::STATUS_PENDING]);

        $this->actingAs($otherProvider)
            ->get(route('prestataire.machinery.photo', $machinery))
            ->assertNotFound();

        $manager = $this->makeManager('Gestionnaire lecture photo engin', ['machinery.view']);
        $this->assign($manager, $provider);
        $managerPhotoUrl = route('gestionnaire.machinery.photo', $machinery);
        $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('src="'.$managerPhotoUrl.'"', false);
        $this->get($managerPhotoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('prestataire.machinery.photo', $machinery))->assertForbidden();

        $managerWithoutView = $this->makeManager('Gestionnaire sans lecture photo engin', []);
        $this->assign($managerWithoutView, $provider);
        $this->actingAs($managerWithoutView)
            ->get($managerPhotoUrl)
            ->assertForbidden();
    }

    public function test_provider_cannot_transfer_machinery_to_another_company(): void
    {
        $provider = $this->makeProvider('Prestataire sans transfert');
        $company = $this->makeCompany($provider, 'Entreprise d’origine');
        $otherCompany = $this->makeCompany($provider, 'Entreprise cible');
        $machinery = $this->makeMachinery($company, 'Chargeuse');

        $this->actingAs($provider)
            ->from(route('prestataire.machinery.show', $machinery))
            ->put(route('prestataire.machinery.update', $machinery), [
                'company_id' => $otherCompany->getKey(),
                'designation' => 'Chargeuse',
                'category' => 'Chargement',
            ])
            ->assertRedirect(route('prestataire.machinery.show', $machinery))
            ->assertSessionHasErrors('company_id');

        $this->assertSame($company->getKey(), $machinery->fresh()->company_id);
    }

    public function test_document_replacements_are_private_versioned_and_reviewed_independently(): void
    {
        $provider = $this->makeProvider('Prestataire documents engins');
        $manager = $this->makeManager('Gestionnaire documents engins', [
            'machinery.view',
            'machinery.approve',
            'machinery.reject',
            'machinery.download',
        ]);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise documents');
        $machinery = $this->makeMachinery($company, 'Nacelle');
        $insurance = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_INSURANCE)->firstOrFail();
        $certificate = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_REGULATORY_CERTIFICATE)->firstOrFail();

        $this->actingAs($provider)
            ->post(route('prestataire.machinery.documents.store', [$machinery, $insurance->type]), [
                'document' => UploadedFile::fake()->create('bad.txt', 50, 'text/plain'),
            ])
            ->assertSessionHasErrors('document');
        $this->uploadDocument($provider, $machinery, $insurance->type, 'insurance-v1.pdf');
        $firstVersion = $insurance->versions()->sole();
        $this->uploadDocument($provider, $machinery, $insurance->type, 'insurance-v2.pdf');
        $this->uploadDocument($provider, $machinery, $certificate->type, 'certificate.pdf');
        $this->uploadRequiredDocuments($provider, $machinery);

        $insurance->refresh();
        $versions = $insurance->versions()->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertFalse($versions[0]->is_current);
        $this->assertTrue($versions[1]->is_current);
        Storage::disk('documents_private')->assertExists($firstVersion->file_path);
        Storage::disk('documents_private')->assertExists($versions[1]->file_path);
        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->fresh()->status);
        $this->assertMatchesRegularExpression('/^assurance-nacelle-\d{8}\.pdf$/', $insurance->fresh()->file_name);

        $this->actingAs($provider)
            ->post(route('prestataire.machinery.submit', $machinery))
            ->assertRedirect(route('prestataire.machinery.show', $machinery));
        $this->actingAs($manager)
            ->post(route('gestionnaire.machinery.approve', $machinery))
            ->assertRedirect(route('gestionnaire.machinery.show', $machinery));
        $this->assertSame(CompanyMachinery::STATUS_VALIDATED, $machinery->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $insurance->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $certificate->fresh()->status);

        $this->actingAs($manager)
            ->post(route('gestionnaire.machinery.documents.reject', [$machinery, $insurance]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->post(route('gestionnaire.machinery.documents.approve', [$machinery, $certificate]))
            ->assertRedirect(route('gestionnaire.machinery.show', $machinery));
        $this->post(route('gestionnaire.machinery.documents.reject', [$machinery, $insurance]), [
            'reason' => 'La date de validité est illisible.',
        ])->assertRedirect(route('gestionnaire.machinery.show', $machinery));

        $this->assertSame(CompanyMachineryDocument::STATUS_VALIDATED, $certificate->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_REJECTED, $insurance->fresh()->status);
        $this->assertSame(CompanyMachinery::STATUS_REJECTED, $machinery->fresh()->status);
        $this->assertSame(Company::STATUS_VALIDATED, $company->fresh()->status);
        $this->actingAs($provider)
            ->get(route('prestataire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('data-document-rejection-reason="'.$insurance->getKey().'"', false)
            ->assertSee('La date de validité est illisible.');

        $this->uploadDocument($provider, $machinery, $insurance->type, 'insurance-corrige.pdf');
        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $insurance->fresh()->status);
        $this->assertSame(CompanyMachinery::STATUS_PENDING, $machinery->fresh()->status);
        $this->get(route('prestataire.machinery.show', $machinery))
            ->assertOk()
            ->assertDontSee('data-document-rejection-reason="'.$insurance->getKey().'"', false);
        $this->assertSame(3, $insurance->versions()->count());
        $this->assertDatabaseHas('company_machinery_histories', [
            'company_machinery_id' => $machinery->getKey(),
            'company_machinery_document_id' => $insurance->getKey(),
            'action' => 'document_replaced',
        ]);

        $this->actingAs($provider)
            ->get(route('prestataire.machinery.documents.versions.download', [$machinery, $insurance, $firstVersion]))
            ->assertOk();
        $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.documents.download', [$machinery, $insurance]))
            ->assertOk()
            ->assertDownload($insurance->file_name);
        $this->get(route('gestionnaire.machinery.documents.versions.download', [$machinery, $insurance, $firstVersion]))
            ->assertOk()
            ->assertDownload($firstVersion->file_name);
        $this->assertSame(6, CompanyMachineryDocumentVersion::query()->count());
    }

    public function test_manager_can_bulk_validate_pending_documents_for_one_machinery_after_all_document_cards(): void
    {
        $provider = $this->makeProvider('Prestataire validation groupée engin');
        $manager = $this->makeManager('Gestionnaire validation groupée engin', ['machinery.view', 'machinery.approve']);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise validation groupée engin');
        $machinery = $this->makeMachinery($company, 'Nacelle validation groupée');
        $machinery->update(['status' => CompanyMachinery::STATUS_PENDING]);
        $firstDocument = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_INSURANCE)->firstOrFail();
        $secondDocument = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_REGULATORY_CERTIFICATE)->firstOrFail();
        $firstDocument->update(['status' => CompanyMachineryDocument::STATUS_PENDING]);
        $secondDocument->update(['status' => CompanyMachineryDocument::STATUS_PENDING]);

        $response = $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.show', $machinery))
            ->assertOk();
        $html = $response->getContent();
        $buttonPosition = strpos($html, 'Valider la sélection (0)');

        $this->assertSame(2, substr_count($html, 'form="bulk-document-approval-form"'));
        $this->assertGreaterThan(
            max(strpos($html, $firstDocument->label), strpos($html, $secondDocument->label)),
            $buttonPosition,
        );

        $this->post(route('gestionnaire.machinery.documents.approve-selected', $machinery), [
            'document_ids' => [$firstDocument->getKey(), $secondDocument->getKey()],
        ])
            ->assertRedirect(route('gestionnaire.machinery.show', $machinery))
            ->assertSessionHas('status', '2 documents validés.');

        foreach ([$firstDocument, $secondDocument] as $document) {
            $this->assertSame(CompanyMachineryDocument::STATUS_VALIDATED, $document->fresh()->status);
            $this->assertDatabaseHas('company_machinery_histories', [
                'company_machinery_id' => $machinery->getKey(),
                'company_machinery_document_id' => $document->getKey(),
                'action' => 'document_validated',
                'to_status' => CompanyMachineryDocument::STATUS_VALIDATED,
            ]);
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $provider->getKey(),
                'type' => CompanyMachineryWorkflowNotification::class,
                'data->event' => CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                'data->document_id' => $document->getKey(),
            ]);
        }
    }

    public function test_machinery_bulk_validation_rejects_foreign_and_non_pending_documents_without_partial_changes(): void
    {
        $provider = $this->makeProvider('Prestataire sélection engins');
        $manager = $this->makeManager('Gestionnaire sélection engins', ['machinery.view', 'machinery.approve']);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise sélection engins');
        $machinery = $this->makeMachinery($company, 'Engin principal');
        $otherMachinery = $this->makeMachinery($company, 'Engin secondaire');
        $pendingDocument = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_INSURANCE)->firstOrFail();
        $foreignDocument = $otherMachinery->documents()->where('type', CompanyMachineryDocument::TYPE_INSURANCE)->firstOrFail();
        $staleDocument = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_REGULATORY_CERTIFICATE)->firstOrFail();
        $pendingDocument->update(['status' => CompanyMachineryDocument::STATUS_PENDING]);
        $foreignDocument->update(['status' => CompanyMachineryDocument::STATUS_PENDING]);
        $staleDocument->update(['status' => CompanyMachineryDocument::STATUS_REJECTED]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.machinery.documents.approve-selected', $machinery), [
                'document_ids' => [$pendingDocument->getKey(), $foreignDocument->getKey()],
            ])
            ->assertStatus(422);
        $this->post(route('gestionnaire.machinery.documents.approve-selected', $machinery), [
            'document_ids' => [$pendingDocument->getKey(), $staleDocument->getKey()],
        ])->assertStatus(409);

        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $pendingDocument->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $foreignDocument->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_REJECTED, $staleDocument->fresh()->status);
        $this->assertDatabaseMissing('company_machinery_histories', [
            'company_machinery_document_id' => $pendingDocument->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_manager_without_machinery_approval_permission_cannot_bulk_validate_documents(): void
    {
        $provider = $this->makeProvider('Prestataire sans droit engin');
        $manager = $this->makeManager('Gestionnaire sans droit engin', ['machinery.view']);
        $this->assign($manager, $provider);
        $machinery = $this->makeMachinery($this->makeCompany($provider, 'Entreprise sans droit engin'), 'Engin protégé');
        $document = $machinery->documents()->where('type', CompanyMachineryDocument::TYPE_INSURANCE)->firstOrFail();
        $document->update(['status' => CompanyMachineryDocument::STATUS_PENDING]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.machinery.documents.approve-selected', $machinery), [
                'document_ids' => [$document->getKey()],
            ])
            ->assertForbidden();

        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $document->fresh()->status);
    }

    public function test_manager_reviews_only_assigned_providers_and_requires_action_permissions(): void
    {
        $provider = $this->makeProvider('Prestataire affecté aux engins');
        $otherProvider = $this->makeProvider('Prestataire non affecté');
        $manager = $this->makeManager('Gestionnaire affecté aux engins', ['machinery.view', 'machinery.update']);
        $unassignedManager = $this->makeManager('Gestionnaire non affecté aux engins', ['machinery.view']);
        $noPermissionManager = $this->makeManager('Gestionnaire sans droit engins', []);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise autorisée engins');
        $machinery = $this->makeMachinery($company, 'Compresseur');
        $machinery->update(['status' => CompanyMachinery::STATUS_PENDING]);
        $foreignMachinery = $this->makeMachinery(
            $this->makeCompany($otherProvider, 'Entreprise interdite engins'),
            'Bulldozer privé',
        );

        $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.index'))
            ->assertOk()
            ->assertSee('Compresseur')
            ->assertSee('data-navigate-to="'.route('gestionnaire.machinery.show', $machinery).'"', false)
            ->assertDontSee('Bulldozer privé');
        $this->get(route('gestionnaire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('Informations de l’engin');
        $this->put(route('gestionnaire.machinery.update', $machinery), [
            'designation' => 'Compresseur complété',
            'category' => 'Air comprimé',
        ])->assertRedirect(route('gestionnaire.machinery.show', $machinery));
        $this->assertSame('Compresseur complété', $machinery->fresh()->designation);
        $this->assertSame(CompanyMachinery::STATUS_PENDING, $machinery->fresh()->status);
        $this->assertDatabaseHas('company_machinery_histories', [
            'company_machinery_id' => $machinery->getKey(),
            'action' => 'information_completed',
        ]);
        $this->get(route('gestionnaire.machinery.show', $foreignMachinery))->assertNotFound();
        $this->actingAs($unassignedManager)
            ->get(route('gestionnaire.machinery.show', $machinery))
            ->assertNotFound();
        $this->actingAs($noPermissionManager)
            ->get(route('gestionnaire.machinery.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('gestionnaire.machinery.approve', $machinery))
            ->assertForbidden();
    }

    public function test_manager_rejection_requires_a_reason_and_provider_can_resubmit(): void
    {
        $provider = $this->makeProvider('Prestataire rejet engin');
        $manager = $this->makeManager('Gestionnaire rejet engin', [
            'machinery.view',
            'machinery.update',
            'machinery.approve',
            'machinery.reject',
        ]);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise rejet engin');
        $machinery = $this->makeMachinery($company, 'Tracteur');

        $this->uploadRequiredDocuments($provider, $machinery);
        $this->actingAs($provider)->post(route('prestataire.machinery.submit', $machinery));
        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nouvel engin à examiner')
            ->assertSee(route('gestionnaire.machinery.show', $machinery));
        $this->actingAs($manager)
            ->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->post(route('gestionnaire.machinery.reject', $machinery), [
            'reason' => 'Le numéro de série est à vérifier.',
        ])->assertRedirect(route('gestionnaire.machinery.show', $machinery));

        $this->assertSame(CompanyMachinery::STATUS_REJECTED, $machinery->fresh()->status);
        $this->assertSame('Le numéro de série est à vérifier.', $machinery->fresh()->rejection_reason);
        $this->actingAs($provider)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Engin rejeté')
            ->assertSee('Le numéro de série est à vérifier.');
        $this->actingAs($provider)
            ->put(route('prestataire.machinery.update', $machinery), [
                'designation' => 'Tracteur corrigé',
                'category' => 'Agricole',
            ])
            ->assertRedirect(route('prestataire.machinery.show', $machinery));
        $this->post(route('prestataire.machinery.submit', $machinery))
            ->assertRedirect(route('prestataire.machinery.show', $machinery));

        $this->assertSame(CompanyMachinery::STATUS_PENDING, $machinery->fresh()->status);
        $this->assertDatabaseHas('company_machinery_histories', [
            'company_machinery_id' => $machinery->getKey(),
            'action' => 'resubmitted',
        ]);
        $this->assertSame(2, $manager->notifications()
            ->whereIn('data->event', [
                CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED,
                CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED,
            ])->count());
    }

    public function test_machinery_submission_requires_all_required_files_and_keeps_drafts_hidden(): void
    {
        $provider = $this->makeProvider('Prestataire documents obligatoires engin');
        $manager = $this->makeManager('Gestionnaire documents obligatoires engin', ['machinery.view']);
        $this->assign($manager, $provider);
        $machinery = $this->makeMachinery(
            $this->makeCompany($provider, 'Entreprise documents obligatoires engin'),
            'Pelle incomplète',
        );

        $this->actingAs($provider)
            ->from(route('prestataire.machinery.show', $machinery))
            ->post(route('prestataire.machinery.submit', $machinery))
            ->assertRedirect(route('prestataire.machinery.show', $machinery))
            ->assertSessionHasErrors([
                'machinery' => 'Soumission impossible. Veuillez téléverser tous les documents obligatoires : Assurance, Carte grise, Visite technique, Certificat réglementaire.',
            ]);

        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->fresh()->status);
        $this->get(route('prestataire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('Assurance')
            ->assertSee('Carte grise')
            ->assertSee('Visite technique')
            ->assertSee('Certificat réglementaire')
            ->assertSee('disabled', false);
        $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.index'))
            ->assertOk()
            ->assertDontSee('Pelle incomplète');

        foreach (array_slice(CompanyMachineryDocument::requiredTypes(), 0, 3) as $type) {
            $this->uploadDocument($provider, $machinery, $type, $type.'.pdf');
        }

        $this->actingAs($provider)
            ->from(route('prestataire.machinery.show', $machinery))
            ->post(route('prestataire.machinery.submit', $machinery))
            ->assertRedirect(route('prestataire.machinery.show', $machinery))
            ->assertSessionHasErrors([
                'machinery' => 'Soumission impossible. Veuillez téléverser tous les documents obligatoires : Certificat réglementaire.',
            ]);
        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->fresh()->status);

        $missingType = CompanyMachineryDocument::requiredTypes()[3];
        $this->uploadDocument($provider, $machinery, $missingType, $missingType.'.pdf');
        $this->post(route('prestataire.machinery.submit', $machinery))
            ->assertRedirect(route('prestataire.machinery.show', $machinery));
        $this->assertSame(CompanyMachinery::STATUS_PENDING, $machinery->fresh()->status);
        $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.index'))
            ->assertOk()
            ->assertSee('Pelle incomplète');
    }

    public function test_machinery_submission_rejects_required_document_paths_missing_from_private_storage(): void
    {
        $provider = $this->makeProvider('Prestataire fichier manquant engin');
        $machinery = $this->makeMachinery(
            $this->makeCompany($provider, 'Entreprise fichier manquant engin'),
            'Engin fichier manquant',
        );
        $this->uploadRequiredDocuments($provider, $machinery);
        $insurance = $machinery->documents()
            ->where('type', CompanyMachineryDocument::TYPE_INSURANCE)
            ->firstOrFail();
        Storage::disk('documents_private')->delete($insurance->file_path);

        $this->actingAs($provider)
            ->from(route('prestataire.machinery.show', $machinery))
            ->post(route('prestataire.machinery.submit', $machinery))
            ->assertRedirect(route('prestataire.machinery.show', $machinery))
            ->assertSessionHasErrors('machinery');
        $this->assertSame(CompanyMachinery::STATUS_DRAFT, $machinery->fresh()->status);
    }

    public function test_machinery_and_document_approval_wait_for_company_validation(): void
    {
        $provider = $this->makeProvider('Prestataire engin entreprise en attente');
        $manager = $this->makeManager('Gestionnaire engin entreprise en attente', [
            'entreprises.validate',
            'machinery.view',
            'machinery.approve',
        ]);
        $this->assign($manager, $provider);
        $company = $provider->companies()->create([
            'name' => 'Entreprise engins en attente',
            'status' => Company::STATUS_PENDING,
        ]);
        $machinery = $this->makeMachinery($company, 'Grue à valider');
        $machinery->update(['status' => CompanyMachinery::STATUS_PENDING]);
        $document = $machinery->documents()
            ->where('type', CompanyMachineryDocument::TYPE_INSURANCE)
            ->firstOrFail();
        $document->update(['status' => CompanyMachineryDocument::STATUS_PENDING]);
        $blockedMessage = "La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.";

        $this->actingAs($manager)
            ->get(route('gestionnaire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('Entreprise non validée')
            ->assertSeeText($blockedMessage)
            ->assertSee('Valider l’engin')
            ->assertSee('Valider ce document')
            ->assertSee('disabled', false);
        $this->post(route('gestionnaire.machinery.approve', $machinery))
            ->assertStatus(409)
            ->assertSeeText($blockedMessage);
        $this->post(route('gestionnaire.machinery.documents.approve', [$machinery, $document]))
            ->assertStatus(409)
            ->assertSeeText($blockedMessage);

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
        $this->assertSame(CompanyMachinery::STATUS_PENDING, $machinery->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_PENDING, $document->fresh()->status);

        $this->post(route('gestionnaire.machinery.approve', $machinery))
            ->assertRedirect(route('gestionnaire.machinery.show', $machinery));
        $this->post(route('gestionnaire.machinery.documents.approve', [$machinery, $document]))
            ->assertRedirect(route('gestionnaire.machinery.show', $machinery));

        $this->assertSame(CompanyMachinery::STATUS_VALIDATED, $machinery->fresh()->status);
        $this->assertSame(CompanyMachineryDocument::STATUS_VALIDATED, $document->fresh()->status);
    }

    private function makeProvider(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
        $user->roles()->attach(Role::query()->where('name', 'prestataire')->firstOrFail());

        return $user;
    }

    private function makeCompany(User $provider, string $name): Company
    {
        return $provider->companies()->create([
            'name' => $name,
            'status' => Company::STATUS_VALIDATED,
        ]);
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

    private function makeMachinery(Company $company, string $designation): CompanyMachinery
    {
        $machinery = $company->machinery()->create([
            'designation' => $designation,
            'category' => 'Engin test',
            'status' => CompanyMachinery::STATUS_DRAFT,
        ]);

        foreach (CompanyMachineryDocument::allTypes() as $type) {
            $machinery->documents()->create([
                'type' => $type,
                'label' => CompanyMachineryDocument::labelForType($type),
                'status' => CompanyMachineryDocument::STATUS_DRAFT,
            ]);
        }

        return $machinery;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeManager(string $name, array $permissions): User
    {
        $manager = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
        $manager->roles()->attach(Role::query()->where('name', 'gestionnaire')->firstOrFail());
        $manager->permissions()->sync(
            Permission::query()->whereIn('name', $permissions)->pluck('id'),
        );

        return $manager;
    }

    private function assign(User $manager, User $provider): void
    {
        $manager->assignedPrestataires()->attach($provider, ['assigned_by' => $manager->getKey()]);
    }

    private function uploadDocument(
        User $provider,
        CompanyMachinery $machinery,
        string $type,
        string $fileName,
    ): CompanyMachineryDocument {
        $this->actingAs($provider)
            ->post(route('prestataire.machinery.documents.store', [$machinery, $type]), [
                'document' => UploadedFile::fake()->create($fileName, 100, 'application/pdf'),
                'issued_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect(route('prestataire.machinery.show', $machinery));

        return $machinery->documents()->where('type', $type)->firstOrFail();
    }

    private function uploadRequiredDocuments(User $provider, CompanyMachinery $machinery): void
    {
        foreach (CompanyMachineryDocument::requiredTypes() as $type) {
            $document = $machinery->documents()->where('type', $type)->firstOrFail();

            if (blank($document->file_path) || ! Storage::disk('documents_private')->exists($document->file_path)) {
                $this->uploadDocument($provider, $machinery, $type, $type.'.pdf');
            }
        }
    }
}
