<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrestataireDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_prestataire_can_create_a_document_with_private_attachment_and_custom_fields(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');

        $this->actingAs($prestataire)
            ->post(route('prestataire.documents.store'), $this->validDocument([
                'custom_fields' => [
                    ['name' => 'Numéro de chantier', 'value' => 'CH-204'],
                ],
                'attachment' => $this->pngUpload('attestation.png'),
                'prestataire_id' => $anotherPrestataire->id,
                'status' => Document::STATUS_VALIDATED,
            ]))
            ->assertRedirect(route('prestataire.documents.show', Document::query()->latest('id')->first()));

        $document = Document::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'prestataire_id' => $prestataire->id,
            'title' => 'Demande d’accès au chantier',
            'status' => Document::STATUS_PENDING,
            'company_name' => 'Entreprise Exemple',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
        ]);
        $this->assertSame(['Numéro de chantier' => 'CH-204'], $document->custom_fields);
        $this->assertNotNull($document->submitted_at);
        Storage::disk('documents_private')->assertExists($document->attachment_path);
    }

    public function test_submitting_a_document_notifies_every_gestionnaire_with_a_direct_review_link(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $prestataire = $this->userWithRole('prestataire');
        $firstManager = $this->userWithRole('gestionnaire', ['name' => 'Premier gestionnaire']);
        $secondManager = $this->userWithRole('gestionnaire', ['name' => 'Second gestionnaire']);

        $this->actingAs($prestataire)
            ->post(route('prestataire.documents.store'), $this->validDocument([
                'title' => 'Dossier à examiner',
            ]))
            ->assertRedirect();

        $document = $prestataire->documents()->sole();

        foreach ([$firstManager, $secondManager] as $manager) {
            $notification = $manager->notifications()->sole();
            $this->assertSame(DocumentWorkflowNotification::EVENT_SUBMITTED, $notification->data['event']);
            $this->assertSame($document->id, $notification->data['document_id']);
            $this->assertSame('Dossier à examiner', $notification->data['title']);
            $this->assertSame($prestataire->name, $notification->data['prestataire_name']);
            $this->assertSame([], $notification->data['member_names']);
            $this->assertSame('2026-11-05T10:00:00+00:00', $notification->data['submitted_at']);
        }

        $this->actingAs($firstManager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nouveau dossier à examiner')
            ->assertSee('Dossier à examiner')
            ->assertSee($prestataire->name)
            ->assertSee(route('gestionnaire.documents.show', $document))
            ->assertSee('Soumis le');
    }

    public function test_prestataire_sees_only_their_own_documents_and_can_filter_them(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');

        $recentDocument = $this->createDocument($prestataire, [
            'title' => 'Contrat récent',
            'company_name' => 'ACME Bâtiment',
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
        $olderDocument = $this->createDocument($prestataire, [
            'title' => 'Contrat ancien',
            'company_name' => 'ACME Bâtiment',
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now()->subDay(),
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Contrat validé',
            'company_name' => 'ACME Bâtiment',
            'status' => Document::STATUS_VALIDATED,
        ]);
        $this->createDocument($otherPrestataire, [
            'title' => 'Contrat confidentiel d’un autre compte',
            'company_name' => 'ACME Bâtiment',
            'status' => Document::STATUS_PENDING,
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.documents.index', [
                'search' => 'ACME',
                'status' => Document::STATUS_PENDING,
            ]))
            ->assertOk()
            ->assertSee($recentDocument->title)
            ->assertSee($olderDocument->title)
            ->assertSeeInOrder([$recentDocument->title, $olderDocument->title])
            ->assertDontSee('Contrat validé')
            ->assertDontSee('Contrat confidentiel d’un autre compte')
            ->assertSee('Date de transmission')
            ->assertSee('Consulter');
    }

    public function test_prestataire_documents_are_paginated(): void
    {
        $prestataire = $this->userWithRole('prestataire');

        foreach (range(1, 11) as $documentNumber) {
            $this->createDocument($prestataire, [
                'title' => "Document {$documentNumber}",
                'submitted_at' => now()->subMinutes($documentNumber),
            ]);
        }

        $this->actingAs($prestataire)
            ->get(route('prestataire.documents.index'))
            ->assertOk()
            ->assertSee('Document 1')
            ->assertDontSee('Document 11')
            ->assertSee('page=2', false);
    }

    public function test_prestataire_cannot_view_or_download_another_users_document(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $path = 'prestataires/'.$otherPrestataire->id.'/preuve.png';
        Storage::disk('documents_private')->put($path, 'private proof');
        $document = $this->createDocument($otherPrestataire, [
            'attachment_path' => $path,
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.documents.show', $document))
            ->assertNotFound();

        $this->put(
            route('prestataire.documents.update', $document),
            $this->validDocument(),
        )->assertNotFound();

        $this->get(route('prestataire.documents.download', $document))
            ->assertNotFound();
    }

    #[DataProvider('nonEditableStatuses')]
    public function test_prestataire_cannot_edit_pending_or_validated_documents(string $status): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, ['status' => $status]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.documents.edit', $document))
            ->assertForbidden();

        $this->put(
            route('prestataire.documents.update', $document),
            $this->validDocument(['title' => 'Modification interdite']),
        )->assertForbidden();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => $document->title,
            'status' => $status,
        ]);
    }

    public function test_prestataire_can_edit_rejected_document_and_resubmission_clears_rejection_comment(): void
    {
        Storage::fake('documents_private');
        $this->travelTo('2026-11-05 10:00:00');
        $prestataire = $this->userWithRole('prestataire');
        $oldPath = 'prestataires/'.$prestataire->id.'/old-proof.png';
        Storage::disk('documents_private')->put($oldPath, 'old attachment');
        $document = $this->createDocument($prestataire, [
            'status' => Document::STATUS_REJECTED,
            'rejection_comment' => 'Veuillez corriger la date de fin.',
            'attachment_path' => $oldPath,
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.documents.edit', $document))
            ->assertOk()
            ->assertSee('Veuillez corriger la date de fin.');

        $this->put(route('prestataire.documents.update', $document), $this->validDocument([
            'title' => 'Document corrigé',
            'custom_fields' => [
                ['name' => 'Site', 'value' => 'Entrepôt Nord'],
            ],
            'attachment' => $this->pngUpload('nouvelle-preuve.png'),
        ]))
            ->assertRedirect(route('prestataire.documents.show', $document));

        $document->refresh();
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Document corrigé',
            'status' => Document::STATUS_PENDING,
            'rejection_comment' => null,
            'validated_by' => null,
            'validated_at' => null,
            'submitted_at' => '2026-11-05 10:00:00',
        ]);
        $this->assertSame(['Site' => 'Entrepôt Nord'], $document->custom_fields);
        Storage::disk('documents_private')->assertExists($document->attachment_path);
        Storage::disk('documents_private')->assertMissing($oldPath);
    }

    public function test_invalid_end_date_is_rejected_with_a_french_validation_message(): void
    {
        $prestataire = $this->userWithRole('prestataire');

        $this->actingAs($prestataire)
            ->from(route('prestataire.documents.create'))
            ->post(route('prestataire.documents.store'), $this->validDocument([
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-19',
            ]))
            ->assertRedirect(route('prestataire.documents.create'))
            ->assertSessionHasErrors([
                'end_date' => 'La date de fin ne peut pas précéder la date de début.',
            ]);

        $this->assertDatabaseMissing('documents', [
            'title' => 'Demande d’accès au chantier',
            'prestataire_id' => $prestataire->id,
        ]);
    }

    public function test_invalid_attachment_type_is_rejected(): void
    {
        $prestataire = $this->userWithRole('prestataire');

        $this->actingAs($prestataire)
            ->from(route('prestataire.documents.create'))
            ->post(route('prestataire.documents.store'), $this->validDocument([
                'attachment' => UploadedFile::fake()->createWithContent('script.txt', 'contenu non autorisé'),
            ]))
            ->assertRedirect(route('prestataire.documents.create'))
            ->assertSessionHasErrors([
                'attachment' => 'Le fichier doit être au format PDF, JPG ou PNG.',
            ]);
    }

    public function test_attachment_over_ten_megabytes_is_rejected(): void
    {
        $prestataire = $this->userWithRole('prestataire');

        $this->actingAs($prestataire)
            ->from(route('prestataire.documents.create'))
            ->post(route('prestataire.documents.store'), $this->validDocument([
                'attachment' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            ]))
            ->assertRedirect(route('prestataire.documents.create'))
            ->assertSessionHasErrors('attachment');
    }

    public function test_private_attachment_download_is_available_only_to_its_owner(): void
    {
        Storage::fake('documents_private');
        $prestataire = $this->userWithRole('prestataire');
        $path = 'prestataires/'.$prestataire->id.'/preuve.png';
        Storage::disk('documents_private')->put($path, 'private proof');
        $document = $this->createDocument($prestataire, ['attachment_path' => $path]);

        $response = $this->actingAs($prestataire)
            ->get(route('prestataire.documents.download', $document));

        $response->assertOk()
            ->assertHeader('content-disposition');
    }

    #[DataProvider('nonPrestataireRoles')]
    public function test_other_roles_cannot_access_prestataire_document_creation_routes(string $roleName): void
    {
        $user = $this->userWithRole($roleName);

        $this->actingAs($user)
            ->get(route('prestataire.documents.create'))
            ->assertForbidden();

        $this->post(route('prestataire.documents.store'), $this->validDocument())
            ->assertForbidden();
    }

    public static function nonEditableStatuses(): array
    {
        return [
            'pending' => [Document::STATUS_PENDING],
            'validated' => [Document::STATUS_VALIDATED],
        ];
    }

    public static function nonPrestataireRoles(): array
    {
        return [
            'gestionnaire' => ['gestionnaire'],
            'responsable securite' => ['responsable_securite'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validDocument(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Demande d’accès au chantier',
            'company_name' => 'Entreprise Exemple',
            'description' => 'Demande d’accès pour une intervention planifiée.',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
        ], $overrides);
    }

    private function pngUpload(string $name): UploadedFile
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lU8AAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $contents ?: '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDocument(User $prestataire, array $attributes = []): Document
    {
        return $prestataire->documents()->create(array_replace([
            'title' => 'Document de test',
            'company_name' => 'Entreprise Test',
            'description' => 'Description du document de test.',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by prestataire document tests.'],
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
