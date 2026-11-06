<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\Role;
use App\Models\SafetyTeamMember;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafetyTeamMemberControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $memberNumber = 0;

    public function test_prestataire_can_create_a_member_for_their_document(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createValidatedDocument($prestataire);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team.create'))
            ->assertOk()
            ->assertSee($document->title)
            ->assertSee('name="document_id"', false);

        $this->post(route('prestataire.safety-team.store'), [
            'document_id' => $document->id,
            'name' => 'Amine Responsable',
            'cin' => 'CIN-CREATE-001',
            'position' => 'Chef d’équipe',
            'email' => 'amine@example.test',
            'phone' => '+33102030405',
            'id' => 999,
            'created_at' => '2000-01-01',
        ])
            ->assertRedirect(route('prestataire.safety-team'))
            ->assertSessionHas('status', 'Le membre a été ajouté à votre équipe de sécurité.');

        $member = SafetyTeamMember::query()->where('cin', 'CIN-CREATE-001')->sole();
        $this->assertSame($document->id, $member->document_id);
        $this->assertSame('Amine Responsable', $member->name);
        $this->assertSame('Chef d’équipe', $member->position);
        $this->assertSame('amine@example.test', $member->email);
        $this->assertSame('+33102030405', $member->phone);
        $this->assertNotSame(999, $member->id);
        $this->assertNotSame('2000-01-01', $member->created_at->toDateString());
    }

    public function test_create_form_shows_only_the_prestataires_validated_documents(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $validatedDocument = $this->createValidatedDocument($prestataire, [
            'title' => 'Dossier validé du prestataire',
            'reference_number' => 'REF-VALIDATED-001',
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Dossier en attente',
            'status' => Document::STATUS_PENDING,
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Dossier refusé',
            'status' => Document::STATUS_REJECTED,
        ]);
        $this->createValidatedDocument($anotherPrestataire, [
            'title' => 'Dossier validé d’un autre prestataire',
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team.create'))
            ->assertOk()
            ->assertSee($validatedDocument->title)
            ->assertSee('REF-VALIDATED-001')
            ->assertDontSee('Dossier en attente')
            ->assertDontSee('Dossier refusé')
            ->assertDontSee('Dossier validé d’un autre prestataire');
    }

    public function test_create_form_explains_when_no_validated_document_is_available(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $this->createDocument($prestataire, ['status' => Document::STATUS_PENDING]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team.create'))
            ->assertOk()
            ->assertSee('Aucun document validé disponible. Veuillez attendre la validation d’un dossier par le Gestionnaire.')
            ->assertSee('disabled', false);
    }

    public function test_create_form_preserves_the_selected_document_after_validation_errors(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createValidatedDocument($prestataire);

        $this->actingAs($prestataire)
            ->from(route('prestataire.safety-team.create'))
            ->post(route('prestataire.safety-team.store'), [
                'document_id' => $document->id,
                'name' => '',
                'cin' => 'CIN-OLD-DOCUMENT',
                'position' => 'Agent',
            ])
            ->assertRedirect(route('prestataire.safety-team.create'));

        $this->get(route('prestataire.safety-team.create'))
            ->assertOk()
            ->assertSee('<option value="'.$document->id.'" selected>', false);
    }

    public function test_prestataire_sees_only_members_from_their_own_documents(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $ownMember = $this->createMember($this->createDocument($prestataire), [
            'name' => 'Membre propriétaire',
            'cin' => 'CIN-OWN-001',
        ]);
        $this->createMember($this->createDocument($anotherPrestataire), [
            'name' => 'Membre privé externe',
            'cin' => 'CIN-FOREIGN-001',
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team'))
            ->assertOk()
            ->assertSee($ownMember->name)
            ->assertSee('CIN-OWN-001')
            ->assertDontSee('Membre privé externe')
            ->assertDontSee('CIN-FOREIGN-001');
    }

    public function test_prestataire_cannot_access_another_prestataires_member(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $member = $this->createMember($this->createDocument($anotherPrestataire));

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team.edit', $member))
            ->assertNotFound();

        $this->assertDatabaseHas('safety_team_members', ['id' => $member->id]);
    }

    public function test_prestataire_cannot_update_another_prestataires_member(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $member = $this->createMember($this->createDocument($anotherPrestataire), [
            'name' => 'Nom avant attaque',
        ]);

        $this->actingAs($prestataire)
            ->put(route('prestataire.safety-team.update', $member), $this->validPayload($member))
            ->assertNotFound();

        $this->assertSame('Nom avant attaque', $member->fresh()->name);
    }

    public function test_prestataire_cannot_delete_another_prestataires_member(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $member = $this->createMember($this->createDocument($anotherPrestataire));

        $this->actingAs($prestataire)
            ->delete(route('prestataire.safety-team.destroy', $member))
            ->assertNotFound();

        $this->assertModelExists($member);
    }

    public function test_foreign_document_id_is_rejected_when_creating_a_member(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $foreignDocument = $this->createValidatedDocument($anotherPrestataire);

        $this->actingAs($prestataire)
            ->from(route('prestataire.safety-team.create'))
            ->post(route('prestataire.safety-team.store'), [
                'document_id' => $foreignDocument->id,
                'name' => 'Membre interdit',
                'cin' => 'CIN-FOREIGN-DOCUMENT',
                'position' => 'Agent',
            ])
            ->assertRedirect(route('prestataire.safety-team.create'))
            ->assertSessionHasErrors('document_id');

        $this->assertDatabaseMissing('safety_team_members', ['cin' => 'CIN-FOREIGN-DOCUMENT']);
    }

    #[DataProvider('nonValidatedStatuses')]
    public function test_prestataire_cannot_create_a_member_with_a_non_validated_document(string $status): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, ['status' => $status]);

        $this->actingAs($prestataire)
            ->from(route('prestataire.safety-team.create'))
            ->post(route('prestataire.safety-team.store'), [
                'document_id' => $document->id,
                'name' => 'Membre non autorisé',
                'cin' => 'CIN-UNVALIDATED-'.$status,
                'position' => 'Agent',
            ])
            ->assertRedirect(route('prestataire.safety-team.create'))
            ->assertSessionHasErrors([
                'document_id' => 'Le document sélectionné ne vous appartient pas ou n’est pas validé.',
            ]);

        $this->assertDatabaseMissing('safety_team_members', [
            'cin' => 'CIN-UNVALIDATED-'.$status,
        ]);
    }

    public function test_update_cannot_reassign_a_member_to_another_document(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $firstDocument = $this->createDocument($prestataire);
        $secondDocument = $this->createDocument($prestataire, ['title' => 'Autre dossier']);
        $member = $this->createMember($firstDocument, ['cin' => 'CIN-LOCKED-DOCUMENT']);

        $this->actingAs($prestataire)
            ->put(route('prestataire.safety-team.update', $member), [
                ...$this->validPayload($member),
                'document_id' => $secondDocument->id,
            ])
            ->assertSessionHasErrors('document_id');

        $this->assertSame($firstDocument->id, $member->fresh()->document_id);
    }

    public function test_prestataire_can_update_and_delete_a_member_on_an_unvalidated_document(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document, [
            'name' => 'Nom initial',
            'cin' => 'CIN-UPDATE-001',
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team.edit', $member))
            ->assertOk()
            ->assertSee('Modifier un membre')
            ->assertSee('Nom initial')
            ->assertSee($document->title)
            ->assertSee('name="document_id" value="'.$document->id.'"', false);

        $this->put(route('prestataire.safety-team.update', $member), [
            ...$this->validPayload($member),
            'name' => 'Nom modifié',
            'position' => 'Superviseur',
        ])
            ->assertRedirect(route('prestataire.safety-team'))
            ->assertSessionHas('status', 'Les informations du membre ont été mises à jour.');

        $this->assertSame('Nom modifié', $member->fresh()->name);
        $this->assertSame('Superviseur', $member->fresh()->position);

        $this->delete(route('prestataire.safety-team.destroy', $member))
            ->assertRedirect(route('prestataire.safety-team'));

        $this->assertDatabaseMissing('safety_team_members', ['id' => $member->id]);
        $this->assertModelExists($document);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_prestataire_roles_cannot_manage_safety_team_members(string $roleName): void
    {
        $user = $this->userWithRole($roleName);
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document);

        $this->actingAs($user)
            ->get(route('prestataire.safety-team'))
            ->assertForbidden();
        $this->get(route('prestataire.safety-team.create'))->assertForbidden();
        $this->post(route('prestataire.safety-team.store'), $this->validPayload($member))
            ->assertForbidden();
        $this->get(route('prestataire.safety-team.edit', $member))->assertForbidden();
        $this->put(route('prestataire.safety-team.update', $member), $this->validPayload($member))
            ->assertForbidden();
        $this->delete(route('prestataire.safety-team.destroy', $member))->assertForbidden();

        $this->assertModelExists($member);
    }

    public function test_validated_document_members_are_read_only_and_preserved(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, ['status' => Document::STATUS_VALIDATED]);
        $member = $this->createMember($document, ['name' => 'Membre validé']);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team.edit', $member))
            ->assertConflict();

        $this->put(route('prestataire.safety-team.update', $member), $this->validPayload($member))
            ->assertConflict();
        $this->delete(route('prestataire.safety-team.destroy', $member))
            ->assertConflict();
        $this->get(route('prestataire.safety-team'))
            ->assertOk()
            ->assertSee('lecture seule');

        $this->assertModelExists($member);
        $this->assertSame('Membre validé', $member->fresh()->name);
    }

    public function test_adding_a_member_to_a_validated_document_resubmits_and_notifies_all_gestionnaires(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $prestataire = $this->userWithRole('prestataire');
        $firstManager = $this->userWithRole('gestionnaire');
        $secondManager = $this->userWithRole('gestionnaire');
        $document = $this->createValidatedDocument($prestataire, ['submitted_at' => now()->subDay()]);

        $this->actingAs($prestataire)
            ->post(route('prestataire.safety-team.store'), [
                'document_id' => $document->id,
                'name' => 'Membre soumis',
                'cin' => 'CIN-SUBMITTED-MEMBER',
                'position' => 'Agent de sécurité',
            ])
            ->assertRedirect(route('prestataire.safety-team'));

        $document->refresh();
        $this->assertSame(Document::STATUS_PENDING, $document->status);
        $this->assertNull($document->validated_by);
        $this->assertNull($document->validated_at);
        $this->assertSame('2026-11-05 10:00:00', $document->submitted_at->toDateTimeString());

        $revision = $document->revisions()->sole();
        $this->assertSame(DocumentRevision::TYPE_MEMBER_ADDED, $revision->type);
        $this->assertSame('Ajout du membre « Membre soumis » à l’équipe de sécurité.', $revision->description);
        $this->assertSame('Membre soumis', $revision->member_name);
        $this->assertSame($prestataire->id, $revision->user_id);
        $this->assertSame(
            "Ajout d'un nouveau membre à l'équipe de sécurité. Une nouvelle vérification est nécessaire.",
            $revision->reason,
        );
        $this->assertSame('2026-11-05 10:00:00', $revision->created_at->toDateTimeString());

        foreach ([$firstManager, $secondManager] as $manager) {
            $notification = $manager->notifications()->sole();
            $this->assertSame(DocumentWorkflowNotification::EVENT_REVISION_REQUESTED, $notification->data['event']);
            $this->assertSame($document->id, $notification->data['document_id']);
            $this->assertSame(['Membre soumis'], $notification->data['member_names']);
            $this->assertSame(DocumentRevision::TYPE_MEMBER_ADDED, $notification->data['revision_type']);
            $this->assertSame('Membre soumis', $notification->data['revision_member_name']);
            $this->assertSame($revision->reason, $notification->data['comment']);
        }

        $this->actingAs($firstManager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document nécessitant une nouvelle vérification')
            ->assertSee("Le Prestataire a ajouté un nouveau membre à l'équipe de sécurité du document Document équipe sécurité. Une nouvelle validation est nécessaire.")
            ->assertSee($revision->reason)
            ->assertSee('Membre soumis')
            ->assertSee($prestataire->name)
            ->assertSee(route('gestionnaire.documents.show', $document))
            ->assertSee('Modification le');

        $this->get(route('gestionnaire.documents.show', $document))
            ->assertOk()
            ->assertSee('Nouvelle révision demandée')
            ->assertSee('Ajout d’un membre')
            ->assertSee('Motif de la nouvelle révision')
            ->assertSee($revision->reason)
            ->assertSee($prestataire->name)
            ->assertSee('Membre soumis')
            ->assertSee('05/11/2026 à 10:00');

        $this->post(route('gestionnaire.documents.approve', $document))
            ->assertRedirect(route('gestionnaire.documents.show', $document));
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $firstManager->id,
        ]);
    }

    public function test_real_member_updates_and_deletions_are_audited_and_notified(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $prestataire = $this->userWithRole('prestataire');
        $manager = $this->userWithRole('gestionnaire');
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document, ['name' => 'Membre avant modification']);

        $this->actingAs($prestataire)
            ->put(route('prestataire.safety-team.update', $member), [
                'document_id' => $document->id,
                'name' => 'Membre après modification',
                'cin' => $member->cin,
                'position' => $member->position,
            ])
            ->assertRedirect(route('prestataire.safety-team'));

        $this->travelTo('2026-11-05 11:00:00');

        $this->delete(route('prestataire.safety-team.destroy', $member))
            ->assertRedirect(route('prestataire.safety-team'));

        $revisions = $document->revisions()->orderBy('id')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame(DocumentRevision::TYPE_MEMBER_UPDATED, $revisions[0]->type);
        $this->assertSame('Membre après modification', $revisions[0]->member_name);
        $this->assertSame(
            'Modification du membre « Membre avant modification » en « Membre après modification » dans l’équipe de sécurité.',
            $revisions[0]->description,
        );
        $this->assertSame($prestataire->id, $revisions[0]->user_id);
        $this->assertSame(
            "Modification d'un membre de l'équipe de sécurité. Une nouvelle vérification est nécessaire.",
            $revisions[0]->reason,
        );
        $this->assertSame(DocumentRevision::TYPE_MEMBER_DELETED, $revisions[1]->type);
        $this->assertSame('Membre après modification', $revisions[1]->member_name);
        $this->assertSame(
            "Suppression d'un membre de l'équipe de sécurité. Une nouvelle vérification est nécessaire.",
            $revisions[1]->reason,
        );
        $this->assertSame(2, $manager->notifications()->count());

        $this->actingAs($manager)
            ->get(route('gestionnaire.documents.show', $document))
            ->assertOk()
            ->assertSee('Suppression d’un membre')
            ->assertSee('Membre après modification')
            ->assertSee('Historique des modifications')
            ->assertSee('Modification d’un membre')
            ->assertSee('Membre avant modification');
    }

    public function test_unchanged_member_update_does_not_create_a_revision_or_notification(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $prestataire = $this->userWithRole('prestataire');
        $manager = $this->userWithRole('gestionnaire');
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document, [
            'name' => 'Membre inchangé',
            'email' => 'member@example.test',
            'phone' => '0600000000',
        ]);
        $submittedAt = $document->submitted_at->toDateTimeString();

        $this->travelTo('2026-11-05 11:00:00');

        $this->actingAs($prestataire)
            ->put(route('prestataire.safety-team.update', $member), [
                'document_id' => $document->id,
                'name' => $member->name,
                'cin' => $member->cin,
                'position' => $member->position,
                'email' => $member->email,
                'phone' => $member->phone,
            ])
            ->assertRedirect(route('prestataire.safety-team'));

        $this->assertSame(0, $document->revisions()->count());
        $this->assertSame(0, $manager->notifications()->count());
        $this->assertSame($submittedAt, $document->fresh()->submitted_at->toDateTimeString());
    }

    public function test_prestataire_cannot_view_another_prestataires_revision_history(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $anotherPrestataire = $this->userWithRole('prestataire');
        $securityLead = $this->userWithRole('responsable_securite');
        $document = $this->createDocument($anotherPrestataire);
        $document->revisions()->create([
            'type' => DocumentRevision::TYPE_MEMBER_ADDED,
            'description' => 'Ajout d’un membre confidentiel.',
            'member_name' => 'Membre confidentiel',
            'user_id' => $anotherPrestataire->id,
            'reason' => 'Motif confidentiel.',
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.documents.show', $document))
            ->assertNotFound()
            ->assertDontSee('Motif confidentiel.')
            ->assertDontSee('Membre confidentiel');

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.documents.show', $document))
            ->assertNotFound();
    }

    public function test_search_matches_member_name_and_cin_and_pagination_is_retained(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document, [
            'name' => 'Nadia Benali',
            'cin' => 'CIN-SEARCH-001',
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.safety-team', ['search' => 'Nadia']))
            ->assertOk()
            ->assertSee($member->name)
            ->assertDontSee('Autre membre absent');

        $this->get(route('prestataire.safety-team', ['search' => 'CIN-SEARCH-001']))
            ->assertOk()
            ->assertSee($member->name);

        foreach (range(1, 11) as $number) {
            $this->createMember($document, [
                'name' => sprintf('Membre pagination %02d', $number),
                'cin' => sprintf('CIN-PAGE-%02d', $number),
            ]);
        }

        $this->get(route('prestataire.safety-team', ['search' => 'Membre pagination']))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('search=Membre', false);
    }

    public function test_gestionnaire_cannot_use_legacy_team_crud_routes(): void
    {
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document);

        $this->actingAs($manager)
            ->get('/gestionnaire/equipe-securite')
            ->assertNotFound();
        $this->get('/gestionnaire/equipe-securite/create')->assertNotFound();
        $this->post('/gestionnaire/equipe-securite', $this->validPayload($member))->assertNotFound();
        $this->get('/gestionnaire/equipe-securite/'.$member->id.'/edit')->assertNotFound();
        $this->put('/gestionnaire/equipe-securite/'.$member->id, $this->validPayload($member))->assertNotFound();
        $this->delete('/gestionnaire/equipe-securite/'.$member->id)->assertNotFound();

        $this->assertModelExists($member);
    }

    public function test_gestionnaire_can_consult_members_and_prestataire_in_the_submitted_document(): void
    {
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire du dossier']);
        $document = $this->createDocument($prestataire);
        $member = $this->createMember($document, ['name' => 'Membre à vérifier']);

        $this->actingAs($manager)
            ->get(route('gestionnaire.documents.show', $document))
            ->assertOk()
            ->assertSee('Prestataire du dossier')
            ->assertSee('Membre à vérifier')
            ->assertSee($member->cin)
            ->assertSee($member->position)
            ->assertDontSee('Modifier')
            ->assertDontSee('Supprimer');
    }

    public function test_gestionnaire_dashboard_has_no_team_management_entry(): void
    {
        $manager = $this->userWithRole('gestionnaire');

        $this->actingAs($manager)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertDontSee('Gérer l’équipe')
            ->assertDontSee('gestionnaire.safety-team');
    }

    public static function unauthorizedRoles(): array
    {
        return [
            'gestionnaire' => ['gestionnaire'],
            'responsable securite' => ['responsable_securite'],
        ];
    }

    public static function nonValidatedStatuses(): array
    {
        return [
            'pending' => [Document::STATUS_PENDING],
            'rejected' => [Document::STATUS_REJECTED],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createValidatedDocument(User $prestataire, array $attributes = []): Document
    {
        $manager = $this->userWithRole('gestionnaire');

        return $this->createDocument($prestataire, array_replace([
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $manager->getKey(),
            'validated_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDocument(User $prestataire, array $attributes = []): Document
    {
        return $prestataire->documents()->create(array_replace([
            'title' => 'Document équipe sécurité',
            'reference_number' => null,
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(Document $document, array $attributes = []): SafetyTeamMember
    {
        return $document->safetyTeamMembers()->create(array_replace([
            'name' => 'Membre équipe',
            'cin' => 'CIN-DEFAULT-'.(++$this->memberNumber),
            'position' => 'Agent de sécurité',
            'email' => null,
            'phone' => null,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(SafetyTeamMember $member): array
    {
        return [
            'document_id' => $member->document_id,
            'name' => 'Membre valide',
            'cin' => $member->cin,
            'position' => 'Agent',
        ];
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by safety team management tests.'],
        );
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }
}
