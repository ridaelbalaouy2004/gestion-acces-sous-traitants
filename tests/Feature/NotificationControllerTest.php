<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use App\Notifications\DocumentWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);
    }

    public function test_supported_roles_can_access_the_shared_notifications_page(): void
    {
        foreach ([
            ['prestataire', 'Prestataire'],
            ['gestionnaire', 'Gestionnaire'],
            ['responsable_securite', 'Responsable sécurité'],
        ] as [$role, $label]) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('notifications.index'))
                ->assertOk()
                ->assertSee('Centre de notifications')
                ->assertViewHas('roleLabel', $label);
        }
    }

    public function test_prestataire_only_sees_notifications_for_their_permanent_employees(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $employee = $this->companyEmployee($prestataire, 'Employé propre');
        $foreignEmployee = $this->companyEmployee($otherPrestataire, 'Employé privé');

        $prestataire->notify(new CompanyEmployeeWorkflowNotification(
            $employee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED,
            reason: 'Informations à corriger.',
        ));
        $prestataire->notify(new CompanyEmployeeWorkflowNotification(
            $foreignEmployee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED,
            reason: 'Motif privé.',
        ));
        $this->createLegacyDocumentNotification($prestataire, Document::STATUS_VALIDATED);

        $response = $this->actingAs($prestataire)
            ->get(route('notifications.index'))
            ->assertOk();

        $response
            ->assertSee('Employé propre')
            ->assertSee('data-navigate-to="'.route('prestataire.employees.show', $employee).'"', false)
            ->assertSee('Informations à corriger.')
            ->assertDontSee('Employé privé')
            ->assertDontSee('Motif privé.')
            ->assertDontSee('Document validé');
        $this->assertSame(1, substr_count($response->getContent(), 'data-navigate-to='));
    }

    public function test_gestionnaire_notifications_require_permission_and_assigned_prestataire(): void
    {
        $manager = $this->userWithRole('gestionnaire');
        $this->grant($manager, 'employees.view');
        $assignedProvider = $this->userWithRole('prestataire');
        $unassignedProvider = $this->userWithRole('prestataire');
        $manager->assignedPrestataires()->attach($assignedProvider, ['assigned_by' => $manager->getKey()]);
        $assignedEmployee = $this->companyEmployee($assignedProvider, 'Employé affecté');
        $unassignedEmployee = $this->companyEmployee($unassignedProvider, 'Employé non affecté');

        $manager->notify(new CompanyEmployeeWorkflowNotification(
            $assignedEmployee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        ));
        $manager->notify(new CompanyEmployeeWorkflowNotification(
            $unassignedEmployee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        ));

        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Employé affecté')
            ->assertDontSee('Employé non affecté');
    }

    public function test_responsable_securite_only_sees_validated_document_notifications(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');
        $validatedDocument = $this->document($prestataire, Document::STATUS_VALIDATED, 'Document validé visible');
        $pendingDocument = $this->document($prestataire, Document::STATUS_PENDING, 'Document en attente privé');

        $this->createLegacyDocumentNotification($securityLead, Document::STATUS_VALIDATED, $validatedDocument);
        $this->createLegacyDocumentNotification($securityLead, Document::STATUS_VALIDATED, $pendingDocument);
        $this->createLegacyDocumentNotification($securityLead, 'document_rejected', $pendingDocument);

        $this->actingAs($securityLead)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Document validé visible')
            ->assertSee(route('responsable-securite.documents.show', $validatedDocument))
            ->assertDontSee('Document en attente privé');
    }

    public function test_user_can_mark_an_owned_retained_notification_as_read_but_not_a_foreign_one(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $employee = $this->companyEmployee($prestataire, 'Employé notification');
        $foreignEmployee = $this->companyEmployee($otherPrestataire, 'Employé étranger');
        $prestataire->notify(new CompanyEmployeeWorkflowNotification(
            $employee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_VALIDATED,
        ));
        $prestataire->notify(new CompanyEmployeeWorkflowNotification(
            $foreignEmployee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_VALIDATED,
        ));
        $owned = $prestataire->notifications()->where('data->employee_id', $employee->getKey())->firstOrFail();
        $foreign = $prestataire->notifications()->where('data->employee_id', $foreignEmployee->getKey())->firstOrFail();

        $this->actingAs($prestataire)
            ->patch(route('notifications.read', $foreign->id))
            ->assertNotFound();
        $this->patch(route('notifications.read', $owned->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNotNull($owned->fresh()->read_at);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function grant(User $manager, string $permission): void
    {
        $manager->permissions()->attach(Permission::query()->where('name', $permission)->firstOrFail());
    }

    private function companyEmployee(User $prestataire, string $name): CompanyEmployee
    {
        $company = Company::query()->create([
            'prestataire_id' => $prestataire->getKey(),
            'name' => 'Entreprise '.$name,
            'status' => Company::STATUS_DRAFT,
        ]);

        return CompanyEmployee::query()->create([
            'company_id' => $company->getKey(),
            'first_name' => $name,
            'last_name' => 'Test',
            'cin' => Str::upper(Str::random(10)),
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_PENDING,
        ]);
    }

    private function document(User $prestataire, string $status, string $title): Document
    {
        return Document::query()->create([
            'prestataire_id' => $prestataire->getKey(),
            'title' => $title,
            'company_name' => 'Entreprise test',
            'description' => 'Document historique',
            'status' => $status,
        ]);
    }

    private function createLegacyDocumentNotification(
        User $user,
        string $event,
        ?Document $document = null,
    ): DatabaseNotification {
        $document ??= $this->document($this->userWithRole('prestataire'), Document::STATUS_VALIDATED, 'Document validé');

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => DocumentWorkflowNotification::class,
            'data' => [
                'event' => $event === Document::STATUS_VALIDATED
                    ? DocumentWorkflowNotification::EVENT_VALIDATED
                    : $event,
                'document_id' => $document->getKey(),
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'status' => $document->status,
                'prestataire_name' => $document->prestataire?->name,
            ],
        ]);
    }
}
