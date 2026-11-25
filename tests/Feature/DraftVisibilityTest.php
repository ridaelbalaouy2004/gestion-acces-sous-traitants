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
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use App\Notifications\CompanyMachineryWorkflowNotification;
use App\Notifications\CompanyToolingWorkflowNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('documents_private');
    }

    public function test_manager_lists_only_submitted_resources_and_cannot_open_drafts_by_id(): void
    {
        $manager = $this->makeManager();
        $prestataire = $this->makePrestataire();
        $this->assignPrestataire($manager, $prestataire);

        $draftCompany = $this->makeCompany($prestataire, 'Entreprise brouillon', Company::STATUS_DRAFT);
        $submittedCompany = $this->makeCompany($prestataire, 'Entreprise soumise', Company::STATUS_PENDING);
        $rejectedCompany = $this->makeCompany($prestataire, 'Entreprise rejetée', Company::STATUS_REJECTED);
        $submittedEmployeeInDraftCompany = $this->makeEmployee(
            $draftCompany,
            'Employé soumis entreprise brouillon',
            CompanyEmployee::STATUS_PENDING,
        );
        $draftEmployee = $this->makeEmployee($submittedCompany, 'Employé brouillon', CompanyEmployee::STATUS_DRAFT);
        $submittedEmployee = $this->makeEmployee($submittedCompany, 'Employé soumis', CompanyEmployee::STATUS_PENDING);
        $rejectedEmployee = $this->makeEmployee($submittedCompany, 'Employé rejeté', CompanyEmployee::STATUS_REJECTED);
        $draftMachinery = $this->makeMachinery($submittedCompany, 'Engin brouillon', CompanyMachinery::STATUS_DRAFT);
        $submittedMachinery = $this->makeMachinery($submittedCompany, 'Engin soumis', CompanyMachinery::STATUS_PENDING);
        $rejectedMachinery = $this->makeMachinery($submittedCompany, 'Engin rejeté', CompanyMachinery::STATUS_REJECTED);
        $draftTooling = $this->makeTooling($submittedCompany, 'Outillage brouillon', CompanyTooling::STATUS_DRAFT);
        $submittedTooling = $this->makeTooling($submittedCompany, 'Outillage soumis', CompanyTooling::STATUS_PENDING);
        $rejectedTooling = $this->makeTooling($submittedCompany, 'Outillage rejeté', CompanyTooling::STATUS_REJECTED);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.index'))
            ->assertOk()
            ->assertSee($submittedCompany->name)
            ->assertSee($rejectedCompany->name)
            ->assertDontSee($draftCompany->name);
        $this->get(route('gestionnaire.employees.index'))
            ->assertOk()
            ->assertSee('Employé soumis')
            ->assertSee('Employé soumis entreprise brouillon')
            ->assertSee('Employé rejeté')
            ->assertDontSee('Employé brouillon');
        $this->get(route('gestionnaire.machinery.index'))
            ->assertOk()
            ->assertSee('Engin soumis')
            ->assertSee('Engin rejeté')
            ->assertDontSee('Engin brouillon');
        $this->get(route('gestionnaire.toolings.index'))
            ->assertOk()
            ->assertSee('Outillage soumis')
            ->assertSee('Outillage rejeté')
            ->assertDontSee('Outillage brouillon');

        $this->get(route('gestionnaire.companies.show', $draftCompany))->assertNotFound();
        $this->get(route('gestionnaire.employees.show', $draftEmployee))->assertNotFound();
        $this->get(route('gestionnaire.employees.show', $submittedEmployeeInDraftCompany))->assertOk();
        $this->get(route('gestionnaire.machinery.show', $draftMachinery))->assertNotFound();
        $this->get(route('gestionnaire.toolings.show', $draftTooling))->assertNotFound();

        $this->post(route('gestionnaire.companies.approve', $draftCompany))->assertNotFound();
        $this->post(route('gestionnaire.employees.approve', $draftEmployee))->assertNotFound();
        $this->post(route('gestionnaire.machinery.approve', $draftMachinery))->assertNotFound();
        $this->post(route('gestionnaire.toolings.approve', $draftTooling))->assertNotFound();
        $this->put(route('gestionnaire.companies.update', $draftCompany), ['name' => 'Tentative'])->assertNotFound();
        $this->put(route('gestionnaire.employees.update', $draftEmployee), ['first_name' => 'Tentative'])->assertNotFound();
        $this->put(route('gestionnaire.machinery.update', $draftMachinery), [
            'designation' => 'Tentative',
            'category' => 'Test',
        ])->assertNotFound();
        $this->put(route('gestionnaire.toolings.update', $draftTooling), [
            'designation' => 'Tentative',
            'category' => 'Test',
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertModelExists($submittedEmployee);
        $this->assertModelExists($submittedMachinery);
        $this->assertModelExists($submittedTooling);
    }

    public function test_manager_sees_submitted_documents_but_not_draft_documents_or_downloads(): void
    {
        $manager = $this->makeManager();
        $prestataire = $this->makePrestataire();
        $this->assignPrestataire($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise documents', Company::STATUS_PENDING);
        $employee = $this->makeEmployee($company, 'Employée documents', CompanyEmployee::STATUS_PENDING);
        $machinery = $this->makeMachinery($company, 'Engin documents', CompanyMachinery::STATUS_PENDING);
        $tooling = $this->makeTooling($company, 'Outillage documents', CompanyTooling::STATUS_PENDING);

        $companyDraft = $company->documents()->create([
            'type' => CompanyDocument::TYPE_ASSURANCE_AT,
            'label' => 'Document entreprise brouillon',
            'status' => CompanyDocument::STATUS_DRAFT,
            'file_name' => 'brouillon.pdf',
            'file_path' => 'draft/company.pdf',
        ]);
        $companyPending = $company->documents()->create([
            'type' => CompanyDocument::TYPE_ASSURANCE_RC,
            'label' => 'Document entreprise en attente',
            'status' => CompanyDocument::STATUS_PENDING,
            'file_name' => 'soumis.pdf',
            'file_path' => 'submitted/company.pdf',
        ]);
        $company->documents()->create([
            'type' => CompanyDocument::TYPE_LISTE_PERSONNEL,
            'label' => 'Document entreprise validé',
            'status' => CompanyDocument::STATUS_VALIDATED,
        ]);
        $company->documents()->create([
            'type' => CompanyDocument::TYPE_LISTE_MATERIEL,
            'label' => 'Document entreprise rejeté',
            'status' => CompanyDocument::STATUS_REJECTED,
        ]);
        $company->reviewHistory()->create([
            'company_document_id' => $companyDraft->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'document_updated',
            'from_status' => CompanyDocument::STATUS_DRAFT,
            'to_status' => CompanyDocument::STATUS_DRAFT,
            'reason' => 'Historique document entreprise brouillon',
        ]);
        $employeeDraft = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CIN,
            'label' => 'Document employé brouillon',
            'status' => CompanyEmployeeDocument::STATUS_DRAFT,
            'file_name' => 'brouillon.pdf',
            'file_path' => 'draft/employee.pdf',
        ]);
        $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CNSS,
            'label' => 'Document employé soumis',
            'status' => CompanyEmployeeDocument::STATUS_PENDING,
        ]);
        $employee->history()->create([
            'company_employee_document_id' => $employeeDraft->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'document_updated',
            'from_status' => CompanyEmployeeDocument::STATUS_DRAFT,
            'to_status' => CompanyEmployeeDocument::STATUS_DRAFT,
            'reason' => 'Historique document employé brouillon',
        ]);
        $machineryDraft = $machinery->documents()->create([
            'type' => CompanyMachineryDocument::allTypes()[0],
            'label' => 'Document engin brouillon',
            'status' => CompanyMachineryDocument::STATUS_DRAFT,
            'file_name' => 'brouillon.pdf',
            'file_path' => 'draft/machinery.pdf',
        ]);
        $machinery->documents()->create([
            'type' => CompanyMachineryDocument::allTypes()[1],
            'label' => 'Document engin soumis',
            'status' => CompanyMachineryDocument::STATUS_PENDING,
        ]);
        $machinery->history()->create([
            'company_machinery_document_id' => $machineryDraft->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'document_updated',
            'from_status' => CompanyMachineryDocument::STATUS_DRAFT,
            'to_status' => CompanyMachineryDocument::STATUS_DRAFT,
            'reason' => 'Historique document engin brouillon',
        ]);
        $toolingDraft = $tooling->documents()->create([
            'type' => CompanyToolingDocument::standardTypes()[0],
            'label' => 'Document outillage brouillon',
            'status' => CompanyToolingDocument::STATUS_DRAFT,
            'file_name' => 'brouillon.pdf',
            'file_path' => 'draft/tooling.pdf',
        ]);
        $tooling->documents()->create([
            'type' => CompanyToolingDocument::standardTypes()[1],
            'label' => 'Document outillage soumis',
            'status' => CompanyToolingDocument::STATUS_PENDING,
        ]);
        $tooling->history()->create([
            'company_tooling_document_id' => $toolingDraft->getKey(),
            'user_id' => $manager->getKey(),
            'action' => 'document_updated',
            'from_status' => CompanyToolingDocument::STATUS_DRAFT,
            'to_status' => CompanyToolingDocument::STATUS_DRAFT,
            'reason' => 'Historique document outillage brouillon',
        ]);
        Storage::disk('documents_private')->put($companyPending->file_path, 'contenu');

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.show', $company))
            ->assertOk()
            ->assertSee('soumis.pdf')
            ->assertSee('Liste exhaustive des effectifs sur site')
            ->assertSee('Liste matériel')
            ->assertDontSee('brouillon.pdf')
            ->assertDontSee('Historique document entreprise brouillon');
        $this->get(route('gestionnaire.employees.show', $employee))
            ->assertOk()
            ->assertSee('CNSS')
            ->assertDontSee('brouillon.pdf')
            ->assertDontSee('Historique document employé brouillon');
        $this->get(route('gestionnaire.machinery.show', $machinery))
            ->assertOk()
            ->assertSee('Document engin soumis')
            ->assertDontSee('brouillon.pdf')
            ->assertDontSee('Historique document engin brouillon');
        $this->get(route('gestionnaire.toolings.show', $tooling))
            ->assertOk()
            ->assertSee('Document outillage soumis')
            ->assertDontSee('brouillon.pdf')
            ->assertDontSee('Historique document outillage brouillon');

        $this->get(route('gestionnaire.companies.documents.download', [$company, $companyDraft]))->assertNotFound();
        $this->get(route('gestionnaire.employees.documents.download', [$employee, $employeeDraft]))->assertNotFound();
        $this->get(route('gestionnaire.machinery.documents.download', [$machinery, $machineryDraft]))->assertNotFound();
        $this->get(route('gestionnaire.toolings.documents.download', [$tooling, $toolingDraft]))->assertNotFound();
        $this->get(route('gestionnaire.companies.documents.download', [$company, $companyPending]))->assertOk();
    }

    public function test_manager_dashboard_counts_only_submitted_resources(): void
    {
        $manager = $this->makeManager();
        $prestataire = $this->makePrestataire();
        $this->assignPrestataire($manager, $prestataire);
        $draftCompany = $this->makeCompany($prestataire, 'Entreprise dashboard brouillon', Company::STATUS_DRAFT);
        $submittedCompany = $this->makeCompany($prestataire, 'Entreprise dashboard soumise', Company::STATUS_PENDING);
        $this->makeEmployee($submittedCompany, 'Employé dashboard brouillon', CompanyEmployee::STATUS_DRAFT);
        $this->makeEmployee($submittedCompany, 'Employé dashboard soumis', CompanyEmployee::STATUS_PENDING);
        $this->makeMachinery($submittedCompany, 'Engin dashboard brouillon', CompanyMachinery::STATUS_DRAFT);
        $this->makeMachinery($submittedCompany, 'Engin dashboard soumis', CompanyMachinery::STATUS_PENDING);
        $this->makeTooling($submittedCompany, 'Outillage dashboard brouillon', CompanyTooling::STATUS_DRAFT);
        $this->makeTooling($submittedCompany, 'Outillage dashboard soumis', CompanyTooling::STATUS_PENDING);

        $this->actingAs($manager)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['companies'] === 1
                && $stats['pending_companies'] === 1
                && $stats['employees'] === 1
                && $stats['pending_employees'] === 1
                && $stats['machinery'] === 1
                && $stats['pending_machinery'] === 1
                && $stats['toolings'] === 1
                && $stats['pending_toolings'] === 1
                && $stats['pending_total'] === 4)
            ->assertViewHas('itemsToReview', fn ($items): bool => $items->pluck('title')->doesntContain(
                'Entreprise à examiner : '.$draftCompany->name,
            ));
    }

    public function test_manager_notifications_hide_draft_resources_and_documents_but_provider_notifications_remain_unchanged(): void
    {
        $manager = $this->makeManager();
        $prestataire = $this->makePrestataire();
        $this->assignPrestataire($manager, $prestataire);
        $company = $this->makeCompany($prestataire, 'Entreprise notifications', Company::STATUS_PENDING);
        $employee = $this->makeEmployee($company, 'Employée notifications', CompanyEmployee::STATUS_PENDING);
        $draftDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CIN,
            'label' => 'Document brouillon notification',
            'status' => CompanyEmployeeDocument::STATUS_DRAFT,
        ]);
        $submittedDocument = $employee->documents()->create([
            'type' => CompanyEmployeeDocument::TYPE_CNSS,
            'label' => 'Document soumis notification',
            'status' => CompanyEmployeeDocument::STATUS_PENDING,
        ]);
        $employeeDraft = $this->makeEmployee($company, 'Employé brouillon notification', CompanyEmployee::STATUS_DRAFT);

        $manager->notify(new CompanyEmployeeWorkflowNotification(
            $employee,
            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            $draftDocument,
        ));
        $draftDocumentNotificationId = $manager->notifications()
            ->where('type', CompanyEmployeeWorkflowNotification::class)
            ->where('data->document_id', $draftDocument->getKey())
            ->value('id');
        $manager->notify(new CompanyEmployeeWorkflowNotification(
            $employee,
            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            $submittedDocument,
        ));
        $submittedDocumentNotificationId = $manager->notifications()
            ->where('type', CompanyEmployeeWorkflowNotification::class)
            ->where('data->document_id', $submittedDocument->getKey())
            ->value('id');
        $manager->notify(new CompanyEmployeeWorkflowNotification(
            $employeeDraft,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        ));
        $draftEmployeeNotificationId = $manager->notifications()
            ->where('type', CompanyEmployeeWorkflowNotification::class)
            ->where('data->employee_id', $employeeDraft->getKey())
            ->value('id');
        $machinery = $this->makeMachinery($company, 'Engin notifications', CompanyMachinery::STATUS_PENDING);
        $draftMachineryDocument = $machinery->documents()->create([
            'type' => CompanyMachineryDocument::allTypes()[0],
            'label' => 'Document engin brouillon notification',
            'status' => CompanyMachineryDocument::STATUS_DRAFT,
        ]);
        $submittedMachineryDocument = $machinery->documents()->create([
            'type' => CompanyMachineryDocument::allTypes()[1],
            'label' => 'Document engin soumis notification',
            'status' => CompanyMachineryDocument::STATUS_PENDING,
        ]);
        $manager->notify(new CompanyMachineryWorkflowNotification(
            $machinery,
            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            $draftMachineryDocument,
        ));
        $draftMachineryNotificationId = $manager->notifications()
            ->where('type', CompanyMachineryWorkflowNotification::class)
            ->where('data->document_id', $draftMachineryDocument->getKey())
            ->value('id');
        $manager->notify(new CompanyMachineryWorkflowNotification(
            $machinery,
            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            $submittedMachineryDocument,
        ));
        $submittedMachineryNotificationId = $manager->notifications()
            ->where('type', CompanyMachineryWorkflowNotification::class)
            ->where('data->document_id', $submittedMachineryDocument->getKey())
            ->value('id');
        $tooling = $this->makeTooling($company, 'Outillage notifications', CompanyTooling::STATUS_PENDING);
        $draftToolingDocument = $tooling->documents()->create([
            'type' => CompanyToolingDocument::standardTypes()[0],
            'label' => 'Document outillage brouillon notification',
            'status' => CompanyToolingDocument::STATUS_DRAFT,
        ]);
        $submittedToolingDocument = $tooling->documents()->create([
            'type' => CompanyToolingDocument::standardTypes()[1],
            'label' => 'Document outillage soumis notification',
            'status' => CompanyToolingDocument::STATUS_PENDING,
        ]);
        $manager->notify(new CompanyToolingWorkflowNotification(
            $tooling,
            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            $draftToolingDocument,
        ));
        $draftToolingNotificationId = $manager->notifications()
            ->where('type', CompanyToolingWorkflowNotification::class)
            ->where('data->document_id', $draftToolingDocument->getKey())
            ->value('id');
        $manager->notify(new CompanyToolingWorkflowNotification(
            $tooling,
            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
            $submittedToolingDocument,
        ));
        $submittedToolingNotificationId = $manager->notifications()
            ->where('type', CompanyToolingWorkflowNotification::class)
            ->where('data->document_id', $submittedToolingDocument->getKey())
            ->value('id');
        $prestataire->notify(new CompanyEmployeeWorkflowNotification(
            $employeeDraft,
            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        ));

        $visibleManagerNotificationIds = $manager->visibleNotifications()->pluck('id');

        $this->assertContains($submittedDocumentNotificationId, $visibleManagerNotificationIds->all());
        $this->assertNotContains($draftDocumentNotificationId, $visibleManagerNotificationIds->all());
        $this->assertNotContains($draftEmployeeNotificationId, $visibleManagerNotificationIds->all());
        $this->assertContains($submittedMachineryNotificationId, $visibleManagerNotificationIds->all());
        $this->assertNotContains($draftMachineryNotificationId, $visibleManagerNotificationIds->all());
        $this->assertContains($submittedToolingNotificationId, $visibleManagerNotificationIds->all());
        $this->assertNotContains($draftToolingNotificationId, $visibleManagerNotificationIds->all());
        $this->assertSame(1, $prestataire->visibleNotifications()->count());
    }

    public function test_prestataire_can_still_edit_and_submit_a_draft_company(): void
    {
        $prestataire = $this->makePrestataire();
        $company = $this->makeCompany($prestataire, 'Entreprise à compléter', Company::STATUS_DRAFT);
        foreach (CompanyDocument::requiredTypes() as $type) {
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => CompanyDocument::STATUS_DRAFT,
                'file_name' => $type.'.pdf',
                'file_path' => 'prestataires/'.$prestataire->getKey().'/'.$type.'.pdf',
            ]);
        }

        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('Entreprise à compléter')
            ->assertSee('Brouillon');
        $this->put(route('prestataire.companies.update', $company), [
            'name' => 'Entreprise complétée',
            'activity' => 'Construction',
            'assignment' => 'Chantier test',
        ])->assertRedirect(route('prestataire.companies.show', $company));
        $this->post(route('prestataire.companies.submit', $company))
            ->assertRedirect(route('prestataire.companies.show', $company));

        $this->assertDatabaseHas('companies', [
            'id' => $company->getKey(),
            'name' => 'Entreprise complétée',
            'status' => Company::STATUS_PENDING,
        ]);
    }

    private function makeManager(): User
    {
        $manager = $this->makeUserWithRole('gestionnaire');
        $manager->permissions()->sync(Permission::query()->pluck('id'));

        return $manager;
    }

    private function makePrestataire(): User
    {
        return $this->makeUserWithRole('prestataire');
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function assignPrestataire(User $manager, User $prestataire): void
    {
        $manager->assignedPrestataires()->attach($prestataire, ['assigned_by' => $manager->getKey()]);
    }

    private function makeCompany(User $prestataire, string $name, string $status): Company
    {
        return $prestataire->companies()->create([
            'name' => $name,
            'activity' => 'Activité test',
            'assignment' => 'Chantier test',
            'status' => $status,
        ]);
    }

    private function makeEmployee(Company $company, string $name, string $status): CompanyEmployee
    {
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, 'Test');

        return $company->employees()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'cin' => strtoupper(substr(md5($name), 0, 10)),
            'position' => 'Technicien',
            'status' => $status,
        ]);
    }

    private function makeMachinery(Company $company, string $name, string $status): CompanyMachinery
    {
        return $company->machinery()->create([
            'designation' => $name,
            'category' => 'Engin test',
            'status' => $status,
        ]);
    }

    private function makeTooling(Company $company, string $name, string $status): CompanyTooling
    {
        return $company->toolings()->create([
            'designation' => $name,
            'category' => 'Outillage test',
            'quantity' => 1,
            'status' => $status,
        ]);
    }
}
