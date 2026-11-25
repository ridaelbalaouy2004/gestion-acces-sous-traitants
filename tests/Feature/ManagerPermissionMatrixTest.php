<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\ManagerSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagerPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('documents_private');
    }

    public function test_primary_manager_has_full_access_to_every_manager_permission_and_menu(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire principal']);
        $company = $this->company($prestataire);
        $employee = $this->employee($company);
        $machinery = $this->machinery($company);
        $tooling = $this->tooling($company);
        $companyDocument = $this->companyDocument($company);
        $employeeDocument = $this->employeeDocument($employee);
        $machineryDocument = $this->machineryDocument($machinery);
        $toolingDocument = $this->toolingDocument($tooling);

        foreach (array_keys(Permission::CATALOG) as $permission) {
            $this->assertTrue($principal->hasPermission($permission));
        }

        $this->actingAs($principal)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee('Gestion des entreprises')
            ->assertSee('Gestion des équipes')
            ->assertSee('Gestion des engins')
            ->assertSee('Gestion des outillages')
            ->assertSee('Gestion des Prestataires')
            ->assertSee('Employés Gestionnaire');

        $this->actingAs($principal)->get(route('gestionnaire.prestataires.index'))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.prestataires.show', $prestataire))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.prestataires.create'))->assertOk();
        $this->actingAs($principal)->put(route('gestionnaire.prestataires.update', $prestataire), [
            'name' => 'Prestataire modifié',
            'email' => 'prestataire-modifie@example.test',
        ])->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));
        $this->actingAs($principal)->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))->assertRedirect();

        $this->actingAs($principal)->get(route('gestionnaire.companies.index'))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.companies.show', $company))->assertOk();
        $this->actingAs($principal)->put(route('gestionnaire.companies.update', $company), [
            'name' => 'Entreprise modifiée',
            'activity' => 'Activité',
            'assignment' => 'Mission',
            'additional_info' => 'Contenu',
        ])->assertRedirect(route('gestionnaire.companies.show', $company));
        $company->documents()->update(['status' => CompanyDocument::STATUS_VALIDATED]);
        $this->actingAs($principal)->post(route('gestionnaire.companies.approve', $company))->assertRedirect();
        $rejectedCompany = $this->company($prestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Entreprise rejet test']);
        $rejectedCompanyDocument = $this->companyDocument($rejectedCompany);
        $this->actingAs($principal)->post(route('gestionnaire.companies.reject', $rejectedCompany), ['reason' => 'Motif valide pour rejet de l’entreprise.'])->assertRedirect();
        $this->actingAs($principal)->get(route('gestionnaire.companies.documents.preview', [$rejectedCompany, $rejectedCompanyDocument]))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.companies.documents.download', [$rejectedCompany, $rejectedCompanyDocument]))->assertOk();

        $this->actingAs($principal)->get(route('gestionnaire.employees.index'))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.employees.show', $employee))->assertOk();
        $this->actingAs($principal)->put(route('gestionnaire.employees.update', $employee), [
            'first_name' => 'Nouveau',
            'last_name' => 'Employé',
            'cin' => 'AA-001',
            'position' => 'Chef de chantier',
        ])->assertRedirect();
        $company->update(['status' => Company::STATUS_VALIDATED]);
        $employee->documents()->update(['status' => CompanyEmployeeDocument::STATUS_VALIDATED]);
        $this->actingAs($principal)->post(route('gestionnaire.employees.approve', $employee))->assertRedirect();
        $this->resetStatusToPending($employee);
        $this->assertSame(CompanyEmployee::STATUS_PENDING, $employee->fresh()->status);
        $this->actingAs($principal)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif valide pour rejet de l’employé.'])->assertRedirect();
        $this->actingAs($principal)->get(route('gestionnaire.employees.documents.preview', [$employee, $employeeDocument]))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.employees.documents.download', [$employee, $employeeDocument]))->assertOk();

        $this->actingAs($principal)->get(route('gestionnaire.machinery.index'))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.machinery.show', $machinery))->assertOk();
        $this->actingAs($principal)->put(route('gestionnaire.machinery.update', $machinery), [
            'designation' => 'Engin modifié',
            'category' => 'Camion',
            'assignment' => 'Mission',
            'brand' => 'Renault',
            'model' => 'Z',
            'serial_number' => 'SER-123',
            'registration_number' => 'REG-123',
        ])->assertRedirect();
        $company->update(['status' => Company::STATUS_VALIDATED]);
        $machinery->documents()->update(['status' => CompanyMachineryDocument::STATUS_VALIDATED]);
        $this->actingAs($principal)->post(route('gestionnaire.machinery.approve', $machinery))->assertRedirect();
        $this->resetStatusToPending($machinery);
        $this->assertSame(CompanyMachinery::STATUS_PENDING, $machinery->fresh()->status);
        $this->actingAs($principal)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif valide pour rejet de l’engin.'])->assertRedirect();
        $this->actingAs($principal)->get(route('gestionnaire.machinery.documents.preview', [$machinery, $machineryDocument]))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.machinery.documents.download', [$machinery, $machineryDocument]))->assertOk();

        $this->actingAs($principal)->get(route('gestionnaire.toolings.index'))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.toolings.show', $tooling))->assertOk();
        $this->actingAs($principal)->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Outillage modifié',
            'category' => 'Outil de précision',
            'internal_reference' => 'REF-123',
            'brand' => 'Bosch',
            'model' => 'Pro',
            'serial_number' => 'SER-456',
            'quantity' => 2,
            'physical_condition' => 'good',
            'location' => 'Entrepôt',
        ])->assertRedirect();
        $company->update(['status' => Company::STATUS_VALIDATED]);
        $tooling->documents()->update(['status' => CompanyToolingDocument::STATUS_VALIDATED]);
        $this->actingAs($principal)->post(route('gestionnaire.toolings.approve', $tooling))->assertRedirect();
        $this->resetStatusToPending($tooling);
        $this->assertSame(CompanyTooling::STATUS_PENDING, $tooling->fresh()->status);
        $this->actingAs($principal)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif valide pour rejet de l’outillage.'])->assertRedirect();
        $this->actingAs($principal)->get(route('gestionnaire.toolings.documents.preview', [$tooling, $toolingDocument]))->assertOk();
        $this->actingAs($principal)->get(route('gestionnaire.toolings.documents.download', [$tooling, $toolingDocument]))->assertOk();
    }

    public function test_primary_manager_sidebar_displays_requested_order_and_live_notification_count(): void
    {
        $manager = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $prestataire = $this->userWithRole('prestataire');
        $employee = $this->employee($this->company($prestataire));

        for ($index = 0; $index < 7; $index++) {
            $manager->notify(new CompanyEmployeeWorkflowNotification(
                $employee,
                CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
            ));
        }

        $manager->notifications()->firstOrFail()->update(['read_at' => now()]);

        $response = $this->actingAs($manager)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="6 notifications non lues"', false);

        $html = $response->getContent();
        $matches = [];
        $this->assertSame(
            1,
            preg_match('/<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5".*?<\/nav>/s', $html, $matches),
        );

        $expectedRoutes = [
            'gestionnaire.dashboard',
            'notifications.index',
            'gestionnaire.companies.index',
            'gestionnaire.employees.index',
            'gestionnaire.machinery.index',
            'gestionnaire.toolings.index',
            'gestionnaire.prestataires.index',
            'gestionnaire.employes.index',
            'gestionnaire.delegated.index',
            'gestionnaire.guides.required-documents.index',
            'gestionnaire.profile',
        ];
        $routePositions = [];

        foreach ($expectedRoutes as $routeName) {
            $routePositions[] = strpos($matches[0], 'href="'.route($routeName).'"');
        }

        $this->assertNotContains(false, $routePositions);
        $sortedRoutePositions = $routePositions;
        sort($sortedRoutePositions);
        $this->assertSame($sortedRoutePositions, $routePositions);
        $this->assertSame(11, preg_match_all('/<a\s+href="[^"]+"/', $matches[0]));
    }

    public function test_prestataire_module_permission_matrix(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire A', 'email' => 'prestataire.a@example.test']);
        $this->assign($manager, $prestataire, $principal);

        $this->grantPermissions($manager, []);
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.show', $prestataire))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.create'))->assertForbidden();
        $this->actingAs($manager)->put(route('gestionnaire.prestataires.update', $prestataire), [
            'name' => 'Nouveau nom',
            'email' => 'new.a@example.test',
        ])->assertForbidden();
        $this->actingAs($manager)->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))->assertForbidden();

        $this->grantPermissions($manager, ['prestataires.view']);
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.show', $prestataire))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.create'))->assertForbidden();
        $this->actingAs($manager)->put(route('gestionnaire.prestataires.update', $prestataire), [
            'name' => 'Nouveau nom',
            'email' => 'new.a@example.test',
        ])->assertForbidden();
        $this->actingAs($manager)->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))->assertForbidden();

        $this->grantPermissions($manager, ['prestataires.view', 'prestataires.update']);
        $this->actingAs($manager)->put(route('gestionnaire.prestataires.update', $prestataire), [
            'name' => 'Prestataire mis à jour',
            'email' => 'updated.a@example.test',
        ])->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.create'))->assertForbidden();
        $this->actingAs($manager)->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))->assertForbidden();

        $this->grantPermissions($manager, ['prestataires.view', 'prestataires.toggle_status']);
        $this->actingAs($manager)->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))->assertRedirect();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.create'))->assertForbidden();

        $this->grantPermissions($manager, ['prestataires.create']);
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.create'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.show', $prestataire))->assertForbidden();

        $this->grantPermissions($manager, ['prestataires.view', 'prestataires.create', 'prestataires.update', 'prestataires.toggle_status']);
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.prestataires.create'))->assertOk();
        $this->actingAs($manager)->put(route('gestionnaire.prestataires.update', $prestataire), [
            'name' => 'Prestataire final',
            'email' => 'final.a@example.test',
        ])->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));
        $this->actingAs($manager)->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))->assertRedirect();
    }

    public function test_company_permission_matrix(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire entreprises', 'email' => 'prestataire.entreprises@example.test']);
        $this->assign($manager, $prestataire, $principal);
        $company = $this->company($prestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Société A']);
        $document = $this->companyDocument($company);

        $this->grantPermissions($manager, []);
        $this->actingAs($manager)->get(route('gestionnaire.companies.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.companies.show', $company))->assertForbidden();
        $this->actingAs($manager)->put(route('gestionnaire.companies.update', $company), [
            'name' => 'Société modifiée',
            'activity' => 'Bâtiment',
            'assignment' => 'Chantier',
            'additional_info' => 'Information',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.preview', [$company, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.download', [$company, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['entreprises.view']);
        $this->actingAs($manager)->get(route('gestionnaire.companies.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.companies.show', $company))->assertOk();
        $this->actingAs($manager)->put(route('gestionnaire.companies.update', $company), [
            'name' => 'Société modifiée',
            'activity' => 'Bâtiment',
            'assignment' => 'Chantier',
            'additional_info' => 'Information',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.preview', [$company, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.download', [$company, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['entreprises.view', 'entreprises.edit']);
        $this->actingAs($manager)->put(route('gestionnaire.companies.update', $company), [
            'name' => 'Société mise à jour',
            'activity' => 'Bâtiment',
            'assignment' => 'Chantier',
            'additional_info' => 'Information',
        ])->assertRedirect(route('gestionnaire.companies.show', $company));
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->grantPermissions($manager, ['entreprises.view', 'entreprises.validate']);
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->resetStatusToPending($company);
        $this->grantPermissions($manager, ['entreprises.view', 'entreprises.reject']);
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertForbidden();

        $this->grantPermissions($manager, ['entreprises.view', 'entreprises.download']);
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.preview', [$company, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.download', [$company, $document]))->assertOk();
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->grantPermissions($manager, ['entreprises.view', 'entreprises.edit', 'entreprises.validate', 'entreprises.reject', 'entreprises.download']);
        $this->resetStatusToPending($company);
        $this->actingAs($manager)->put(route('gestionnaire.companies.update', $company), [
            'name' => 'Société finale',
            'activity' => 'Bâtiment',
            'assignment' => 'Chantier',
            'additional_info' => 'Information',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.companies.approve', $company))->assertRedirect();
        $this->resetStatusToPending($company);
        $this->actingAs($manager)->post(route('gestionnaire.companies.reject', $company), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.preview', [$company, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.companies.documents.download', [$company, $document]))->assertOk();

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $this->company($this->userWithRole('prestataire', ['name' => 'Autre prestataire', 'email' => 'autre@mail.test']))))
            ->assertNotFound();
    }

    public function test_employee_permission_matrix(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire employés', 'email' => 'prestataire.employes@example.test']);
        $this->assign($manager, $prestataire, $principal);
        $company = $this->company($prestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Société employé']);
        $employee = $this->employee($company, ['status' => CompanyEmployee::STATUS_PENDING]);
        $document = $this->employeeDocument($employee);

        $this->grantPermissions($manager, []);
        $this->actingAs($manager)->get(route('gestionnaire.employees.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.employees.show', $employee))->assertForbidden();
        $this->actingAs($manager)->put(route('gestionnaire.employees.update', $employee), [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'cin' => 'CIN-1',
            'position' => 'Position',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.preview', [$employee, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.download', [$employee, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['employees.view']);
        $this->actingAs($manager)->get(route('gestionnaire.employees.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.employees.show', $employee))->assertOk();
        $this->actingAs($manager)->put(route('gestionnaire.employees.update', $employee), [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'cin' => 'CIN-1',
            'position' => 'Position',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.preview', [$employee, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.download', [$employee, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['employees.view', 'employees.edit']);
        $this->actingAs($manager)->put(route('gestionnaire.employees.update', $employee), [
            'first_name' => 'Prénom modifié',
            'last_name' => 'Nom modifié',
            'cin' => 'CIN-2',
            'position' => 'Chef de chantier',
        ])->assertRedirect(route('gestionnaire.employees.show', $employee));
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $company->update(['status' => Company::STATUS_VALIDATED]);
        $this->grantPermissions($manager, ['employees.view', 'employees.validate']);
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->resetStatusToPending($employee);
        $this->grantPermissions($manager, ['employees.view', 'employees.reject']);
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertForbidden();

        $this->grantPermissions($manager, ['employees.view', 'employees.download']);
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.preview', [$employee, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.download', [$employee, $document]))->assertOk();
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->grantPermissions($manager, ['employees.view', 'employees.edit', 'employees.validate', 'employees.reject', 'employees.download']);
        $company->update(['status' => Company::STATUS_VALIDATED]);
        $this->resetStatusToPending($employee);
        $this->actingAs($manager)->put(route('gestionnaire.employees.update', $employee), [
            'first_name' => 'Prénom final',
            'last_name' => 'Nom final',
            'cin' => 'CIN-3',
            'position' => 'Coordonnateur',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.employees.approve', $employee))->assertRedirect();
        $this->resetStatusToPending($employee);
        $this->actingAs($manager)->post(route('gestionnaire.employees.reject', $employee), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.preview', [$employee, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.employees.documents.download', [$employee, $document]))->assertOk();

        $otherPrestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire privé', 'email' => 'prestataire.prive@example.test']);
        $otherCompany = $this->company($otherPrestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Autre société']);
        $otherEmployee = $this->employee($otherCompany, ['status' => CompanyEmployee::STATUS_PENDING]);
        $this->actingAs($manager)->get(route('gestionnaire.employees.show', $otherEmployee))->assertNotFound();
    }

    public function test_machinery_permission_matrix(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire engins', 'email' => 'prestataire.engins@example.test']);
        $this->assign($manager, $prestataire, $principal);
        $company = $this->company($prestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Société engin']);
        $machinery = $this->machinery($company, ['status' => CompanyMachinery::STATUS_PENDING]);
        $document = $this->machineryDocument($machinery);

        $this->grantPermissions($manager, []);
        $this->actingAs($manager)->get(route('gestionnaire.machinery.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.show', $machinery))->assertForbidden();
        $this->actingAs($manager)->put(route('gestionnaire.machinery.update', $machinery), [
            'designation' => 'Engin modifié',
            'category' => 'Camion',
            'assignment' => 'Mission',
            'brand' => 'Renault',
            'model' => 'Z',
            'serial_number' => 'SER-123',
            'registration_number' => 'REG-123',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.preview', [$machinery, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.download', [$machinery, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['machinery.view']);
        $this->actingAs($manager)->get(route('gestionnaire.machinery.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.show', $machinery))->assertOk();
        $this->actingAs($manager)->put(route('gestionnaire.machinery.update', $machinery), [
            'designation' => 'Engin modifié',
            'category' => 'Camion',
            'assignment' => 'Mission',
            'brand' => 'Renault',
            'model' => 'Z',
            'serial_number' => 'SER-123',
            'registration_number' => 'REG-123',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.preview', [$machinery, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.download', [$machinery, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['machinery.view', 'machinery.update']);
        $this->actingAs($manager)->put(route('gestionnaire.machinery.update', $machinery), [
            'designation' => 'Engin modifié',
            'category' => 'Camion',
            'assignment' => 'Mission',
            'brand' => 'Renault',
            'model' => 'Z',
            'serial_number' => 'SER-123',
            'registration_number' => 'REG-123',
        ])->assertRedirect(route('gestionnaire.machinery.show', $machinery));
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $company->update(['status' => Company::STATUS_VALIDATED]);
        $this->grantPermissions($manager, ['machinery.view', 'machinery.approve']);
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->resetStatusToPending($machinery);
        $this->grantPermissions($manager, ['machinery.view', 'machinery.reject']);
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertForbidden();

        $this->grantPermissions($manager, ['machinery.view', 'machinery.download']);
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.preview', [$machinery, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.download', [$machinery, $document]))->assertOk();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->grantPermissions($manager, ['machinery.view', 'machinery.update', 'machinery.approve', 'machinery.reject', 'machinery.download']);
        $company->update(['status' => Company::STATUS_VALIDATED]);
        $this->resetStatusToPending($machinery);
        $this->actingAs($manager)->put(route('gestionnaire.machinery.update', $machinery), [
            'designation' => 'Engin final',
            'category' => 'Camion',
            'assignment' => 'Mission',
            'brand' => 'Renault',
            'model' => 'Z',
            'serial_number' => 'SER-456',
            'registration_number' => 'REG-456',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.machinery.approve', $machinery))->assertRedirect();
        $this->resetStatusToPending($machinery);
        $this->actingAs($manager)->post(route('gestionnaire.machinery.reject', $machinery), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.preview', [$machinery, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.documents.download', [$machinery, $document]))->assertOk();
    }

    public function test_tooling_permission_matrix(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $manager = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire outillages', 'email' => 'prestataire.outillages@example.test']);
        $this->assign($manager, $prestataire, $principal);
        $company = $this->company($prestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Société outillage']);
        $tooling = $this->tooling($company, ['status' => CompanyTooling::STATUS_PENDING]);
        $document = $this->toolingDocument($tooling);

        $this->grantPermissions($manager, []);
        $this->actingAs($manager)->get(route('gestionnaire.toolings.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.show', $tooling))->assertForbidden();
        $this->actingAs($manager)->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Outillage modifié',
            'category' => 'Outil de précision',
            'internal_reference' => 'REF-123',
            'brand' => 'Bosch',
            'model' => 'Pro',
            'serial_number' => 'SER-456',
            'quantity' => 2,
            'physical_condition' => 'good',
            'location' => 'Entrepôt',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.preview', [$tooling, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.download', [$tooling, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['toolings.view']);
        $this->actingAs($manager)->get(route('gestionnaire.toolings.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.show', $tooling))->assertOk();
        $this->actingAs($manager)->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Outillage modifié',
            'category' => 'Outil de précision',
            'internal_reference' => 'REF-123',
            'brand' => 'Bosch',
            'model' => 'Pro',
            'serial_number' => 'SER-456',
            'quantity' => 2,
            'physical_condition' => 'good',
            'location' => 'Entrepôt',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.preview', [$tooling, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.download', [$tooling, $document]))->assertForbidden();

        $this->grantPermissions($manager, ['toolings.view', 'toolings.update']);
        $this->actingAs($manager)->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Outillage modifié',
            'category' => 'Outil de précision',
            'internal_reference' => 'REF-123',
            'brand' => 'Bosch',
            'model' => 'Pro',
            'serial_number' => 'SER-456',
            'quantity' => 2,
            'physical_condition' => 'good',
            'location' => 'Entrepôt',
        ])->assertRedirect(route('gestionnaire.toolings.show', $tooling));
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $company->update(['status' => Company::STATUS_VALIDATED]);
        $this->grantPermissions($manager, ['toolings.view', 'toolings.approve']);
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->resetStatusToPending($tooling);
        $this->grantPermissions($manager, ['toolings.view', 'toolings.reject']);
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertForbidden();

        $this->grantPermissions($manager, ['toolings.view', 'toolings.download']);
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.preview', [$tooling, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.download', [$tooling, $document]))->assertOk();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertForbidden();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertForbidden();

        $this->grantPermissions($manager, ['toolings.view', 'toolings.update', 'toolings.approve', 'toolings.reject', 'toolings.download']);
        $company->update(['status' => Company::STATUS_VALIDATED]);
        $this->resetStatusToPending($tooling);
        $this->actingAs($manager)->put(route('gestionnaire.toolings.update', $tooling), [
            'designation' => 'Outillage final',
            'category' => 'Outil de précision',
            'internal_reference' => 'REF-456',
            'brand' => 'Bosch',
            'model' => 'Pro X',
            'serial_number' => 'SER-789',
            'quantity' => 3,
            'physical_condition' => 'good',
            'location' => 'Atelier',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('gestionnaire.toolings.approve', $tooling))->assertRedirect();
        $this->resetStatusToPending($tooling);
        $this->actingAs($manager)->post(route('gestionnaire.toolings.reject', $tooling), ['reason' => 'Motif de rejet suffisamment long.'])->assertRedirect();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.preview', [$tooling, $document]))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.documents.download', [$tooling, $document]))->assertOk();
    }

    public function test_manager_cannot_access_unassigned_prestataires_or_scope_beyond_assignment(): void
    {
        $principal = $this->designatePrimaryManager($this->userWithRole('gestionnaire'));
        $manager = $this->userWithRole('gestionnaire');
        $assignedPrestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire assigné', 'email' => 'assigned@example.test']);
        $otherPrestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire non assigné', 'email' => 'other@example.test']);
        $this->assign($manager, $assignedPrestataire, $principal);
        $this->grantPermissions($manager, ['prestataires.view', 'entreprises.view', 'employees.view', 'machinery.view', 'toolings.view']);

        $this->actingAs($manager)->get(route('gestionnaire.prestataires.show', $otherPrestataire))->assertNotFound();

        $assignedCompany = $this->company($assignedPrestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Société assignée']);
        $otherCompany = $this->company($otherPrestataire, ['status' => Company::STATUS_PENDING, 'name' => 'Société non assignée']);

        $this->actingAs($manager)->get(route('gestionnaire.companies.show', $otherCompany))->assertNotFound();
        $this->actingAs($manager)->get(route('gestionnaire.employees.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.machinery.index'))->assertOk();
        $this->actingAs($manager)->get(route('gestionnaire.toolings.index'))->assertOk();

        $this->assertFalse($manager->accessiblePrestataires()->whereKey($otherPrestataire->getKey())->exists());
        $this->assertTrue($manager->accessiblePrestataires()->whereKey($assignedPrestataire->getKey())->exists());
        $this->assertNotSame($assignedCompany->prestataire_id, $otherCompany->prestataire_id);
    }

    private function designatePrimaryManager(User $manager): User
    {
        ManagerSettings::query()->updateOrCreate(
            ['id' => ManagerSettings::SINGLETON_ID],
            ['principal_user_id' => $manager->getKey()],
        );

        return $manager;
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'name' => $attributes['name'] ?? 'User '.$roleName,
            'email' => $attributes['email'] ?? strtolower($roleName).'_'.uniqid().'@example.test',
            'password' => bcrypt('Password123!'),
            'is_active' => $attributes['is_active'] ?? true,
            'must_change_password' => $attributes['must_change_password'] ?? false,
        ]);

        $user->roles()->syncWithoutDetaching($role);

        return $user;
    }

    private function grantPermissions(User $manager, array $permissionNames): void
    {
        $ids = Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all();
        $manager->permissions()->sync($ids);
    }

    private function assign(User $manager, User $prestataire, User $actor): void
    {
        $manager->assignedPrestataires()->syncWithoutDetaching([
            $prestataire->getKey() => ['assigned_by' => $actor->getKey()],
        ]);
    }

    private function resetStatusToPending(object $model): void
    {
        $model::query()->whereKey($model->getKey())->update(['status' => 'pending']);
        $model->refresh();
    }

    private function validPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n4 0 obj\n<< /Length 44 >>\nstream\nBT\n/F1 24 Tf\n50 70 Td\n(Test) Tj\nET\nendstream\nendobj\n5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000062 00000 n \n0000000123 00000 n \n0000000245 00000 n \n0000000833 00000 n \ntrailer\n<< /Root 1 0 R /Size 6 >>\nstartxref\n900\n%%EOF";
    }

    private function company(User $prestataire, array $attributes = []): Company
    {
        $company = Company::query()->create(array_replace([
            'prestataire_id' => $prestataire->getKey(),
            'name' => 'Entreprise de test',
            'activity' => 'Activité de test',
            'assignment' => 'Mission de test',
            'additional_info' => 'Information complémentaire',
            'status' => Company::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));

        foreach (CompanyDocument::requiredTypes() as $type) {
            $path = 'company-'.$company->getKey().'-'.$type.'-'.uniqid().'.pdf';
            Storage::disk('documents_private')->put($path, $this->validPdfContent());

            CompanyDocument::query()->create([
                'company_id' => $company->getKey(),
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'validated_at' => now(),
                'submitted_at' => now(),
            ]);
        }

        return $company;
    }

    private function employee(Company $company, array $attributes = []): CompanyEmployee
    {
        $employee = CompanyEmployee::query()->create(array_replace([
            'company_id' => $company->getKey(),
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'cin' => 'CIN-'.uniqid(),
            'position' => 'Agent de chantier',
            'status' => CompanyEmployee::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));

        foreach (CompanyEmployeeDocument::requiredTypes() as $type) {
            $path = 'employee-'.$employee->getKey().'-'.$type.'-'.uniqid().'.pdf';
            Storage::disk('documents_private')->put($path, $this->validPdfContent());

            CompanyEmployeeDocument::query()->create([
                'company_employee_id' => $employee->getKey(),
                'type' => $type,
                'label' => CompanyEmployeeDocument::labelForType($type),
                'status' => CompanyEmployeeDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'validated_at' => now(),
                'submitted_at' => now(),
            ]);
        }

        return $employee;
    }

    private function machinery(Company $company, array $attributes = []): CompanyMachinery
    {
        $machinery = CompanyMachinery::query()->create(array_replace([
            'company_id' => $company->getKey(),
            'designation' => 'Engin de test',
            'category' => 'Camion',
            'assignment' => 'Chantier',
            'brand' => 'Renault',
            'model' => 'Model X',
            'serial_number' => 'SER-'.uniqid(),
            'registration_number' => 'REG-'.uniqid(),
            'commissioned_at' => now()->toDateString(),
            'description' => 'Description de test',
            'status' => CompanyMachinery::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));

        foreach (CompanyMachineryDocument::allTypes() as $type) {
            $path = 'machinery-'.$machinery->getKey().'-'.$type.'-'.uniqid().'.pdf';
            Storage::disk('documents_private')->put($path, $this->validPdfContent());

            CompanyMachineryDocument::query()->create([
                'company_machinery_id' => $machinery->getKey(),
                'type' => $type,
                'label' => CompanyMachineryDocument::labelForType($type),
                'status' => CompanyMachineryDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'validated_at' => now(),
                'submitted_at' => now(),
            ]);
        }

        return $machinery;
    }

    private function tooling(Company $company, array $attributes = []): CompanyTooling
    {
        $tooling = CompanyTooling::query()->create(array_replace([
            'company_id' => $company->getKey(),
            'designation' => 'Outillage de test',
            'category' => 'Outil de précision',
            'internal_reference' => 'REF-'.uniqid(),
            'brand' => 'Bosch',
            'model' => 'Pro',
            'serial_number' => 'SER-'.uniqid(),
            'quantity' => 1,
            'physical_condition' => 'good',
            'acquired_at' => now()->toDateString(),
            'location' => 'Entrepôt',
            'description' => 'Description de test',
            'status' => CompanyTooling::STATUS_PENDING,
            'submitted_at' => now(),
        ], $attributes));

        foreach (CompanyToolingDocument::standardTypes() as $type) {
            $path = 'tooling-'.$tooling->getKey().'-'.$type.'-'.uniqid().'.pdf';
            Storage::disk('documents_private')->put($path, $this->validPdfContent());

            CompanyToolingDocument::query()->create([
                'company_tooling_id' => $tooling->getKey(),
                'type' => $type,
                'label' => CompanyToolingDocument::labelForType($type),
                'status' => CompanyToolingDocument::STATUS_VALIDATED,
                'file_name' => $type.'.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'validated_at' => now(),
                'submitted_at' => now(),
            ]);
        }

        return $tooling;
    }

    private function companyDocument(Company $company): CompanyDocument
    {
        $path = 'company-doc-'.uniqid().'.pdf';
        Storage::disk('documents_private')->put($path, $this->validPdfContent());

        return CompanyDocument::query()->firstOrCreate(
            ['company_id' => $company->getKey(), 'type' => CompanyDocument::TYPE_ASSURANCE_AT],
            [
                'label' => 'Assurance AT',
                'status' => CompanyDocument::STATUS_PENDING,
                'file_name' => 'assurance.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'submitted_at' => now(),
            ],
        );
    }

    private function employeeDocument(CompanyEmployee $employee): CompanyEmployeeDocument
    {
        $path = 'employee-doc-'.uniqid().'.pdf';
        Storage::disk('documents_private')->put($path, $this->validPdfContent());

        return CompanyEmployeeDocument::query()->firstOrCreate(
            ['company_employee_id' => $employee->getKey(), 'type' => CompanyEmployeeDocument::TYPE_CIN],
            [
                'label' => 'CIN',
                'status' => CompanyEmployeeDocument::STATUS_PENDING,
                'file_name' => 'cin.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'submitted_at' => now(),
            ],
        );
    }

    private function machineryDocument(CompanyMachinery $machinery): CompanyMachineryDocument
    {
        $path = 'machinery-doc-'.uniqid().'.pdf';
        Storage::disk('documents_private')->put($path, $this->validPdfContent());

        return CompanyMachineryDocument::query()->firstOrCreate(
            ['company_machinery_id' => $machinery->getKey(), 'type' => CompanyMachineryDocument::TYPE_REGISTRATION_CARD],
            [
                'label' => 'Carte grise',
                'status' => CompanyMachineryDocument::STATUS_PENDING,
                'file_name' => 'carte-grise.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'submitted_at' => now(),
            ],
        );
    }

    private function toolingDocument(CompanyTooling $tooling): CompanyToolingDocument
    {
        $path = 'tooling-doc-'.uniqid().'.pdf';
        Storage::disk('documents_private')->put($path, $this->validPdfContent());

        return CompanyToolingDocument::query()->firstOrCreate(
            ['company_tooling_id' => $tooling->getKey(), 'type' => CompanyToolingDocument::TYPE_TECHNICAL_SHEET],
            [
                'label' => 'Fiche technique',
                'status' => CompanyToolingDocument::STATUS_PENDING,
                'file_name' => 'fiche-technique.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'submitted_at' => now(),
            ],
        );
    }
}
