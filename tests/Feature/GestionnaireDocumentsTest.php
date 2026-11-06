<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GestionnaireDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gestionnaire_can_view_all_documents_and_document_details(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'title' => 'Dossier de maintenance',
            'reference_number' => 'DOC-2026-001',
            'company_name' => 'Entreprise Exemple',
            'custom_fields' => ['Site' => 'Atelier Nord'],
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.documents.index'))
            ->assertOk()
            ->assertSee('DOC-2026-001')
            ->assertSee('Dossier de maintenance')
            ->assertSee('Entreprise Exemple')
            ->assertSee($prestataire->name)
            ->assertSee('Examiner');

        $this->get(route('gestionnaire.documents.show', $document))
            ->assertOk()
            ->assertSee('Informations du document')
            ->assertSee('Prestataire')
            ->assertSee($prestataire->email)
            ->assertSee('Atelier Nord')
            ->assertSee('Date de début')
            ->assertSee('Date de fin')
            ->assertSee('Valider le document')
            ->assertSee('Motif obligatoire du rejet');
    }

    public function test_gestionnaire_can_search_by_title_reference_company_and_prestataire(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Société Prestataire']);
        $document = $this->createDocument($prestataire, [
            'title' => 'Dossier de conformité',
            'reference_number' => 'REF-2026-ABC',
            'company_name' => 'Compagnie Bâtiment',
        ]);

        $this->actingAs($gestionnaire);

        foreach (['conformité', 'REF-2026-ABC', 'Compagnie Bâtiment', 'Société Prestataire'] as $term) {
            $this->get(route('gestionnaire.documents.index', ['search' => $term]))
                ->assertOk()
                ->assertSee($document->title);
        }
    }

    public function test_untrusted_document_fields_are_escaped_on_the_review_page(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $untrustedText = '<script>alert(1)</script>';
        $document = $this->createDocument($prestataire, [
            'title' => $untrustedText,
            'company_name' => $untrustedText,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.documents.show', $document))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee($untrustedText, false);
    }

    public function test_invalid_sort_option_is_rejected_with_a_french_validation_error(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');

        $this->actingAs($gestionnaire)
            ->from(route('gestionnaire.documents.index'))
            ->get(route('gestionnaire.documents.index', ['sort' => 'title desc']))
            ->assertRedirect(route('gestionnaire.documents.index'))
            ->assertSessionHasErrors([
                'sort' => 'Le tri sélectionné est invalide.',
            ]);
    }

    public function test_gestionnaire_can_filter_sort_and_paginate_documents(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $newestDocument = $this->createDocument($prestataire, [
            'title' => 'Demande récente',
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
        $oldestDocument = $this->createDocument($prestataire, [
            'title' => 'Demande ancienne',
            'status' => Document::STATUS_VALIDATED,
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.documents.index', [
                'status' => Document::STATUS_PENDING,
                'sort' => 'oldest',
            ]))
            ->assertOk()
            ->assertSee($newestDocument->title)
            ->assertDontSee($oldestDocument->title);

        $this->get(route('gestionnaire.documents.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSeeInOrder([$oldestDocument->title, $newestDocument->title]);

        foreach (range(1, 11) as $documentNumber) {
            $this->createDocument($prestataire, [
                'title' => "Document paginé {$documentNumber}",
                'submitted_at' => now()->subMinutes($documentNumber),
            ]);
        }

        $this->get(route('gestionnaire.documents.index'))
            ->assertOk()
            ->assertSee('Document paginé 1')
            ->assertDontSee('Document paginé 11')
            ->assertSee('page=2', false);
    }

    public function test_gestionnaire_can_view_only_pending_documents_on_pending_page(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $pendingDocument = $this->createDocument($prestataire, [
            'title' => 'Document à examiner',
            'status' => Document::STATUS_PENDING,
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Document déjà rejeté',
            'status' => Document::STATUS_REJECTED,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.documents.pending'))
            ->assertOk()
            ->assertSee('attendent une décision')
            ->assertSee($pendingDocument->title)
            ->assertSee('Examiner')
            ->assertDontSee('Document déjà rejeté');
    }

    public function test_gestionnaire_can_download_an_attachment_through_the_private_route(): void
    {
        Storage::fake('documents_private');
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $path = 'prestataires/'.$prestataire->id.'/preuve.pdf';
        Storage::disk('documents_private')->put($path, 'contenu privé');
        $document = $this->createDocument($prestataire, ['attachment_path' => $path]);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.documents.show', $document))
            ->assertOk()
            ->assertDontSee($path, false);

        $this->get(route('gestionnaire.documents.download', $document))
            ->assertOk()
            ->assertHeader('content-disposition')
            ->assertDontSee($path);
    }

    public function test_gestionnaire_can_approve_pending_document_and_notify_prestataire(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $securityLead = $this->userWithRole('responsable_securite');
        $document = $this->createDocument($prestataire, [
            'rejection_comment' => 'Ancien motif à effacer.',
        ]);
        $member = $document->safetyTeamMembers()->create([
            'name' => 'Membre accepté',
            'cin' => 'CIN-APPROVED-001',
            'position' => 'Agent',
        ]);

        $this->actingAs($gestionnaire)
            ->post(route('gestionnaire.documents.approve', $document))
            ->assertRedirect(route('gestionnaire.documents.show', $document));

        $document->refresh();
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $gestionnaire->id,
            'rejection_comment' => null,
            'validated_at' => '2026-11-05 10:00:00',
        ]);
        $this->assertSame($gestionnaire->id, $document->validated_by);
        $this->assertNotNull($document->validated_at);

        $notification = $prestataire->notifications()->firstOrFail();
        $this->assertSame(DocumentWorkflowNotification::class, $notification->type);
        $this->assertSame([
            'event' => DocumentWorkflowNotification::EVENT_VALIDATED,
            'document_id' => $document->id,
            'title' => $document->title,
            'reference_number' => $document->reference_number,
            'status' => Document::STATUS_VALIDATED,
            'comment' => null,
            'prestataire_name' => $prestataire->name,
            'member_names' => [$member->name],
            'submitted_at' => $document->submitted_at->toIso8601String(),
            'validated_at' => $document->validated_at->toIso8601String(),
        ], $notification->data);

        $securityNotification = $securityLead->notifications()->sole();
        $this->assertSame(DocumentWorkflowNotification::EVENT_VALIDATED, $securityNotification->data['event']);
        $this->assertSame([$member->name], $securityNotification->data['member_names']);

        $this->actingAs($securityLead)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($prestataire->name)
            ->assertSee($member->name)
            ->assertSee('Validé le')
            ->assertSee(route('responsable-securite.documents.show', $document));

        $this->actingAs($prestataire)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Votre document a été accepté.')
            ->assertSee($member->name)
            ->assertSee('Validé')
            ->assertSee(route('prestataire.documents.show', $document));
    }

    public function test_gestionnaire_cannot_approve_a_rejected_document(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'status' => Document::STATUS_REJECTED,
            'rejection_comment' => 'Le dossier est incomplet.',
        ]);

        $this->actingAs($gestionnaire)
            ->post(route('gestionnaire.documents.approve', $document))
            ->assertConflict();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_REJECTED,
            'rejection_comment' => 'Le dossier est incomplet.',
        ]);
    }

    public function test_rejection_requires_a_reason_between_ten_and_two_thousand_characters(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);
        $url = route('gestionnaire.documents.reject', $document);
        $returnUrl = route('gestionnaire.documents.show', $document);

        $this->actingAs($gestionnaire)
            ->from($returnUrl)
            ->post($url, ['reason' => ''])
            ->assertRedirect($returnUrl)
            ->assertSessionHasErrors([
                'reason' => 'Veuillez saisir le motif du rejet.',
            ]);

        $this->from($returnUrl)
            ->post($url, ['reason' => 'Court'])
            ->assertRedirect($returnUrl)
            ->assertSessionHasErrors([
                'reason' => 'Le motif du rejet doit contenir au moins 10 caractères.',
            ]);

        $this->from($returnUrl)
            ->post($url, ['reason' => str_repeat('x', 2001)])
            ->assertRedirect($returnUrl)
            ->assertSessionHasErrors([
                'reason' => 'Le motif du rejet ne peut pas dépasser 2 000 caractères.',
            ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_PENDING,
            'rejection_comment' => null,
        ]);
        $this->assertSame(0, $prestataire->notifications()->count());
    }

    public function test_gestionnaire_can_reject_pending_document_and_notify_prestataire_with_reason(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'title' => 'Attestation de sécurité',
        ]);
        $member = $document->safetyTeamMembers()->create([
            'name' => 'Membre refusé',
            'cin' => 'CIN-REJECTED-001',
            'position' => 'Agent',
        ]);
        $reason = 'Veuillez joindre une attestation lisible.';

        $this->actingAs($gestionnaire)
            ->post(route('gestionnaire.documents.reject', $document), ['reason' => $reason])
            ->assertRedirect(route('gestionnaire.documents.show', $document));

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_REJECTED,
            'rejection_comment' => $reason,
            'validated_by' => null,
            'validated_at' => null,
        ]);

        $notification = $prestataire->notifications()->firstOrFail();
        $this->assertSame(DocumentWorkflowNotification::class, $notification->type);
        $this->assertSame(DocumentWorkflowNotification::EVENT_REJECTED, $notification->data['event']);
        $this->assertSame('Attestation de sécurité', $notification->data['title']);
        $this->assertSame($reason, $notification->data['comment']);
        $this->assertSame(Document::STATUS_REJECTED, $notification->data['status']);
        $this->assertSame([$member->name], $notification->data['member_names']);

        $this->actingAs($prestataire)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document refusé')
            ->assertSee($reason)
            ->assertSee('Refusé')
            ->assertSee(route('prestataire.documents.show', $document));
    }

    public function test_gestionnaire_cannot_reject_a_validated_document(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $gestionnaire->id,
            'validated_at' => now(),
        ]);

        $this->actingAs($gestionnaire)
            ->post(route('gestionnaire.documents.reject', $document), [
                'reason' => 'Le document doit rester validé.',
            ])
            ->assertConflict();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $gestionnaire->id,
            'rejection_comment' => null,
        ]);
        $this->assertSame(0, $prestataire->notifications()->count());
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_cannot_access_gestionnaire_document_review_routes(string $roleName): void
    {
        $user = $this->userWithRole($roleName);
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);

        $this->actingAs($user)
            ->get(route('gestionnaire.documents.index'))
            ->assertForbidden();

        $this->get(route('gestionnaire.documents.pending'))->assertForbidden();
        $this->get(route('gestionnaire.documents.show', $document))->assertForbidden();
        $this->get(route('gestionnaire.documents.download', $document))->assertForbidden();
        $this->post(route('gestionnaire.documents.approve', $document))->assertForbidden();
        $this->post(route('gestionnaire.documents.reject', $document), [
            'reason' => 'Motif de rejet suffisamment précis.',
        ])->assertForbidden();
    }

    public function test_guest_cannot_access_gestionnaire_document_review_routes(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);

        $this->get(route('gestionnaire.documents.index'))
            ->assertRedirect(route('login'));

        $this->post(route('gestionnaire.documents.approve', $document))
            ->assertRedirect(route('login'));
    }

    public function test_gestionnaire_dashboard_shows_accurate_statistics_and_recent_submissions(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $baselineStats = [
            'total' => Document::query()->count(),
            'pending' => Document::query()->where('status', Document::STATUS_PENDING)->count(),
            'validated' => Document::query()->where('status', Document::STATUS_VALIDATED)->count(),
            'rejected' => Document::query()->where('status', Document::STATUS_REJECTED)->count(),
        ];
        $recentDocument = $this->createDocument($prestataire, [
            'title' => 'Soumission récente',
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Document ancien rejeté',
            'status' => Document::STATUS_REJECTED,
            'submitted_at' => now()->subDays(3),
        ]);
        $this->createDocument($prestataire, [
            'status' => Document::STATUS_VALIDATED,
            'submitted_at' => now()->subDays(2),
        ]);
        $this->createDocument($prestataire, [
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', [
                'total' => $baselineStats['total'] + 4,
                'pending' => $baselineStats['pending'] + 2,
                'validated' => $baselineStats['validated'] + 1,
                'rejected' => $baselineStats['rejected'] + 1,
            ])
            ->assertSee('Soumissions récentes')
            ->assertSeeInOrder(['Soumission récente', 'Document de test'])
            ->assertSee(route('gestionnaire.documents.pending'));
    }

    public static function unauthorizedRoles(): array
    {
        return [
            'prestataire' => ['prestataire'],
            'responsable securite' => ['responsable_securite'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDocument(User $prestataire, array $attributes = []): Document
    {
        return $prestataire->documents()->create(array_replace([
            'title' => 'Document de test',
            'reference_number' => null,
            'company_name' => 'Entreprise Test',
            'description' => 'Description du document de test.',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
            'status' => Document::STATUS_PENDING,
            'custom_fields' => ['Site' => 'Chantier test'],
            'submitted_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by Gestionnaire document tests.'],
        );
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }
}
