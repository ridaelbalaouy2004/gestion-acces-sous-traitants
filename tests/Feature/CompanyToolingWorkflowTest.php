<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\CompanyToolingDocumentVersion;
use App\Models\ManagerSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyToolingWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyToolingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('documents_private');
    }

    public function test_tooling_submission_requires_both_required_files_and_keeps_drafts_hidden(): void
    {
        $provider = $this->makeProvider('Prestataire documents obligatoires outillage');
        $manager = $this->makeManager('Gestionnaire documents obligatoires outillage', ['toolings.view']);
        $this->assign($manager, $provider);
        $tooling = $this->makeTooling(
            $this->makeCompany($provider, 'Entreprise documents obligatoires outillage'),
            'Outillage incomplet',
        );

        $this->actingAs($provider)
            ->from(route('prestataire.toolings.show', $tooling))
            ->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling))
            ->assertSessionHasErrors([
                'tooling' => 'Soumission impossible. Veuillez téléverser tous les documents obligatoires : Certificat de contrôle, Certificat de conformité.',
            ]);
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->fresh()->status);
        $this->get(route('prestataire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('Certificat de contrôle')
            ->assertSee('Certificat de conformité')
            ->assertSee('disabled', false);
        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.index'))
            ->assertOk()
            ->assertDontSee('Outillage incomplet');

        $firstRequiredType = CompanyToolingDocument::requiredTypes()[0];
        $this->uploadDocument($provider, $tooling, $firstRequiredType, $firstRequiredType.'.pdf');
        $this->actingAs($provider)
            ->from(route('prestataire.toolings.show', $tooling))
            ->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling))
            ->assertSessionHasErrors([
                'tooling' => 'Soumission impossible. Veuillez téléverser tous les documents obligatoires : Certificat de conformité.',
            ]);
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->fresh()->status);

        $this->uploadRequiredDocuments($provider, $tooling);
        $this->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling));
        $this->assertSame(CompanyTooling::STATUS_PENDING, $tooling->fresh()->status);
        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.index'))
            ->assertOk()
            ->assertSee('Outillage incomplet');
    }

    public function test_tooling_submission_rejects_required_document_paths_missing_from_private_storage(): void
    {
        $provider = $this->makeProvider('Prestataire fichier manquant outillage');
        $tooling = $this->makeTooling(
            $this->makeCompany($provider, 'Entreprise fichier manquant outillage'),
            'Outillage fichier manquant',
        );
        $this->uploadRequiredDocuments($provider, $tooling);
        $inspectionCertificate = $tooling->documents()
            ->where('type', CompanyToolingDocument::TYPE_INSPECTION_CERTIFICATE)
            ->firstOrFail();
        Storage::disk('documents_private')->delete($inspectionCertificate->file_path);

        $this->actingAs($provider)
            ->from(route('prestataire.toolings.show', $tooling))
            ->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling))
            ->assertSessionHasErrors('tooling');
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->fresh()->status);
    }

    public function test_provider_creates_tooling_for_owned_company_and_isolated_records_remain_private(): void
    {
        $provider = $this->makeProvider('Prestataire outillage');
        $otherProvider = $this->makeProvider('Prestataire autre outillage');
        $firstCompany = $this->makeCompany($provider, 'Entreprise Alpha outillage');
        $secondCompany = $this->makeCompany($provider, 'Entreprise Beta outillage');
        $foreignCompany = $this->makeCompany($otherProvider, 'Entreprise privée outillage');

        $this->actingAs($provider)
            ->post(route('prestataire.toolings.store'), [
                'company_id' => $firstCompany->getKey(),
                'designation' => 'Clé dynamométrique',
                'category' => 'Mesure',
                'quantity' => 2,
                'physical_condition' => 'good',
                'internal_reference' => 'OUT-001',
            ])
            ->assertRedirect();
        $firstTooling = CompanyTooling::query()->where('company_id', $firstCompany->getKey())->sole();

        $this->post(route('prestataire.toolings.store'), [
            'company_id' => $secondCompany->getKey(),
            'designation' => 'Pince ampèremétrique',
            'category' => 'Électricité',
            'quantity' => 1,
        ])->assertRedirect();
        $secondTooling = CompanyTooling::query()->where('company_id', $secondCompany->getKey())->sole();

        $this->post(route('prestataire.toolings.store'), [
            'company_id' => $foreignCompany->getKey(),
            'designation' => 'Outillage privé',
            'category' => 'Test',
            'quantity' => 1,
        ])->assertSessionHasErrors('company_id');
        $this->post(route('prestataire.toolings.store'), [
            'company_id' => $firstCompany->getKey(),
            'designation' => 'Quantité invalide',
            'category' => 'Test',
            'quantity' => 0,
        ])->assertSessionHasErrors('quantity');

        $this->get(route('prestataire.toolings.index', ['company_id' => $firstCompany->getKey()]))
            ->assertOk()
            ->assertSee('Clé dynamométrique')
            ->assertSee('data-navigate-to="'.route('prestataire.toolings.show', $firstTooling).'"', false)
            ->assertDontSee('Pince ampèremétrique');
        $this->get(route('prestataire.toolings.show', $secondTooling))->assertOk();
        $foreignTooling = $this->makeTooling($foreignCompany, 'Outillage étranger');
        $this->get(route('prestataire.toolings.show', $foreignTooling))->assertNotFound();

        $this->assertSame($firstCompany->getKey(), $firstTooling->company_id);
        $this->assertSame(2, $firstTooling->quantity);
        $this->assertCount(4, $firstTooling->documents);
        $this->assertDatabaseHas('company_tooling_histories', [
            'company_tooling_id' => $firstTooling->getKey(),
            'action' => 'created',
        ]);
    }

    public function test_provider_can_create_tooling_with_a_private_photo_without_changing_its_validation_workflow(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire photo outillage');
        $company = $this->makeCompany($provider, 'Entreprise photo outillage');
        $photo = $this->toolingPhoto();

        $this->actingAs($provider)
            ->post(route('prestataire.toolings.store'), [
                'company_id' => $company->getKey(),
                'designation' => 'Marteau perforateur',
                'category' => 'Électroportatif',
                'quantity' => 1,
                'photo' => $photo,
            ])
            ->assertRedirect();

        $tooling = CompanyTooling::query()
            ->where('company_id', $company->getKey())
            ->sole();
        $photoPath = $tooling->photo_path;
        $this->assertIsString($photoPath);
        $this->assertStringStartsWith('company-toolings/'.$tooling->getKey().'/photo/', $photoPath);
        Storage::disk('documents_private')->assertExists($photoPath);
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->status);
        $this->assertSame(1, $tooling->history()->count());

        $photoUrl = route('prestataire.toolings.photo', $tooling);
        $listResponse = $this->get(route('prestataire.toolings.index'))
            ->assertOk()
            ->assertSee('src="'.$photoUrl.'"', false)
            ->assertSee('alt="Photo de Marteau perforateur"', false);
        $listHtml = $listResponse->getContent();
        $cellMatch = preg_match(
            '/<td class="px-5 py-4 text-sm font-semibold text-slate-900">(.*?)<\/td>/s',
            $listHtml,
            $toolingCell,
        );

        $this->assertSame(7, substr_count($listHtml, '<th class='));
        $this->assertSame(1, $cellMatch);
        $this->assertStringContainsString('gap-3', $toolingCell[1]);
        $this->assertStringContainsString('src="'.$photoUrl.'"', $toolingCell[1]);
        $this->assertStringContainsString('size-11 shrink-0 rounded-full object-cover', $toolingCell[1]);
        $this->assertStringContainsString('data-tooling-photo-error-fallback', $toolingCell[1]);

        $this->get(route('prestataire.toolings.show', $tooling))
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

        $this->uploadRequiredDocuments($provider, $tooling);
        $inspectionCertificate = $tooling->documents()
            ->where('type', CompanyToolingDocument::TYPE_INSPECTION_CERTIFICATE)
            ->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^certificat-de-controle-marteau-perforateur-\d{8}\.pdf$/',
            $inspectionCertificate->file_name,
        );
        $this->actingAs($provider)
            ->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling));
        $tooling->refresh();
        $this->assertSame(CompanyTooling::STATUS_PENDING, $tooling->status);
        $this->assertSame($photoPath, $tooling->photo_path);
        $this->assertSame(4, $tooling->history()->count());
        $this->assertDatabaseHas('company_tooling_histories', [
            'company_tooling_id' => $tooling->getKey(),
            'action' => 'submitted',
            'from_status' => CompanyTooling::STATUS_DRAFT,
            'to_status' => CompanyTooling::STATUS_PENDING,
        ]);

        $manager = $this->makeManager('Gestionnaire lecture photo outillage', ['toolings.view']);
        $this->assign($manager, $provider);
        $managerPhotoUrl = route('gestionnaire.toolings.photo', $tooling);
        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('src="'.$managerPhotoUrl.'"', false);
        $this->get($managerPhotoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_tooling_without_a_photo_shows_the_wrench_avatar_and_missing_photo_fallback(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire sans photo outillage');
        $company = $this->makeCompany($provider, 'Entreprise sans photo outillage');

        $this->actingAs($provider)
            ->post(route('prestataire.toolings.store'), [
                'company_id' => $company->getKey(),
                'designation' => 'Clé plate',
                'category' => 'Clés',
                'quantity' => 1,
            ])
            ->assertRedirect();
        $tooling = CompanyTooling::query()
            ->where('company_id', $company->getKey())
            ->sole();

        $this->get(route('prestataire.toolings.index'))
            ->assertOk()
            ->assertSee('data-tooling-photo-placeholder', false)
            ->assertSee('data-tooling-wrench-icon', false)
            ->assertSee('aria-label="Aucune photo pour Clé plate"', false)
            ->assertDontSee('src="'.route('prestataire.toolings.photo', $tooling).'"', false);
        $this->get(route('prestataire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('data-tooling-photo-placeholder', false)
            ->assertSee('data-tooling-wrench-icon', false);
        $this->get(route('prestataire.toolings.photo', $tooling))->assertNotFound();

        $tooling->update([
            'photo_path' => 'company-toolings/'.$tooling->getKey().'/photo/absente.png',
        ]);
        $this->get(route('prestataire.toolings.index'))
            ->assertOk()
            ->assertSee('data-company-tooling-photo', false)
            ->assertSee('data-tooling-photo-error-fallback', false)
            ->assertSee('data-tooling-wrench-icon', false);
        $this->get(route('prestataire.toolings.photo', $tooling))->assertNotFound();
    }

    public function test_provider_can_replace_and_remove_tooling_photos_without_deleting_shared_files_or_changing_history(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire remplacement photo outillage');
        $company = $this->makeCompany($provider, 'Entreprise remplacement photo outillage');
        $tooling = $this->makeTooling($company, 'Tournevis isolé');
        $historyCount = $tooling->history()->count();
        $documentCount = CompanyToolingDocument::query()->count();

        $this->actingAs($provider)
            ->put(route('prestataire.toolings.update', $tooling), [
                'designation' => 'Tournevis isolé',
                'category' => 'Outillage test',
                'quantity' => 1,
                'photo' => $this->toolingPhoto('premiere-photo.png'),
            ])
            ->assertRedirect(route('prestataire.toolings.show', $tooling));
        $firstPhotoPath = $tooling->fresh()->photo_path;

        $this->put(route('prestataire.toolings.update', $tooling), [
            'designation' => 'Tournevis isolé',
            'category' => 'Outillage test',
            'quantity' => 1,
            'photo' => $this->toolingPhoto('photo-remplacee.png'),
        ])->assertRedirect(route('prestataire.toolings.show', $tooling));
        $replacementPhotoPath = $tooling->fresh()->photo_path;

        $this->assertNotSame($firstPhotoPath, $replacementPhotoPath);
        Storage::disk('documents_private')->assertMissing($firstPhotoPath);
        Storage::disk('documents_private')->assertExists($replacementPhotoPath);

        $otherTooling = $this->makeTooling($company, 'Pince isolée');
        $otherTooling->update(['photo_path' => $replacementPhotoPath]);
        $this->put(route('prestataire.toolings.update', $tooling), [
            'designation' => 'Tournevis isolé',
            'category' => 'Outillage test',
            'quantity' => 1,
            'remove_photo' => '1',
        ])->assertRedirect(route('prestataire.toolings.show', $tooling));

        $this->assertNull($tooling->fresh()->photo_path);
        $this->assertSame($replacementPhotoPath, $otherTooling->fresh()->photo_path);
        Storage::disk('documents_private')->assertExists($replacementPhotoPath);

        $this->put(route('prestataire.toolings.update', $tooling), [
            'designation' => 'Tournevis isolé',
            'category' => 'Outillage test',
            'quantity' => 1,
            'photo' => $this->toolingPhoto('photo-a-supprimer.png'),
        ])->assertRedirect(route('prestataire.toolings.show', $tooling));
        $photoToRemove = $tooling->fresh()->photo_path;
        $this->put(route('prestataire.toolings.update', $tooling), [
            'designation' => 'Tournevis isolé',
            'category' => 'Outillage test',
            'quantity' => 1,
            'remove_photo' => '1',
        ])->assertRedirect(route('prestataire.toolings.show', $tooling));

        $this->assertNull($tooling->fresh()->photo_path);
        Storage::disk('documents_private')->assertMissing($photoToRemove);
        $this->assertSame($historyCount, $tooling->history()->count());
        $this->assertSame(
            $documentCount + count(CompanyToolingDocument::standardTypes()),
            CompanyToolingDocument::query()->count(),
        );
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->fresh()->status);
    }

    public function test_tooling_photo_upload_rejects_unsupported_and_over_five_megabyte_files(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire validation photo outillage');
        $tooling = $this->makeTooling(
            $this->makeCompany($provider, 'Entreprise validation photo outillage'),
            'Clé à cliquet',
        );

        $this->actingAs($provider)
            ->from(route('prestataire.toolings.edit', $tooling))
            ->put(route('prestataire.toolings.update', $tooling), [
                'designation' => 'Clé à cliquet',
                'category' => 'Outillage test',
                'quantity' => 1,
                'photo' => UploadedFile::fake()->create('photo.gif', 20, 'image/gif'),
            ])
            ->assertRedirect(route('prestataire.toolings.edit', $tooling))
            ->assertSessionHasErrors([
                'photo' => 'La photo doit être au format JPG, JPEG, PNG ou WEBP.',
            ]);

        $this->put(route('prestataire.toolings.update', $tooling), [
            'designation' => 'Clé à cliquet',
            'category' => 'Outillage test',
            'quantity' => 1,
            'photo' => UploadedFile::fake()->create('photo-trop-grande.png', 5121, 'image/png'),
        ])->assertSessionHasErrors([
            'photo' => 'La photo ne peut pas dépasser 5 Mo.',
        ]);

        $this->assertNull($tooling->fresh()->photo_path);
        $this->assertSame([], Storage::disk('documents_private')->allFiles('company-toolings'));
    }

    public function test_tooling_photo_routes_enforce_provider_ownership_and_manager_view_permission(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire propriétaire photo outillage');
        $otherProvider = $this->makeProvider('Autre prestataire photo outillage');
        $tooling = $this->makeTooling(
            $this->makeCompany($provider, 'Entreprise propriétaire photo outillage'),
            'Coffret privé',
        );
        $this->actingAs($provider)
            ->put(route('prestataire.toolings.update', $tooling), [
                'designation' => 'Coffret privé',
                'category' => 'Outillage test',
                'quantity' => 1,
                'photo' => $this->toolingPhoto(),
            ])
            ->assertRedirect();
        $tooling->update(['status' => CompanyTooling::STATUS_PENDING]);

        $this->actingAs($otherProvider)
            ->get(route('prestataire.toolings.photo', $tooling))
            ->assertNotFound();

        $manager = $this->makeManager('Gestionnaire lecture photo outillage affecté', ['toolings.view']);
        $this->assign($manager, $provider);
        $managerPhotoUrl = route('gestionnaire.toolings.photo', $tooling);
        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('src="'.$managerPhotoUrl.'"', false);
        $this->get($managerPhotoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('prestataire.toolings.photo', $tooling))->assertForbidden();

        $unassignedManager = $this->makeManager('Gestionnaire non affecté photo outillage', ['toolings.view']);
        $this->actingAs($unassignedManager)
            ->get($managerPhotoUrl)
            ->assertNotFound();

        $managerWithoutPermission = $this->makeManager('Gestionnaire sans lecture photo outillage', []);
        $this->assign($managerWithoutPermission, $provider);
        $this->actingAs($managerWithoutPermission)
            ->get($managerPhotoUrl)
            ->assertForbidden();
    }

    public function test_provider_filters_toolings_and_pagination_preserves_active_filters(): void
    {
        $provider = $this->makeProvider('Prestataire filtres outillage');
        $company = $this->makeCompany($provider, 'Entreprise filtres outillage');
        $otherCompany = $this->makeCompany($provider, 'Entreprise autre filtre');

        for ($index = 1; $index <= 16; $index++) {
            $this->makeTooling($company, 'Micromètre précis '.$index, [
                'category' => 'Mesure',
                'status' => CompanyTooling::STATUS_VALIDATED,
                'internal_reference' => 'PRECISION-'.$index,
            ]);
        }
        $this->makeTooling($company, 'Tournevis isolé', [
            'category' => 'Électricité',
            'status' => CompanyTooling::STATUS_DRAFT,
        ]);
        $this->makeTooling($otherCompany, 'Micromètre autre entreprise', [
            'category' => 'Mesure',
            'status' => CompanyTooling::STATUS_VALIDATED,
            'internal_reference' => 'PRECISION-OTHER',
        ]);

        $this->actingAs($provider)
            ->get(route('prestataire.toolings.index', [
                'company_id' => $company->getKey(),
                'category' => 'Mesure',
                'status' => 'validated',
                'search' => 'PRECISION',
            ]))
            ->assertOk()
            ->assertSee('Micromètre précis 1')
            ->assertDontSee('Tournevis isolé')
            ->assertDontSee('Micromètre autre entreprise')
            ->assertSee('category=Mesure')
            ->assertSee('search=PRECISION')
            ->assertSee('page=2');

        $this->get(route('prestataire.toolings.index', ['category' => 'Électricité']))
            ->assertOk()
            ->assertSee('Tournevis isolé')
            ->assertDontSee('Micromètre précis 1');
        $this->get(route('prestataire.toolings.index', ['search' => 'PRECISION-3']))
            ->assertOk()
            ->assertSee('Micromètre précis 3')
            ->assertDontSee('Micromètre précis 2');
    }

    public function test_manager_can_bulk_validate_pending_documents_for_one_tooling_after_all_document_cards(): void
    {
        $provider = $this->makeProvider('Prestataire validation groupée outillage');
        $manager = $this->makeManager('Gestionnaire validation groupée outillage', ['toolings.view', 'toolings.approve']);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise validation groupée outillage');
        $tooling = $this->makeTooling($company, 'Coffret validation groupée');
        $tooling->update(['status' => CompanyTooling::STATUS_PENDING]);
        $firstDocument = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)->firstOrFail();
        $secondDocument = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_ACQUISITION_PROOF)->firstOrFail();
        $firstDocument->update(['status' => CompanyToolingDocument::STATUS_PENDING]);
        $secondDocument->update(['status' => CompanyToolingDocument::STATUS_PENDING]);

        $response = $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk();
        $html = $response->getContent();
        $buttonPosition = strpos($html, 'Valider la sélection (0)');

        $this->assertSame(2, substr_count($html, 'form="bulk-document-approval-form"'));
        $this->assertGreaterThan(
            max(strpos($html, $firstDocument->label), strpos($html, $secondDocument->label)),
            $buttonPosition,
        );

        $this->post(route('gestionnaire.toolings.documents.approve-selected', $tooling), [
            'document_ids' => [$firstDocument->getKey(), $secondDocument->getKey()],
        ])
            ->assertRedirect(route('gestionnaire.toolings.show', $tooling))
            ->assertSessionHas('status', '2 documents validés.');

        foreach ([$firstDocument, $secondDocument] as $document) {
            $this->assertSame(CompanyToolingDocument::STATUS_VALIDATED, $document->fresh()->status);
            $this->assertDatabaseHas('company_tooling_histories', [
                'company_tooling_id' => $tooling->getKey(),
                'company_tooling_document_id' => $document->getKey(),
                'action' => 'document_validated',
                'to_status' => CompanyToolingDocument::STATUS_VALIDATED,
            ]);
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $provider->getKey(),
                'type' => CompanyToolingWorkflowNotification::class,
                'data->event' => CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                'data->document_id' => $document->getKey(),
            ]);
        }
    }

    public function test_tooling_bulk_validation_rejects_foreign_and_non_pending_documents_without_partial_changes(): void
    {
        $provider = $this->makeProvider('Prestataire sélection outillages');
        $manager = $this->makeManager('Gestionnaire sélection outillages', ['toolings.view', 'toolings.approve']);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise sélection outillages');
        $tooling = $this->makeTooling($company, 'Outillage principal');
        $otherTooling = $this->makeTooling($company, 'Outillage secondaire');
        $pendingDocument = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)->firstOrFail();
        $foreignDocument = $otherTooling->documents()->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)->firstOrFail();
        $staleDocument = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_ACQUISITION_PROOF)->firstOrFail();
        $pendingDocument->update(['status' => CompanyToolingDocument::STATUS_PENDING]);
        $foreignDocument->update(['status' => CompanyToolingDocument::STATUS_PENDING]);
        $staleDocument->update(['status' => CompanyToolingDocument::STATUS_VALIDATED]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.toolings.documents.approve-selected', $tooling), [
                'document_ids' => [$pendingDocument->getKey(), $foreignDocument->getKey()],
            ])
            ->assertStatus(422);
        $this->post(route('gestionnaire.toolings.documents.approve-selected', $tooling), [
            'document_ids' => [$pendingDocument->getKey(), $staleDocument->getKey()],
        ])->assertStatus(409);

        $this->assertSame(CompanyToolingDocument::STATUS_PENDING, $pendingDocument->fresh()->status);
        $this->assertSame(CompanyToolingDocument::STATUS_PENDING, $foreignDocument->fresh()->status);
        $this->assertSame(CompanyToolingDocument::STATUS_VALIDATED, $staleDocument->fresh()->status);
        $this->assertDatabaseMissing('company_tooling_histories', [
            'company_tooling_document_id' => $pendingDocument->getKey(),
            'action' => 'document_validated',
        ]);
    }

    public function test_manager_without_tooling_approval_permission_cannot_bulk_validate_documents(): void
    {
        $provider = $this->makeProvider('Prestataire sans droit outillage');
        $manager = $this->makeManager('Gestionnaire sans droit outillage', ['toolings.view']);
        $this->assign($manager, $provider);
        $tooling = $this->makeTooling($this->makeCompany($provider, 'Entreprise sans droit outillage'), 'Outillage protégé');
        $document = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)->firstOrFail();
        $document->update(['status' => CompanyToolingDocument::STATUS_PENDING]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.toolings.documents.approve-selected', $tooling), [
                'document_ids' => [$document->getKey()],
            ])
            ->assertForbidden();

        $this->assertSame(CompanyToolingDocument::STATUS_PENDING, $document->fresh()->status);
    }

    public function test_manager_access_requires_permission_assignment_and_scopes_company_data(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire affecté outillage');
        $otherProvider = $this->makeProvider('Prestataire non affecté outillage');
        $manager = $this->makeManager('Gestionnaire outillage affecté', ['toolings.view']);
        $managerWithoutPermission = $this->makeManager('Gestionnaire sans permission outillage', []);
        $this->assign($manager, $provider);
        $company = $this->makeCompany($provider, 'Entreprise accessible outillage');
        $tooling = $this->makeTooling($company, 'Niveau laser');
        $technicalSheet = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)->firstOrFail();
        $this->uploadDocument($provider, $tooling, $technicalSheet->type, 'fiche-manager.pdf');
        $tooling->update(['status' => CompanyTooling::STATUS_PENDING]);
        $filteredTooling = $this->makeTooling($company, 'Micromètre affecté', [
            'category' => 'Mesure',
            'status' => CompanyTooling::STATUS_PENDING,
            'internal_reference' => 'MES-001',
        ]);
        $foreignTooling = $this->makeTooling(
            $this->makeCompany($otherProvider, 'Entreprise inaccessible outillage'),
            'Clé privée',
        );

        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.index'))
            ->assertOk()
            ->assertSee('Niveau laser')
            ->assertSee('data-navigate-to="'.route('gestionnaire.toolings.show', $tooling).'"', false)
            ->assertDontSee('Clé privée');
        $this->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('Informations de l’outillage');
        $this->get(route('gestionnaire.toolings.show', $foreignTooling))->assertNotFound();
        $this->get(route('gestionnaire.toolings.index', [
            'prestataire_id' => $provider->getKey(),
            'company_id' => $company->getKey(),
            'category' => 'Mesure',
            'status' => 'pending',
            'search' => 'MES-001',
        ]))
            ->assertOk()
            ->assertSee($filteredTooling->designation)
            ->assertDontSee($tooling->designation);
        $this->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Niveau laser modifié',
            'category' => 'Mesure',
            'quantity' => 1,
        ])->assertForbidden();
        $this->post(route('gestionnaire.toolings.approve', $tooling))->assertForbidden();
        $this->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet'])->assertForbidden();
        $this->get(route('gestionnaire.toolings.documents.download', [$tooling, $technicalSheet]))->assertForbidden();
        $this->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertDontSee(route('gestionnaire.toolings.documents.download', [$tooling, $technicalSheet]));
        $this->actingAs($managerWithoutPermission)
            ->get(route('gestionnaire.toolings.index'))
            ->assertForbidden();
        $this->assertFalse($managerWithoutPermission->permissions()->where('name', 'toolings.view')->exists());
    }

    public function test_primary_manager_can_manage_toolings_without_automatic_employee_permissions(): void
    {
        $primaryManager = $this->makeManager('Gestionnaire Principal outillage', []);
        ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->update([
            'principal_user_id' => $primaryManager->getKey(),
        ]);
        $provider = $this->makeProvider('Prestataire du principal outillage');
        $tooling = $this->makeTooling($this->makeCompany($provider, 'Entreprise du principal'), 'Coffret outils');
        $this->uploadRequiredDocuments($provider, $tooling);

        $employee = $this->makeManager('Employé Gestionnaire outillage', []);
        $this->assign($employee, $provider);

        $this->assertTrue($primaryManager->hasPermission('toolings.view'));
        $this->assertFalse($employee->hasPermission('toolings.view'));
        $this->actingAs($primaryManager)
            ->get(route('gestionnaire.toolings.show', $tooling))
            ->assertNotFound();
        $this->actingAs($provider)
            ->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling));
        $this->actingAs($primaryManager)
            ->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('Coffret outils');
        $this->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Coffret vérifié',
            'category' => 'Maintenance',
            'quantity' => 3,
            'physical_condition' => 'good',
        ])->assertRedirect(route('gestionnaire.toolings.show', $tooling));
        $this->assertSame('Coffret vérifié', $tooling->fresh()->designation);
        $this->assertSame(3, $tooling->fresh()->quantity);
        $this->assertDatabaseHas('company_tooling_histories', [
            'company_tooling_id' => $tooling->getKey(),
            'action' => 'information_completed',
        ]);
        $this->actingAs($primaryManager)
            ->post(route('gestionnaire.toolings.approve', $tooling))
            ->assertRedirect(route('gestionnaire.toolings.show', $tooling));
        $this->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Modification après validation',
            'category' => 'Maintenance',
            'quantity' => 3,
        ])->assertStatus(409);
        $this->assertSame('Coffret vérifié', $tooling->fresh()->designation);
        $this->actingAs($primaryManager)
            ->get(route('gestionnaire.employes.show', $employee))
            ->assertOk()
            ->assertSee('Mes outillages')
            ->assertSee('Consulter les outillages');
        $this->actingAs($employee)->get(route('gestionnaire.toolings.index'))->assertForbidden();

        $toolingsViewPermission = Permission::query()->where('name', 'toolings.view')->firstOrFail();
        $this->actingAs($primaryManager)
            ->put(route('gestionnaire.employes.permissions.update', $employee), [
                'permission_ids' => [$toolingsViewPermission->getKey()],
            ])
            ->assertRedirect(route('gestionnaire.employes.show', $employee));
        $this->assertTrue($employee->fresh()->hasPermission('toolings.view'));
    }

    public function test_manager_rejection_requires_reason_and_provider_can_correct_and_resubmit(): void
    {
        $provider = $this->makeProvider('Prestataire rejet outillage');
        $manager = $this->makeManager('Gestionnaire rejet outillage', ['toolings.view', 'toolings.approve', 'toolings.reject']);
        $unassignedManager = $this->makeManager('Gestionnaire non affecté outillage', ['toolings.view']);
        $this->assign($manager, $provider);
        $tooling = $this->makeTooling($this->makeCompany($provider, 'Entreprise rejet outillage'), 'Perceuse');

        $this->uploadRequiredDocuments($provider, $tooling);
        $this->actingAs($provider)
            ->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling));
        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nouvel outillage à examiner')
            ->assertSee(route('gestionnaire.toolings.show', $tooling));
        $this->assertSame(1, $manager->notifications()
            ->where('type', CompanyToolingWorkflowNotification::class)
            ->where('data->event', CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED)
            ->count());
        $this->assertSame(0, $unassignedManager->notifications()
            ->where('type', CompanyToolingWorkflowNotification::class)
            ->count());
        $this->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->post(route('gestionnaire.toolings.reject', $tooling), [
            'reason' => 'La référence interne est à confirmer.',
        ])->assertRedirect(route('gestionnaire.toolings.show', $tooling));

        $this->assertSame(CompanyTooling::STATUS_REJECTED, $tooling->fresh()->status);
        $this->assertSame('La référence interne est à confirmer.', $tooling->fresh()->rejection_reason);
        $this->actingAs($provider)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Outillage rejeté')
            ->assertSee('La référence interne est à confirmer.');
        $this->put(route('prestataire.toolings.update', $tooling), [
            'designation' => 'Perceuse corrigée',
            'category' => 'Électroportatif',
            'quantity' => 1,
            'internal_reference' => 'P-20',
        ])->assertRedirect(route('prestataire.toolings.show', $tooling));
        $this->post(route('prestataire.toolings.submit', $tooling))
            ->assertRedirect(route('prestataire.toolings.show', $tooling));

        $this->assertSame(CompanyTooling::STATUS_PENDING, $tooling->fresh()->status);
        $this->assertDatabaseHas('company_tooling_histories', [
            'company_tooling_id' => $tooling->getKey(),
            'action' => 'resubmitted',
        ]);
        $this->assertSame(2, $manager->notifications()
            ->where('type', CompanyToolingWorkflowNotification::class)
            ->whereIn('data->event', [
                CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED,
                CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED,
            ])->count());
        $this->actingAs($manager)
            ->post(route('gestionnaire.toolings.approve', $tooling))
            ->assertRedirect(route('gestionnaire.toolings.show', $tooling));
        $this->assertSame(CompanyTooling::STATUS_VALIDATED, $tooling->fresh()->status);
        $this->post(route('gestionnaire.toolings.approve', $tooling))->assertStatus(409);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $provider->getKey(),
            'type' => CompanyToolingWorkflowNotification::class,
            'data->event' => CompanyToolingWorkflowNotification::EVENT_TOOLING_VALIDATED,
        ]);
    }

    public function test_tooling_and_document_approval_wait_for_company_validation(): void
    {
        $provider = $this->makeProvider('Prestataire outillage entreprise en attente');
        $manager = $this->makeManager('Gestionnaire outillage entreprise en attente', [
            'entreprises.validate',
            'toolings.view',
            'toolings.approve',
        ]);
        $this->assign($manager, $provider);
        $company = $provider->companies()->create([
            'name' => 'Entreprise outillages en attente',
            'status' => Company::STATUS_PENDING,
        ]);
        $tooling = $this->makeTooling($company, 'Perceuse à valider', [
            'status' => CompanyTooling::STATUS_PENDING,
        ]);
        $document = $tooling->documents()
            ->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)
            ->firstOrFail();
        $document->update(['status' => CompanyToolingDocument::STATUS_PENDING]);
        $blockedMessage = "La validation de cette ressource est impossible tant que l'entreprise associée n'est pas validée.";

        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('Entreprise non validée')
            ->assertSeeText($blockedMessage)
            ->assertSee('Valider l’outillage')
            ->assertSee('Valider ce document')
            ->assertSee('disabled', false);
        $this->post(route('gestionnaire.toolings.approve', $tooling))
            ->assertStatus(409)
            ->assertSeeText($blockedMessage);
        $this->post(route('gestionnaire.toolings.documents.approve', [$tooling, $document]))
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
        $this->assertSame(CompanyTooling::STATUS_PENDING, $tooling->fresh()->status);
        $this->assertSame(CompanyToolingDocument::STATUS_PENDING, $document->fresh()->status);

        $this->post(route('gestionnaire.toolings.approve', $tooling))
            ->assertRedirect(route('gestionnaire.toolings.show', $tooling));
        $this->post(route('gestionnaire.toolings.documents.approve', [$tooling, $document]))
            ->assertRedirect(route('gestionnaire.toolings.show', $tooling));

        $this->assertSame(CompanyTooling::STATUS_VALIDATED, $tooling->fresh()->status);
        $this->assertSame(CompanyToolingDocument::STATUS_VALIDATED, $document->fresh()->status);
    }

    public function test_document_review_is_independent_and_replacements_keep_private_versions(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire documents outillage');
        $manager = $this->makeManager('Gestionnaire documents outillage', [
            'toolings.view',
            'toolings.approve',
            'toolings.reject',
            'toolings.download',
        ]);
        $this->assign($manager, $provider);
        $tooling = $this->makeTooling($this->makeCompany($provider, 'Entreprise documents outillage'), 'Pied à coulisse');
        $technicalSheet = $tooling->documents()->where('type', CompanyToolingDocument::TYPE_TECHNICAL_SHEET)->firstOrFail();

        $this->actingAs($provider)
            ->post(route('prestataire.toolings.documents.store', [$tooling, $technicalSheet->type]), [
                'document' => UploadedFile::fake()->create('fiche.txt', 50, 'text/plain'),
            ])
            ->assertSessionHasErrors('document');
        $this->uploadDocument($provider, $tooling, $technicalSheet->type, 'fiche-technique-v1.pdf');
        $firstVersion = $technicalSheet->versions()->sole();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->getKey(),
            'type' => CompanyToolingWorkflowNotification::class,
            'data->event' => CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
        ]);

        $this->actingAs($manager)
            ->post(route('gestionnaire.toolings.documents.reject', [$tooling, $technicalSheet]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->post(route('gestionnaire.toolings.documents.reject', [$tooling, $technicalSheet]), [
            'reason' => 'La fiche doit être plus lisible.',
        ])->assertRedirect(route('gestionnaire.toolings.show', $tooling));

        $this->assertSame(CompanyToolingDocument::STATUS_REJECTED, $technicalSheet->fresh()->status);
        $this->assertSame(CompanyTooling::STATUS_REJECTED, $tooling->fresh()->status);
        $this->actingAs($provider)
            ->get(route('prestataire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('data-document-rejection-reason="'.$technicalSheet->getKey().'"', false)
            ->assertSee('La fiche doit être plus lisible.');
        $this->uploadDocument($provider, $tooling, $technicalSheet->type, 'fiche-technique-v2.pdf');

        $versions = $technicalSheet->versions()->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertFalse($versions[0]->is_current);
        $this->assertTrue($versions[1]->is_current);
        Storage::disk('documents_private')->assertExists($firstVersion->file_path);
        Storage::disk('documents_private')->assertExists($versions[1]->file_path);
        $this->assertSame(CompanyToolingDocument::STATUS_PENDING, $technicalSheet->fresh()->status);
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->fresh()->status);
        $this->get(route('prestataire.toolings.show', $tooling))
            ->assertOk()
            ->assertDontSee('data-document-rejection-reason="'.$technicalSheet->getKey().'"', false);

        $this->actingAs($manager)
            ->post(route('gestionnaire.toolings.documents.approve', [$tooling, $technicalSheet]))
            ->assertRedirect(route('gestionnaire.toolings.show', $tooling));
        $this->assertSame(CompanyToolingDocument::STATUS_VALIDATED, $technicalSheet->fresh()->status);
        $this->assertSame(CompanyTooling::STATUS_DRAFT, $tooling->fresh()->status);

        $this->actingAs($provider)
            ->get(route('prestataire.toolings.documents.versions.download', [$tooling, $technicalSheet, $firstVersion]))
            ->assertOk();
        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.documents.download', [$tooling, $technicalSheet]))
            ->assertOk()
            ->assertDownload($technicalSheet->file_name);
        $this->get(route('gestionnaire.toolings.documents.versions.download', [$tooling, $technicalSheet, $firstVersion]))
            ->assertOk();
        $foreignProvider = $this->makeProvider('Prestataire fichiers étrangers');
        $foreignTooling = $this->makeTooling($this->makeCompany($foreignProvider, 'Entreprise fichier étrangère'), 'Outillage étranger');
        $foreignDocument = $foreignTooling->documents()->firstOrFail();
        $this->actingAs($provider)
            ->get(route('prestataire.toolings.documents.download', [$tooling, $foreignDocument]))
            ->assertNotFound();
        $this->actingAs($manager)
            ->get(route('gestionnaire.toolings.documents.download', [$tooling, $foreignDocument]))
            ->assertNotFound();
        $this->assertSame(2, CompanyToolingDocumentVersion::query()->count());
        $this->assertDatabaseHas('company_tooling_histories', [
            'company_tooling_id' => $tooling->getKey(),
            'company_tooling_document_id' => $technicalSheet->getKey(),
            'action' => 'document_replaced',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $provider->getKey(),
            'type' => CompanyToolingWorkflowNotification::class,
            'data->event' => CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $provider->getKey(),
            'type' => CompanyToolingWorkflowNotification::class,
            'data->event' => CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
        ]);
    }

    public function test_custom_documents_are_supported_and_responsable_securite_has_no_tooling_access(): void
    {
        Storage::fake('documents_private');
        $provider = $this->makeProvider('Prestataire document personnalisé');
        $tooling = $this->makeTooling($this->makeCompany($provider, 'Entreprise document personnalisé'), 'Tournevis isolé');

        $this->actingAs($provider)
            ->post(route('prestataire.toolings.documents.custom.store', $tooling), [
                'label' => 'Attestation de calibration',
                'document' => UploadedFile::fake()->create('calibration.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('prestataire.toolings.show', $tooling));
        $customDocument = $tooling->documents()->where('label', 'Attestation de calibration')->firstOrFail();
        Storage::disk('documents_private')->assertExists($customDocument->file_path);
        $this->get(route('prestataire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('Attestation de calibration');

        $securityOfficer = $this->userWithRole('responsable_securite');
        $this->actingAs($securityOfficer)
            ->get(route('gestionnaire.toolings.index'))
            ->assertForbidden();
    }

    private function makeProvider(string $name): User
    {
        return $this->userWithRole('prestataire', [
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
    }

    private function makeCompany(User $provider, string $name): Company
    {
        return $provider->companies()->create([
            'name' => $name,
            'status' => Company::STATUS_VALIDATED,
        ]);
    }

    private function toolingPhoto(string $name = 'photo.png'): UploadedFile
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTooling(Company $company, string $designation, array $attributes = []): CompanyTooling
    {
        $tooling = $company->toolings()->create(array_replace([
            'designation' => $designation,
            'category' => 'Outillage test',
            'quantity' => 1,
            'status' => CompanyTooling::STATUS_DRAFT,
        ], $attributes));

        foreach (CompanyToolingDocument::standardTypes() as $type) {
            $tooling->documents()->create([
                'type' => $type,
                'label' => CompanyToolingDocument::labelForType($type),
                'status' => CompanyToolingDocument::STATUS_DRAFT,
            ]);
        }

        return $tooling;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeManager(string $name, array $permissions): User
    {
        $manager = $this->userWithRole('gestionnaire', [
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);
        $manager->permissions()->sync(
            Permission::query()->whereIn('name', $permissions)->pluck('id'),
        );

        return $manager;
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function assign(User $manager, User $provider): void
    {
        $manager->assignedPrestataires()->attach($provider, ['assigned_by' => $manager->getKey()]);
    }

    private function uploadDocument(
        User $provider,
        CompanyTooling $tooling,
        string $type,
        string $fileName,
    ): CompanyToolingDocument {
        $this->actingAs($provider)
            ->post(route('prestataire.toolings.documents.store', [$tooling, $type]), [
                'document' => UploadedFile::fake()->create($fileName, 100, 'application/pdf'),
                'issued_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect(route('prestataire.toolings.show', $tooling));

        return $tooling->documents()->where('type', $type)->firstOrFail();
    }

    private function uploadRequiredDocuments(User $provider, CompanyTooling $tooling): void
    {
        foreach (CompanyToolingDocument::requiredTypes() as $type) {
            $document = $tooling->documents()->where('type', $type)->firstOrFail();

            if (blank($document->file_path) || ! Storage::disk('documents_private')->exists($document->file_path)) {
                $this->uploadDocument($provider, $tooling, $type, $type.'.pdf');
            }
        }
    }
}
