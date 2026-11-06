<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('supportedRoles')]
    public function test_each_supported_role_can_access_the_shared_notifications_page(
        string $roleName,
        string $expectedRoleLabel,
    ): void {
        $user = $this->userWithRole($roleName);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk()
            ->assertSee('Centre de notifications')
            ->assertViewHas('roleLabel', $expectedRoleLabel);
    }

    public function test_guest_is_redirected_to_login_for_notifications_routes(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));

        $this->patch(route('notifications.read-all'))
            ->assertRedirect(route('login'));
    }

    public function test_database_notifications_remain_available_after_logout_and_login(): void
    {
        $user = $this->userWithRole('prestataire');
        $document = $this->createDocument($user, ['title' => 'Dossier persistant']);
        $this->createNotification($user, $document);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('prestataire.dashboard'));

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Dossier persistant');
    }

    public function test_user_only_sees_their_own_notifications_in_newest_first_order(): void
    {
        $this->travelTo('2026-11-05 11:00:00');
        $user = $this->userWithRole('prestataire');
        $otherUser = $this->userWithRole('prestataire');
        $oldDocument = $this->createDocument($user, ['title' => 'Dossier ancien']);
        $newDocument = $this->createDocument($user, ['title' => 'Dossier récent']);
        $otherDocument = $this->createDocument($otherUser, ['title' => 'Dossier confidentiel externe']);
        $this->createNotification($user, $oldDocument, createdAt: '2026-11-05 09:00:00');
        $this->createNotification($user, $newDocument, createdAt: '2026-11-05 10:00:00');
        $this->createNotification($otherUser, $otherDocument);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeInOrder(['Dossier récent', 'Dossier ancien'])
            ->assertSee('Dossier mis à jour')
            ->assertSee(route('prestataire.documents.show', $newDocument))
            ->assertDontSee('Dossier confidentiel externe')
            ->assertSee('05/11/2026 à 10:00');
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = $this->userWithRole('prestataire');
        $otherUser = $this->userWithRole('prestataire');
        $document = $this->createDocument($otherUser);
        $notification = $this->createNotification($otherUser, $document);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'notifiable_id' => $otherUser->id,
            'read_at' => null,
        ]);
    }

    public function test_invalid_notification_id_returns_not_found(): void
    {
        $user = $this->userWithRole('prestataire');

        $this->actingAs($user)
            ->patch(route('notifications.read', 'invalid-notification-id'))
            ->assertNotFound();
    }

    public function test_user_can_mark_one_owned_notification_as_read(): void
    {
        $user = $this->userWithRole('prestataire');
        $otherUser = $this->userWithRole('prestataire');
        $document = $this->createDocument($user);
        $otherDocument = $this->createDocument($otherUser);
        $notification = $this->createNotification($user, $document);
        $otherNotification = $this->createNotification($otherUser, $otherDocument);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'La notification a été marquée comme lue.');

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_marking_an_already_read_notification_does_not_change_its_timestamps(): void
    {
        $user = $this->userWithRole('prestataire');
        $document = $this->createDocument($user);
        $notification = $this->createNotification(
            $user,
            $document,
            createdAt: '2026-11-04 09:00:00',
            readAt: '2026-11-04 10:00:00',
        );
        $originalUpdatedAt = $notification->updated_at->toDateTimeString();

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame('2026-11-04 10:00:00', $notification->fresh()->read_at->toDateTimeString());
        $this->assertSame($originalUpdatedAt, $notification->fresh()->updated_at->toDateTimeString());

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Lue');
    }

    public function test_mark_all_as_read_changes_only_current_users_unread_notifications(): void
    {
        $this->travelTo('2026-11-05 11:00:00');
        $user = $this->userWithRole('prestataire');
        $otherUser = $this->userWithRole('prestataire');
        $document = $this->createDocument($user);
        $otherDocument = $this->createDocument($otherUser);
        $firstUnread = $this->createNotification($user, $document);
        $secondUnread = $this->createNotification($user, $document);
        $alreadyRead = $this->createNotification(
            $user,
            $document,
            createdAt: '2026-11-04 09:00:00',
            readAt: '2026-11-04 10:00:00',
        );
        $otherUnread = $this->createNotification($otherUser, $otherDocument);

        $this->actingAs($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'Toutes les notifications ont été marquées comme lues.');

        $this->assertNotNull($firstUnread->fresh()->read_at);
        $this->assertNotNull($secondUnread->fresh()->read_at);
        $this->assertSame('2026-11-04 10:00:00', $alreadyRead->fresh()->read_at->toDateTimeString());
        $this->assertNull($otherUnread->fresh()->read_at);
    }

    public function test_unread_notification_count_is_accurate_and_user_specific(): void
    {
        $user = $this->userWithRole('gestionnaire');
        $otherUser = $this->userWithRole('gestionnaire');
        $document = $this->createDocument($this->userWithRole('prestataire'));
        $otherDocument = $this->createDocument($this->userWithRole('prestataire'));
        $this->createNotification($user, $document);
        $this->createNotification($user, $document);
        $this->createNotification($otherUser, $otherDocument);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('aria-label="Notifications, 2 non lues"', false)
            ->assertSee('aria-label="2 notifications non lues"', false)
            ->assertSee(route('gestionnaire.documents.show', $document));
    }

    #[DataProvider('supportedRoles')]
    public function test_unread_count_and_mark_all_read_work_for_each_role(string $roleName, string $roleLabel): void
    {
        $user = $this->userWithRole($roleName);
        $anotherUser = $this->userWithRole($roleName);
        $prestataire = $roleName === 'prestataire' ? $user : $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'status' => $roleName === 'responsable_securite'
                ? Document::STATUS_VALIDATED
                : Document::STATUS_PENDING,
        ]);
        $otherDocument = $this->createDocument($prestataire, [
            'status' => $roleName === 'responsable_securite'
                ? Document::STATUS_VALIDATED
                : Document::STATUS_PENDING,
        ]);
        $firstUnread = $this->createNotification($user, $document);
        $secondUnread = $this->createNotification($user, $document);
        $otherUnread = $this->createNotification($anotherUser, $otherDocument);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('roleLabel', $roleLabel)
            ->assertSee('aria-label="Notifications, 2 non lues"', false)
            ->assertSee('aria-label="2 notifications non lues"', false);

        $this->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($firstUnread->fresh()->read_at);
        $this->assertNotNull($secondUnread->fresh()->read_at);
        $this->assertNull($otherUnread->fresh()->read_at);
    }

    public function test_prestataire_cannot_follow_a_foreign_document_link_from_notification_data(): void
    {
        $user = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $foreignDocument = $this->createDocument($anotherPrestataire, [
            'title' => 'Document appartenant à un autre prestataire',
            'reference_number' => 'REF-PRIVE-001',
        ]);
        $this->createNotification($user, $foreignDocument);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Document appartenant à un autre prestataire')
            ->assertDontSee('REF-PRIVE-001')
            ->assertDontSee(route('prestataire.documents.show', $foreignDocument));
    }

    public function test_untrusted_notification_content_is_escaped(): void
    {
        $user = $this->userWithRole('prestataire');
        $document = $this->createDocument($user, [
            'title' => '<script>alert(1)</script>',
        ]);
        $this->createNotification(
            $user,
            $document,
            event: DocumentWorkflowNotification::EVENT_REJECTED,
            comment: '<img src=x onerror=alert(1)>',
        );

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_empty_state_is_shown_without_an_unread_badge(): void
    {
        $user = $this->userWithRole('responsable_securite');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Aucune notification pour le moment')
            ->assertDontSee('notification-count-badge', false);
    }

    public function test_notifications_are_paginated(): void
    {
        $this->travelTo('2026-11-05 11:00:00');
        $user = $this->userWithRole('prestataire');

        foreach (range(1, 11) as $number) {
            $document = $this->createDocument($user, [
                'title' => sprintf('Document notification %02d', $number),
            ]);
            $this->createNotification(
                $user,
                $document,
                createdAt: now()->subMinutes($number)->toDateTimeString(),
            );
        }

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document notification 01')
            ->assertSee('Document notification 10')
            ->assertDontSee('Document notification 11')
            ->assertSee('page=2', false);

        $this->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Document notification 11');
    }

    public function test_responsable_securite_only_sees_links_and_details_for_validated_documents(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');
        $validatedDocument = $this->createDocument($prestataire, [
            'title' => 'Document validé autorisé',
            'reference_number' => 'REF-VALIDE-001',
            'status' => Document::STATUS_VALIDATED,
        ]);
        $pendingDocument = $this->createDocument($prestataire, [
            'title' => 'Document en attente privé',
            'reference_number' => 'REF-ATTENTE-001',
            'status' => Document::STATUS_PENDING,
        ]);
        $rejectedDocument = $this->createDocument($prestataire, [
            'title' => 'Document rejeté privé',
            'reference_number' => 'REF-REJETE-001',
            'status' => Document::STATUS_REJECTED,
            'attachment_path' => 'prestataires/private/rejected.pdf',
        ]);
        $this->createNotification($securityLead, $validatedDocument, DocumentWorkflowNotification::EVENT_VALIDATED);
        $this->createNotification($securityLead, $pendingDocument);
        $this->createNotification(
            $securityLead,
            $rejectedDocument,
            DocumentWorkflowNotification::EVENT_REJECTED,
            comment: 'Motif interne à ne pas afficher.',
        );

        $this->actingAs($securityLead)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document validé autorisé')
            ->assertSee('REF-VALIDE-001')
            ->assertSee(route('responsable-securite.documents.show', $validatedDocument))
            ->assertDontSee('Document en attente privé')
            ->assertDontSee('REF-ATTENTE-001')
            ->assertDontSee('Document rejeté privé')
            ->assertDontSee('REF-REJETE-001')
            ->assertDontSee('Motif interne à ne pas afficher.')
            ->assertDontSee('prestataires/private/rejected.pdf')
            ->assertDontSee(route('responsable-securite.documents.show', $pendingDocument))
            ->assertDontSee(route('responsable-securite.documents.show', $rejectedDocument));
    }

    public function test_deleted_document_notification_does_not_render_a_broken_document_link(): void
    {
        $user = $this->userWithRole('prestataire');
        $document = $this->createDocument($user, ['title' => 'Document supprimé']);
        $this->createNotification($user, $document);
        $documentUrl = route('prestataire.documents.show', $document);
        $document->delete();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document supprimé')
            ->assertDontSee($documentUrl);
    }

    public function test_rejection_notification_generated_by_gestionnaire_is_visible_with_its_reason(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'title' => 'Attestation rejetée',
            'reference_number' => 'REF-REJET-2026',
        ]);
        $reason = 'Veuillez joindre une attestation lisible.';

        $this->actingAs($gestionnaire)
            ->post(route('gestionnaire.documents.reject', $document), ['reason' => $reason])
            ->assertRedirect(route('gestionnaire.documents.show', $document));

        $this->actingAs($prestataire)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document refusé')
            ->assertSee('Attestation rejetée')
            ->assertSee('REF-REJET-2026')
            ->assertSee($reason);
    }

    public static function supportedRoles(): array
    {
        return [
            'prestataire' => ['prestataire', 'Prestataire'],
            'gestionnaire' => ['gestionnaire', 'Gestionnaire'],
            'responsable securite' => ['responsable_securite', 'Responsable sécurité'],
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

    private function createNotification(
        User $user,
        Document $document,
        string $event = DocumentWorkflowNotification::EVENT_UPDATED,
        ?string $createdAt = null,
        ?string $readAt = null,
        ?string $comment = null,
    ): DatabaseNotification {
        $createdAt ??= now()->toDateTimeString();

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => DocumentWorkflowNotification::class,
            'data' => [
                'event' => $event,
                'document_id' => $document->id,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'status' => $document->status,
                'comment' => $comment,
            ],
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by notification feature tests.'],
        );
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }
}
