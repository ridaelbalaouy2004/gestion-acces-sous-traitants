<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyMachinery;
use App\Models\CompanyTooling;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataProgressCountersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_prestataire_counters_show_uploaded_required_documents_and_all_owned_records(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise du Prestataire');

        foreach (CompanyDocument::requiredTypes() as $index => $type) {
            $uploaded = in_array($index, [0, 1, 2], true);
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => $index === 1 ? CompanyDocument::STATUS_REJECTED : CompanyDocument::STATUS_DRAFT,
                'file_name' => $uploaded ? $type.'.pdf' : null,
                'file_path' => $uploaded ? 'companies/'.$company->getKey().'/'.$type.'.pdf' : null,
            ]);
        }

        $company->documents()->create([
            'type' => 'document_optionnel',
            'label' => 'Document facultatif',
            'status' => CompanyDocument::STATUS_VALIDATED,
            'file_name' => 'facultatif.pdf',
            'file_path' => 'companies/'.$company->getKey().'/facultatif.pdf',
        ]);

        $company->employees()->createMany([
            ['first_name' => 'Nadia', 'last_name' => 'Amrani', 'status' => CompanyEmployee::STATUS_DRAFT],
            ['first_name' => 'Youssef', 'last_name' => 'Idrissi', 'status' => CompanyEmployee::STATUS_REJECTED],
        ]);
        $company->machinery()->createMany([
            ['designation' => 'Engin 1', 'category' => 'Camion', 'status' => CompanyMachinery::STATUS_DRAFT],
            ['designation' => 'Engin 2', 'category' => 'Grue', 'status' => CompanyMachinery::STATUS_REJECTED],
            ['designation' => 'Engin 3', 'category' => 'Chariot', 'status' => CompanyMachinery::STATUS_PENDING],
        ]);
        $company->toolings()->createMany([
            ['designation' => 'Outillage 1', 'category' => 'Clé', 'quantity' => 1, 'status' => CompanyTooling::STATUS_DRAFT],
            ['designation' => 'Outillage 2', 'category' => 'Pince', 'quantity' => 1, 'status' => CompanyTooling::STATUS_REJECTED],
            ['designation' => 'Outillage 3', 'category' => 'Perceuse', 'quantity' => 1, 'status' => CompanyTooling::STATUS_PENDING],
            ['designation' => 'Outillage 4', 'category' => 'Scie', 'quantity' => 1, 'status' => CompanyTooling::STATUS_VALIDATED],
        ]);

        $this->actingAs($prestataire)
            ->get(route('prestataire.companies.index'))
            ->assertOk()
            ->assertSee('3/9 documents');

        $this->get(route('prestataire.employees.index'))
            ->assertOk()
            ->assertSee('2 employés');
        $this->get(route('prestataire.machinery.index'))
            ->assertOk()
            ->assertSee('3 engins');
        $this->get(route('prestataire.toolings.index'))
            ->assertOk()
            ->assertSee('4 outillages');

        $this->assertDatabaseHas('company_documents', [
            'company_id' => $company->getKey(),
            'type' => CompanyDocument::requiredTypes()[1],
            'status' => CompanyDocument::STATUS_REJECTED,
            'file_path' => 'companies/'.$company->getKey().'/'.CompanyDocument::requiredTypes()[1].'.pdf',
        ]);
    }

    public function test_manager_counters_are_scoped_to_assigned_and_visible_provider_records(): void
    {
        $manager = $this->userWithRole('gestionnaire');
        $manager->permissions()->attach(
            Permission::query()->whereIn('name', [
                'entreprises.view',
                'employees.view',
                'machinery.view',
                'toolings.view',
            ])->pluck('id'),
        );
        $assignedPrestataire = $this->userWithRole('prestataire');
        $unassignedPrestataire = $this->userWithRole('prestataire');
        $manager->assignedPrestataires()->attach($assignedPrestataire, ['assigned_by' => $manager->getKey()]);

        $company = $this->company($assignedPrestataire, 'Entreprise accessible', Company::STATUS_PENDING);
        $privateCompany = $this->company($unassignedPrestataire, 'Entreprise privée', Company::STATUS_PENDING);
        $this->addRequiredDocuments($company, [0, 1, 2]);
        $this->addRequiredDocuments($privateCompany, [0, 1, 2, 3]);

        $company->employees()->createMany([
            ['first_name' => 'Employé visible', 'last_name' => 'Test', 'status' => CompanyEmployee::STATUS_PENDING],
            ['first_name' => 'Employé brouillon', 'last_name' => 'Test', 'status' => CompanyEmployee::STATUS_DRAFT],
        ]);
        $privateCompany->employees()->create([
            'first_name' => 'Employé privé',
            'last_name' => 'Test',
            'status' => CompanyEmployee::STATUS_PENDING,
        ]);
        $company->machinery()->createMany([
            ['designation' => 'Engin visible', 'category' => 'Camion', 'status' => CompanyMachinery::STATUS_PENDING],
            ['designation' => 'Engin brouillon', 'category' => 'Grue', 'status' => CompanyMachinery::STATUS_DRAFT],
        ]);
        $privateCompany->machinery()->create([
            'designation' => 'Engin privé',
            'category' => 'Chariot',
            'status' => CompanyMachinery::STATUS_PENDING,
        ]);
        $company->toolings()->createMany([
            ['designation' => 'Outillage visible', 'category' => 'Clé', 'quantity' => 1, 'status' => CompanyTooling::STATUS_PENDING],
            ['designation' => 'Outillage brouillon', 'category' => 'Pince', 'quantity' => 1, 'status' => CompanyTooling::STATUS_DRAFT],
        ]);
        $privateCompany->toolings()->create([
            'designation' => 'Outillage privé',
            'category' => 'Perceuse',
            'quantity' => 1,
            'status' => CompanyTooling::STATUS_PENDING,
        ]);

        $this->actingAs($manager)
            ->get(route('gestionnaire.companies.index', ['prestataire_id' => $assignedPrestataire->getKey()]))
            ->assertOk()
            ->assertSee('3/9 documents')
            ->assertDontSee('Entreprise privée');

        $this->get(route('gestionnaire.employees.index', ['prestataire_id' => $assignedPrestataire->getKey()]))
            ->assertOk()
            ->assertSee('1 employé')
            ->assertDontSee('Employé privé')
            ->assertDontSee('Employé brouillon');
        $this->get(route('gestionnaire.machinery.index', ['prestataire_id' => $assignedPrestataire->getKey()]))
            ->assertOk()
            ->assertSee('1 engin')
            ->assertDontSee('Engin privé')
            ->assertDontSee('Engin brouillon');
        $this->get(route('gestionnaire.toolings.index', ['prestataire_id' => $assignedPrestataire->getKey()]))
            ->assertOk()
            ->assertSee('1 outillage')
            ->assertDontSee('Outillage privé')
            ->assertDontSee('Outillage brouillon');
    }

    public function test_company_history_keeps_all_entries_inside_a_scrollable_region(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $company = $this->company($prestataire, 'Entreprise avec historique');

        foreach (range(1, 30) as $index) {
            $company->reviewHistory()->create([
                'user_id' => $prestataire->getKey(),
                'action' => 'updated',
                'reason' => 'Entrée historique '.$index,
            ]);
        }

        $response = $this->actingAs($prestataire)
            ->get(route('prestataire.companies.show', $company))
            ->assertOk()
            ->assertSee('max-h-56 space-y-3 overflow-y-auto overscroll-contain', false)
            ->assertSee('Entrée historique 1')
            ->assertSee('Entrée historique 30');

        $this->assertSame(30, substr_count($response->getContent(), 'Entrée historique'));
    }

    /**
     * @param  array<int, int>  $uploadedIndexes
     */
    private function addRequiredDocuments(Company $company, array $uploadedIndexes): void
    {
        foreach (CompanyDocument::requiredTypes() as $index => $type) {
            $uploaded = in_array($index, $uploadedIndexes, true);
            $company->documents()->create([
                'type' => $type,
                'label' => CompanyDocument::labelForType($type),
                'status' => $index === 1 ? CompanyDocument::STATUS_REJECTED : CompanyDocument::STATUS_PENDING,
                'file_name' => $uploaded ? $type.'.pdf' : null,
                'file_path' => $uploaded ? 'companies/'.$company->getKey().'/'.$type.'.pdf' : null,
            ]);
        }
    }

    private function company(User $prestataire, string $name, string $status = Company::STATUS_DRAFT): Company
    {
        return Company::query()->create([
            'prestataire_id' => $prestataire->getKey(),
            'name' => $name,
            'activity' => 'Construction',
            'assignment' => 'Chantier A',
            'status' => $status,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
