<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Document;
use App\Models\ManagerSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagerPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_manager_schema_migration_can_resume_without_overwriting_existing_data(): void
    {
        $principal = User::factory()->create();
        Permission::create([
            'name' => 'existing.permission',
            'module' => 'existing',
            'label' => 'Existing permission',
        ]);
        DB::table('manager_settings')->where('id', 1)->update([
            'principal_user_id' => $principal->id,
        ]);
        Schema::drop('manager_principal_transfers');

        $migration = require database_path('migrations/2026_11_11_114010_create_manager_permissions_and_assignments_tables.php');
        $migration->up();

        $this->assertDatabaseHas('permissions', [
            'name' => 'existing.permission',
            'label' => 'Existing permission',
        ]);
        $this->assertDatabaseHas('manager_settings', [
            'id' => 1,
            'principal_user_id' => $principal->id,
        ]);
        $this->assertTrue(Schema::hasTable('manager_principal_transfers'));
    }

    public function test_primary_manager_has_every_permission_and_can_create_an_employee(): void
    {
        $principal = $this->primaryManager();
        $prestataire = $this->userWithRole('prestataire');
        $temporaryPassword = 'Manager-Temporary-Password-42!';

        foreach (array_keys(Permission::CATALOG) as $permission) {
            $this->assertTrue($principal->hasPermission($permission));
        }

        $this->actingAs($principal)
            ->post(route('gestionnaire.employes.store'), [
                'name' => 'Employée Gestionnaire',
                'email' => 'employe@example.test',
                'password' => $temporaryPassword,
                'password_confirmation' => $temporaryPassword,
                'permission_ids' => [$this->permission('prestataires.view')->id],
                'prestataire_ids' => [$prestataire->id],
            ])
            ->assertRedirect();

        $employee = User::query()->where('email', 'employe@example.test')->firstOrFail();
        $this->assertSame('gestionnaire', $employee->roles()->sole()->name);
        $this->assertTrue(Hash::check($temporaryPassword, $employee->password));
        $this->assertTrue($employee->must_change_password);
        $this->assertFalse($employee->isPrimaryManager());
        $this->assertTrue($employee->hasPermission('prestataires.view'));
        $this->assertFalse($employee->hasPermission('documents.download'));
        $this->assertTrue($employee->assignedPrestataires()->whereKey($prestataire->id)->exists());

        $this->actingAs($principal)
            ->get(route('gestionnaire.employes.show', [
                'manager' => $employee->id,
                'assignment_search' => $prestataire->email,
            ]))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee($prestataire->name)
            ->assertSee('data-navigate-to="'.route('gestionnaire.prestataires.show', $prestataire).'"', false)
            ->assertSee('Historique récent des affectations');

        $this->get(route('gestionnaire.employes.index'))
            ->assertOk()
            ->assertSee('data-navigate-to="'.route('gestionnaire.employes.show', $employee).'"', false);
    }

    public function test_assignment_history_is_not_truncated_in_its_scrollable_section(): void
    {
        $principal = $this->primaryManager();
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');

        foreach (range(1, 25) as $index) {
            DB::table('manager_prestataire_assignment_history')->insert([
                'manager_id' => $manager->getKey(),
                'prestataire_id' => $prestataire->getKey(),
                'actor_id' => $principal->getKey(),
                'action' => 'assigned',
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->actingAs($principal)
            ->get(route('gestionnaire.employes.show', $manager))
            ->assertOk()
            ->assertSee('max-h-56 space-y-2 overflow-y-auto overscroll-contain', false)
            ->assertSee('Historique récent des affectations')
            ->assertSee($prestataire->name);

        $this->assertSame(25, substr_count($response->getContent(), 'Affecté :'));
    }

    public function test_employee_only_sees_assigned_prestataires(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $this->grant($employee, 'prestataires.view');
        $assigned = $this->userWithRole('prestataire', ['name' => 'Prestataire affecté']);
        $unassigned = $this->userWithRole('prestataire', ['name' => 'Prestataire privé']);
        $this->assign($employee, $assigned, $principal);
        $this->actingAs($employee)
            ->get(route('gestionnaire.prestataires.index'))
            ->assertOk()
            ->assertSee($assigned->name)
            ->assertSee('data-navigate-to="'.route('gestionnaire.prestataires.show', $assigned).'"', false)
            ->assertDontSee($unassigned->name);

        $this->get(route('gestionnaire.prestataires.show', $unassigned))->assertNotFound();
    }

    public function test_assignment_removal_preserves_prestataire_and_document_history(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->document($prestataire, 'Historique conservé');
        $this->assign($employee, $prestataire, $principal);

        $this->actingAs($principal)
            ->put(route('gestionnaire.employes.assignments.update', $employee), ['prestataire_ids' => []])
            ->assertRedirect();

        $this->assertDatabaseMissing('manager_prestataire_assignments', [
            'manager_id' => $employee->id,
            'prestataire_id' => $prestataire->id,
        ]);
        $this->assertDatabaseHas('manager_prestataire_assignment_history', [
            'manager_id' => $employee->id,
            'prestataire_id' => $prestataire->id,
            'action' => 'removed',
        ]);
        $this->assertDatabaseHas('users', ['id' => $prestataire->id]);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_employee_cannot_manage_other_employees_or_assign_primary_status(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $otherEmployee = $this->userWithRole('gestionnaire');

        $this->actingAs($employee)
            ->get(route('gestionnaire.employes.index'))
            ->assertForbidden();
        $this->get(route('gestionnaire.employes.show', $otherEmployee))->assertForbidden();
        $this->get(route('gestionnaire.employes.show', 999999))->assertForbidden();
        $this->put(route('gestionnaire.employes.permissions.update', $otherEmployee), [])
            ->assertForbidden();
        $this->post(route('gestionnaire.employes.principal.transfer', $otherEmployee))
            ->assertForbidden();

        $this->actingAs($principal)
            ->patch(route('gestionnaire.employes.status', $principal))
            ->assertNotFound();
        $this->assertTrue($principal->fresh()->is_active);
        $this->assertTrue($principal->fresh()->isPrimaryManager());
    }

    public function test_primary_responsibility_can_only_be_transferred_to_another_active_manager(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');

        $this->actingAs($principal)
            ->post(route('gestionnaire.employes.principal.transfer', $employee))
            ->assertRedirect(route('gestionnaire.dashboard'));

        $this->assertFalse($principal->fresh()->isPrimaryManager());
        $this->assertTrue($employee->fresh()->isPrimaryManager());
        $this->assertDatabaseHas('manager_principal_transfers', [
            'previous_user_id' => $principal->id,
            'new_user_id' => $employee->id,
            'actor_id' => $principal->id,
        ]);
    }

    public function test_primary_manager_can_update_an_employee_without_changing_role_or_status(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire', ['is_active' => false]);

        $this->actingAs($principal)
            ->put(route('gestionnaire.employes.update', $employee), [
                'name' => 'Nouveau nom Gestionnaire',
                'email' => 'nouveau.nom@example.test',
            ])
            ->assertRedirect(route('gestionnaire.employes.show', $employee));

        $employee->refresh();
        $this->assertSame('Nouveau nom Gestionnaire', $employee->name);
        $this->assertSame('nouveau.nom@example.test', $employee->email);
        $this->assertFalse($employee->is_active);
        $this->assertSame('gestionnaire', $employee->roles()->sole()->name);
    }

    public function test_primary_manager_can_update_selected_permission_modules_without_changing_other_modules(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $this->grant($employee, 'prestataires.view');
        $this->grant($employee, 'machinery.view');
        $this->grant($employee, 'entreprises.view');
        $this->grant($employee, 'employees.edit');

        $this->actingAs($principal)
            ->get(route('gestionnaire.employes.edit', $employee))
            ->assertOk()
            ->assertSee('Permissions Entreprises et Mon équipe')
            ->assertSee('Consulter les entreprises')
            ->assertSee('Consulter les employés');

        $this->put(route('gestionnaire.employes.permissions.update', $employee), [
            'permission_modules' => ['entreprises', 'employees'],
            'permission_ids' => [
                $this->permission('entreprises.validate')->id,
                $this->permission('employees.view')->id,
            ],
        ])
            ->assertRedirect(route('gestionnaire.employes.show', $employee));

        $employee->refresh();
        $this->assertTrue($employee->hasPermission('entreprises.validate'));
        $this->assertFalse($employee->hasPermission('entreprises.view'));
        $this->assertTrue($employee->hasPermission('employees.view'));
        $this->assertFalse($employee->hasPermission('employees.edit'));
        $this->assertTrue($employee->hasPermission('prestataires.view'));
        $this->assertTrue($employee->hasPermission('machinery.view'));
    }

    public function test_primary_manager_can_assign_multiple_prestataires_without_creating_duplicates(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $firstPrestataire = $this->userWithRole('prestataire');
        $secondPrestataire = $this->userWithRole('prestataire');

        $this->actingAs($principal)
            ->put(route('gestionnaire.employes.assignments.update', $employee), [
                'prestataire_ids' => [$firstPrestataire->id, $secondPrestataire->id],
            ])
            ->assertRedirect();
        $this->assertSame(2, $employee->assignedPrestataires()->count());
        $this->assertDatabaseCount('manager_prestataire_assignment_history', 2);

        $this->put(route('gestionnaire.employes.assignments.update', $employee), [
            'prestataire_ids' => [$firstPrestataire->id, $firstPrestataire->id],
        ])->assertSessionHasErrors('prestataire_ids.1');
        $this->assertSame(2, $employee->assignedPrestataires()->count());
    }

    public function test_disabling_an_employee_blocks_sign_in_but_preserves_their_history_and_can_be_reversed(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = $this->document($prestataire, 'Dossier historique');
        $this->assign($employee, $prestataire, $principal);

        $this->actingAs($principal)
            ->patch(route('gestionnaire.employes.status', $employee))
            ->assertRedirect(route('gestionnaire.employes.show', $employee));
        $this->assertFalse($employee->fresh()->is_active);
        $this->assertTrue($employee->fresh()->assignedPrestataires()->whereKey($prestataire->id)->exists());
        $this->assertDatabaseHas('documents', ['id' => $document->id]);

        $this->actingAs($employee->fresh())->get(route('gestionnaire.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->actingAs($principal)
            ->patch(route('gestionnaire.employes.status', $employee))
            ->assertRedirect(route('gestionnaire.employes.show', $employee));
        $this->assertTrue($employee->fresh()->is_active);
        $this->assertTrue($employee->fresh()->assignedPrestataires()->whereKey($prestataire->id)->exists());
    }

    public function test_employee_notifications_are_limited_to_their_permissions_and_assignments(): void
    {
        $principal = $this->primaryManager();
        $employee = $this->userWithRole('gestionnaire');
        $this->grant($employee, 'employees.view');
        $assigned = $this->userWithRole('prestataire');
        $unassigned = $this->userWithRole('prestataire');
        $this->assign($employee, $assigned, $principal);
        $assignedEmployee = $this->companyEmployee($assigned, 'Employé accessible');
        $unassignedEmployee = $this->companyEmployee($unassigned, 'Employé confidentiel');
        $employee->notify(new CompanyEmployeeWorkflowNotification(
            $assignedEmployee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        ));
        $employee->notify(new CompanyEmployeeWorkflowNotification(
            $unassignedEmployee,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        ));

        $this->actingAs($employee)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Employé accessible')
            ->assertDontSee('Employé confidentiel');
    }

    public function test_manager_must_change_the_temporary_password_before_accessing_protected_pages(): void
    {
        $principal = $this->primaryManager();
        $temporaryPassword = 'Temporary-Password-Manager-42!';
        $employee = $this->userWithRole('gestionnaire', [
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);
        $this->actingAs($principal)->get(route('gestionnaire.employes.index'));

        $this->actingAs($employee)
            ->get(route('gestionnaire.employees.index'))
            ->assertRedirect(route('gestionnaire.password.edit'));
        $this->get(route('gestionnaire.password.edit'))->assertOk();
        $this->put(route('gestionnaire.password.update'), [
            'password' => $temporaryPassword,
            'password_confirmation' => $temporaryPassword,
        ])->assertSessionHasErrors('password');

        $newPassword = 'New-Manager-Password-62!';
        $this->put(route('gestionnaire.password.update'), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('gestionnaire.dashboard'));

        $this->assertFalse($employee->fresh()->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $employee->fresh()->password));
        $this->get(route('gestionnaire.dashboard'))->assertOk();
    }

    private function primaryManager(): User
    {
        $manager = $this->userWithRole('gestionnaire');
        ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->update([
            'principal_user_id' => $manager->id,
        ]);

        return $manager;
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::query()->where('name', $name)->firstOrFail();
    }

    private function grant(User $manager, string $permission): void
    {
        $manager->permissions()->attach($this->permission($permission));
    }

    private function assign(User $manager, User $prestataire, User $actor): void
    {
        $manager->assignedPrestataires()->attach($prestataire, ['assigned_by' => $actor->id]);
    }

    private function document(User $prestataire, string $title, array $attributes = []): Document
    {
        return Document::query()->create(array_replace([
            'prestataire_id' => $prestataire->getKey(),
            'title' => $title,
            'company_name' => 'Entreprise de test',
            'description' => 'Document de test',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => Document::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));
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
            'cin' => 'CIN-'.uniqid(),
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_PENDING,
        ]);
    }
}
