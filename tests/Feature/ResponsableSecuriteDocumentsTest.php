<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResponsableSecuriteDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_responsable_securite_sees_only_validated_documents_and_can_search_them(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $manager = $this->userWithRole('gestionnaire', ['name' => 'Gestionnaire Validateur']);
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire Recherche']);
        $validatedDocument = $this->createDocument($prestataire, [
            'title' => 'Dossier conformité',
            'reference_number' => 'REF-SEC-2026-001',
            'company_name' => 'Entreprise Recherche',
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $manager->id,
            'submitted_at' => '2026-11-04 09:15:00',
            'validated_at' => '2026-11-05 10:30:00',
        ]);
        $validatedMember = $validatedDocument->safetyTeamMembers()->create([
            'name' => 'Membre validé visible',
            'cin' => 'CIN-VALIDATED-001',
            'position' => 'Superviseur',
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Document en attente confidentiel',
            'status' => Document::STATUS_PENDING,
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Document rejeté confidentiel',
            'status' => Document::STATUS_REJECTED,
        ]);

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.documents.index'))
            ->assertOk()
            ->assertSee($validatedDocument->reference_number)
            ->assertSee($validatedDocument->title)
            ->assertSee('Entreprise Recherche')
            ->assertSee('Prestataire Recherche')
            ->assertSee('Gestionnaire Validateur')
            ->assertSee('04/11/2026')
            ->assertSee('05/11/2026')
            ->assertSee($validatedMember->name)
            ->assertDontSee('Document en attente confidentiel')
            ->assertDontSee('Document rejeté confidentiel');

        foreach ([
            'Dossier conformité',
            'REF-SEC-2026-001',
            'Entreprise Recherche',
            'Prestataire Recherche',
        ] as $searchTerm) {
            $this->get(route('responsable-securite.documents.index', ['search' => $searchTerm]))
                ->assertOk()
                ->assertSee($validatedDocument->title);
        }
    }

    public function test_responsable_securite_can_view_all_validated_document_details_and_download_the_attachment(): void
    {
        Storage::fake('documents_private');
        $securityLead = $this->userWithRole('responsable_securite');
        $manager = $this->userWithRole('gestionnaire', [
            'name' => 'Gestionnaire Validateur',
            'email' => 'gestionnaire@example.test',
        ]);
        $prestataire = $this->userWithRole('prestataire', [
            'name' => 'Prestataire Exemple',
            'email' => 'prestataire@example.test',
        ]);
        $path = 'prestataires/'.$prestataire->id.'/preuve-responsable-securite.pdf';
        Storage::disk('documents_private')->put($path, 'contenu privé');
        $document = $this->createDocument($prestataire, [
            'title' => 'Dossier accès chantier',
            'reference_number' => 'REF-DETAIL-001',
            'company_name' => 'Entreprise Exemple',
            'description' => 'Intervention de maintenance planifiée.',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
            'custom_fields' => ['Site' => 'Atelier Nord'],
            'attachment_path' => $path,
            'status' => Document::STATUS_VALIDATED,
            'validated_by' => $manager->id,
            'submitted_at' => '2026-11-04 09:15:00',
            'validated_at' => '2026-11-05 10:30:00',
        ]);
        $document->safetyTeamMembers()->create([
            'name' => 'Membre validé détail',
            'cin' => 'CIN-VALIDATED-DETAIL',
            'position' => 'Chef d’équipe',
            'email' => 'membre@example.test',
            'phone' => '+33102030405',
        ]);

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.documents.show', $document))
            ->assertOk()
            ->assertSee('REF-DETAIL-001')
            ->assertSee('Dossier accès chantier')
            ->assertSee('Entreprise Exemple')
            ->assertSee('Prestataire Exemple')
            ->assertSee('prestataire@example.test')
            ->assertSee('10/11/2026')
            ->assertSee('20/11/2026')
            ->assertSee('Intervention de maintenance planifiée.')
            ->assertSee('Atelier Nord')
            ->assertSee('04/11/2026 à 09:15')
            ->assertSee('05/11/2026 à 10:30')
            ->assertSee('Membre validé détail')
            ->assertSee('CIN-VALIDATED-DETAIL')
            ->assertSee('Chef d’équipe')
            ->assertSee('membre@example.test')
            ->assertSee('Gestionnaire Validateur')
            ->assertSee('gestionnaire@example.test')
            ->assertSee('preuve-responsable-securite.pdf')
            ->assertSee(route('responsable-securite.documents.download', $document));

        $this->get(route('responsable-securite.documents.download', $document))
            ->assertDownload('preuve-responsable-securite.pdf');
    }

    public function test_pending_and_rejected_documents_cannot_be_viewed_or_downloaded(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');

        foreach ([Document::STATUS_PENDING, Document::STATUS_REJECTED] as $status) {
            $document = $this->createDocument($prestataire, [
                'title' => 'Dossier '.$status,
                'status' => $status,
                'attachment_path' => 'prestataires/'.$prestataire->id.'/'.$status.'.pdf',
            ]);

            $this->actingAs($securityLead)
                ->get(route('responsable-securite.documents.show', $document))
                ->assertNotFound();

            $this->get(route('responsable-securite.documents.download', $document))
                ->assertNotFound();
        }
    }

    public function test_validated_documents_are_sorted_by_validation_date_and_paginated(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');

        foreach (range(1, 11) as $documentNumber) {
            $this->createDocument($prestataire, [
                'title' => sprintf('Document validé %02d', $documentNumber),
                'status' => Document::STATUS_VALIDATED,
                'validated_at' => now()->subMinutes($documentNumber),
            ]);
        }

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.documents.index'))
            ->assertOk()
            ->assertSeeInOrder(['Document validé 01', 'Document validé 02'])
            ->assertDontSee('Document validé 11')
            ->assertSee('page=2', false);

        $this->get(route('responsable-securite.documents.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSeeInOrder(['Document validé 11', 'Document validé 10'])
            ->assertDontSee('Document validé 01');
    }

    public function test_responsable_securite_dashboard_counts_and_shows_only_validated_documents(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');
        $validatedCount = Document::query()->where('status', Document::STATUS_VALIDATED)->count() + 2;
        $this->createDocument($prestataire, [
            'title' => 'Dossier validé récent',
            'status' => Document::STATUS_VALIDATED,
            'validated_at' => now(),
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Dossier validé ancien',
            'status' => Document::STATUS_VALIDATED,
            'validated_at' => now()->subDay(),
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Dossier en attente privé',
            'status' => Document::STATUS_PENDING,
        ]);
        $this->createDocument($prestataire, [
            'title' => 'Dossier rejeté privé',
            'status' => Document::STATUS_REJECTED,
        ]);

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', [
                'total' => $validatedCount,
                'pending' => 0,
                'validated' => $validatedCount,
                'rejected' => 0,
            ])
            ->assertSee('Dossier validé récent')
            ->assertSee(route('responsable-securite.documents.index'))
            ->assertDontSee('Dossier en attente privé')
            ->assertDontSee('Dossier rejeté privé');
    }

    public function test_responsable_securite_has_no_document_write_routes(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'title' => 'Document immuable',
            'status' => Document::STATUS_VALIDATED,
        ]);

        $this->actingAs($securityLead)
            ->post('/responsable-securite/documents')
            ->assertStatus(405);

        $this->get('/responsable-securite/documents/create')->assertNotFound();
        $this->get(route('responsable-securite.documents.show', $document).'/edit')->assertNotFound();
        $this->put(route('responsable-securite.documents.show', $document), [
            'title' => 'Modification interdite',
        ])->assertStatus(405);
        $this->delete(route('responsable-securite.documents.show', $document))->assertStatus(405);
        $this->post(route('responsable-securite.documents.show', $document).'/approve')->assertNotFound();
        $this->post(route('responsable-securite.documents.show', $document).'/reject')->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Document immuable',
            'status' => Document::STATUS_VALIDATED,
        ]);
    }

    public function test_responsable_securite_cannot_access_gestionnaire_or_prestataire_areas(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire);

        $this->actingAs($securityLead)
            ->get(route('gestionnaire.dashboard'))
            ->assertForbidden();

        $this->get(route('gestionnaire.documents.index'))->assertForbidden();
        $this->get('/gestionnaire/equipe-securite')->assertNotFound();
        $this->post(route('gestionnaire.documents.approve', $document))->assertForbidden();
        $this->get(route('prestataire.dashboard'))->assertForbidden();
        $this->get(route('prestataire.documents.create'))->assertForbidden();
        $this->post(route('prestataire.documents.store'))->assertForbidden();
    }

    public function test_guests_cannot_access_security_document_routes(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->createDocument($prestataire, [
            'status' => Document::STATUS_VALIDATED,
            'attachment_path' => 'preuve.pdf',
        ]);

        $this->get(route('responsable-securite.documents.index'))
            ->assertRedirect(route('login'));

        $this->get(route('responsable-securite.documents.show', $document))
            ->assertRedirect(route('login'));

        $this->get(route('responsable-securite.documents.download', $document))
            ->assertRedirect(route('login'));
    }

    public function test_empty_validated_document_list_has_a_french_empty_state(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.documents.index', [
                'search' => 'aucun-resultat-responsable-securite-unique-2026-11-05',
            ]))
            ->assertOk()
            ->assertSee('Aucun document validé')
            ->assertSee('Les documents approuvés apparaîtront ici.');
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
            ['description' => 'Role used by Responsable Securite document tests.'],
        );
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }
}
