<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyMachinery;
use App\Models\CompanyTooling;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_every_dashboard(): void
    {
        foreach (self::dashboardPaths() as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_prestataire_can_access_their_dashboard(): void
    {
        $user = $this->userWithRole('prestataire');

        $this->actingAs($user)
            ->get(route('prestataire.dashboard'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Prestataire')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Mes entreprises')
            ->assertSee('Mon équipe')
            ->assertDontSee('Mes documents')
            ->assertDontSee('Équipe de sécurité (accès)');
    }

    public function test_prestataire_cannot_access_gestionnaire_dashboard(): void
    {
        $this->actingAs($this->userWithRole('prestataire'))
            ->get(route('gestionnaire.dashboard'))
            ->assertForbidden();
    }

    public function test_gestionnaire_can_access_their_dashboard(): void
    {
        $user = $this->userWithRole('gestionnaire');

        $this->actingAs($user)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Gestionnaire')
            ->assertSee('Gestion des équipes')
            ->assertDontSee('Documents en attente')
            ->assertDontSee('Mon équipe de sécurité');
    }

    public function test_gestionnaire_cannot_access_responsable_securite_dashboard(): void
    {
        $this->actingAs($this->userWithRole('gestionnaire'))
            ->get(route('responsable-securite.dashboard'))
            ->assertForbidden();
    }

    public function test_responsable_securite_can_access_their_dashboard(): void
    {
        $user = $this->userWithRole('responsable_securite');

        $this->actingAs($user)
            ->get(route('responsable-securite.dashboard'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Responsable sécurité')
            ->assertSee('Documents récemment validés')
            ->assertSee(route('responsable-securite.documents.index'));
    }

    public function test_responsable_securite_cannot_access_gestionnaire_dashboard(): void
    {
        $this->actingAs($this->userWithRole('responsable_securite'))
            ->get(route('gestionnaire.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_placeholders_are_restricted_to_their_role(): void
    {
        $this->actingAs($this->userWithRole('prestataire'))
            ->get(route('gestionnaire.employees.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('gestionnaire'))
            ->get(route('responsable-securite.documents.validated'))
            ->assertForbidden();
    }

    public function test_prestataire_notifications_destination_renders_the_shared_notifications_page(): void
    {
        $this->actingAs($this->userWithRole('prestataire'))
            ->get(route('prestataire.notifications.index'))
            ->assertOk()
            ->assertSee('Centre de notifications')
            ->assertSee('Aucune notification pour le moment')
            ->assertDontSee('Fonctionnalité en cours de développement')
            ->assertSee('Mon profil');
    }

    #[DataProvider('placeholderRoutesByRole')]
    public function test_each_sidebar_destination_loads_only_in_its_role_space(string $roleName, string $routeName): void
    {
        $response = $this->actingAs($this->userWithRole($roleName))
            ->get(route($routeName))
            ->assertOk();

        if (str_ends_with($routeName, 'notifications.index')) {
            $response->assertSee('Centre de notifications')
                ->assertDontSee('Fonctionnalité en cours de développement');

            return;
        }

        $response->assertSee('Mon profil')
            ->assertSee('Informations personnelles');
    }

    public function test_prestataire_dashboard_shows_company_and_employee_counts(): void
    {
        $user = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');
        $company = Company::query()->create([
            'prestataire_id' => $user->getKey(),
            'name' => 'Entreprise Dashboard',
            'status' => Company::STATUS_DRAFT,
        ]);
        CompanyEmployee::query()->create([
            'company_id' => $company->getKey(),
            'first_name' => 'Employé',
            'last_name' => 'Dashboard',
            'cin' => 'DASHBOARD123',
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
        $otherCompany = Company::query()->create([
            'prestataire_id' => $otherPrestataire->getKey(),
            'name' => 'Entreprise Externe',
            'status' => Company::STATUS_DRAFT,
        ]);
        CompanyEmployee::query()->create([
            'company_id' => $otherCompany->getKey(),
            'first_name' => 'Employé',
            'last_name' => 'Externe',
            'cin' => 'EXTERNAL123',
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->get(route('prestataire.dashboard'))
            ->assertOk()
            ->assertSee('Mes entreprises')
            ->assertSee('Employés permanents')
            ->assertSee('1');
    }

    public function test_gestionnaire_dashboard_shows_only_retained_module_shortcuts(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee(route('gestionnaire.companies.index'))
            ->assertSee(route('gestionnaire.employees.index'))
            ->assertDontSee('Documents en attente')
            ->assertDontSee('Équipe de sécurité (accès)');
    }

    public function test_gestionnaire_dashboard_exposes_workload_cards_and_review_queue(): void
    {
        $primaryManager = $this->userWithRole('gestionnaire');
        $secondaryManager = $this->userWithRole('gestionnaire');
        $permissionIds = Permission::query()->whereIn('name', [
            'entreprises.view',
            'employees.view',
            'machinery.view',
            'toolings.view',
        ])->pluck('id')->all();
        $secondaryManager->permissions()->sync($permissionIds);

        $assignedPrestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire assigné']);
        $foreignPrestataire = $this->userWithRole('prestataire', ['name' => 'Prestataire externe']);
        $secondaryManager->assignedPrestataires()->sync([
            $assignedPrestataire->getKey() => ['assigned_by' => $secondaryManager->getKey()],
        ]);

        $assignedCompany = Company::query()->create([
            'prestataire_id' => $assignedPrestataire->getKey(),
            'name' => 'Entreprise assignée',
            'status' => Company::STATUS_PENDING,
        ]);
        Company::query()->create([
            'prestataire_id' => $foreignPrestataire->getKey(),
            'name' => 'Entreprise externe',
            'status' => Company::STATUS_PENDING,
        ]);

        $assignedEmployee = CompanyEmployee::query()->create([
            'company_id' => $assignedCompany->getKey(),
            'first_name' => 'Alice',
            'last_name' => 'Assignée',
            'cin' => 'EMP-001',
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_PENDING,
        ]);
        CompanyEmployee::query()->create([
            'company_id' => $assignedCompany->getKey(),
            'first_name' => 'Bob',
            'last_name' => 'Externe',
            'cin' => 'EMP-002',
            'position' => 'Agent',
            'status' => CompanyEmployee::STATUS_VALIDATED,
        ]);

        $this->actingAs($secondaryManager)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee('Entreprises suivies')
            ->assertSee('Éléments à traiter')
            ->assertSee('Activités récentes');

        $this->assertDatabaseHas('manager_prestataire_assignments', [
            'manager_id' => $secondaryManager->getKey(),
            'prestataire_id' => $assignedPrestataire->getKey(),
        ]);
        $this->assertNotSame($primaryManager->getKey(), $secondaryManager->getKey());
        $this->assertNotNull($assignedEmployee->fresh());
    }

    public function test_sidebar_destination_routes_load_for_prestataires_and_gestionnaires(): void
    {
        $provider = $this->userWithRole('prestataire');
        $providerDestinations = [
            'prestataire.dashboard',
            'prestataire.companies.index',
            'prestataire.employees.index',
            'prestataire.machinery.index',
            'prestataire.toolings.index',
            'prestataire.notifications.index',
            'prestataire.profile',
        ];

        $this->actingAs($provider)
            ->get(route('prestataire.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('prestataire.dashboard').'"', false)
            ->assertSee('href="'.route('prestataire.companies.index').'"', false)
            ->assertSee('href="'.route('prestataire.employees.index').'"', false)
            ->assertSee('href="'.route('prestataire.machinery.index').'"', false)
            ->assertSee('href="'.route('prestataire.toolings.index').'"', false)
            ->assertSee('href="'.route('notifications.index').'"', false)
            ->assertSee('href="'.route('prestataire.profile').'"', false);

        foreach ($providerDestinations as $destination) {
            $this->get(route($destination))->assertOk();
        }

        $manager = $this->userWithRole('gestionnaire');
        $managerDestinations = [
            'gestionnaire.dashboard',
            'gestionnaire.companies.index',
            'gestionnaire.employees.index',
            'gestionnaire.machinery.index',
            'gestionnaire.toolings.index',
            'gestionnaire.prestataires.index',
            'gestionnaire.notifications.index',
            'gestionnaire.profile',
            'gestionnaire.employes.index',
        ];

        $this->actingAs($manager)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('gestionnaire.dashboard').'"', false)
            ->assertSee('href="'.route('gestionnaire.companies.index').'"', false)
            ->assertSee('href="'.route('gestionnaire.employees.index').'"', false)
            ->assertSee('href="'.route('gestionnaire.machinery.index').'"', false)
            ->assertSee('href="'.route('gestionnaire.toolings.index').'"', false)
            ->assertSee('href="'.route('gestionnaire.prestataires.index').'"', false)
            ->assertSee('href="'.route('notifications.index').'"', false)
            ->assertSee('href="'.route('gestionnaire.profile').'"', false)
            ->assertSee('href="'.route('gestionnaire.employes.index').'"', false);

        foreach ($managerDestinations as $destination) {
            $this->get(route($destination))->assertOk();
        }
    }

    public function test_sidebar_highlights_the_parent_module_on_detail_pages(): void
    {
        $provider = $this->userWithRole('prestataire');
        $manager = $this->userWithRole('gestionnaire');
        $company = Company::query()->create([
            'prestataire_id' => $provider->getKey(),
            'name' => 'Entreprise navigation',
            'status' => Company::STATUS_DRAFT,
        ]);
        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->getKey(),
            'first_name' => 'Employé',
            'last_name' => 'Navigation',
            'status' => CompanyEmployee::STATUS_DRAFT,
        ]);
        $machinery = CompanyMachinery::query()->create([
            'company_id' => $company->getKey(),
            'designation' => 'Engin navigation',
            'category' => 'Test',
            'status' => CompanyMachinery::STATUS_DRAFT,
        ]);
        $tooling = CompanyTooling::query()->create([
            'company_id' => $company->getKey(),
            'designation' => 'Outillage navigation',
            'category' => 'Test',
            'status' => CompanyTooling::STATUS_DRAFT,
        ]);
        $manager->assignedPrestataires()->attach($provider, ['assigned_by' => $manager->getKey()]);

        $providerPages = [
            ['prestataire.companies.show', $company, 'prestataire.companies.index'],
            ['prestataire.employees.show', $employee, 'prestataire.employees.index'],
            ['prestataire.machinery.show', $machinery, 'prestataire.machinery.index'],
            ['prestataire.toolings.show', $tooling, 'prestataire.toolings.index'],
        ];

        foreach ($providerPages as [$pageRoute, $resource, $menuRoute]) {
            $this->actingAs($provider)
                ->get(route($pageRoute, $resource))
                ->assertOk()
                ->assertSee('href="'.route($menuRoute).'"', false)
                ->assertSee('aria-current="page"', false)
                ->assertSee('href="'.route('prestataire.toolings.index').'"', false);
        }

        $company->update(['status' => Company::STATUS_PENDING]);
        $employee->update(['status' => CompanyEmployee::STATUS_PENDING]);
        $machinery->update(['status' => CompanyMachinery::STATUS_PENDING]);
        $tooling->update(['status' => CompanyTooling::STATUS_PENDING]);

        $managerPages = [
            ['gestionnaire.companies.show', $company, 'gestionnaire.companies.index'],
            ['gestionnaire.employees.show', $employee, 'gestionnaire.employees.index'],
            ['gestionnaire.machinery.show', $machinery, 'gestionnaire.machinery.index'],
            ['gestionnaire.toolings.show', $tooling, 'gestionnaire.toolings.index'],
        ];

        foreach ($managerPages as [$pageRoute, $resource, $menuRoute]) {
            $this->actingAs($manager)
                ->get(route($pageRoute, $resource))
                ->assertOk()
                ->assertSee('href="'.route($menuRoute).'"', false)
                ->assertSee('aria-current="page"', false);
        }
    }

    public function test_removed_provider_and_manager_workflow_routes_are_unregistered(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $manager = $this->userWithRole('gestionnaire');

        $this->actingAs($prestataire)
            ->get('/prestataire/documents')
            ->assertNotFound();
        $this->get('/prestataire/equipe-securite')->assertNotFound();

        $this->actingAs($manager)
            ->get('/gestionnaire/documents')
            ->assertNotFound();
        $this->get('/gestionnaire/membres-securite')->assertNotFound();
    }

    public function test_security_dashboard_only_counts_validated_documents(): void
    {
        $securityLead = $this->userWithRole('responsable_securite');
        $prestataire = $this->userWithRole('prestataire');

        $this->createDocument($prestataire, 'pending');
        $this->createDocument($prestataire, 'rejected');
        $this->createDocument($prestataire, 'validated', 'Dossier validé');

        $this->actingAs($securityLead)
            ->get(route('responsable-securite.dashboard'))
            ->assertOk()
            ->assertSee('Documents validés')
            ->assertSee('Dossier validé')
            ->assertDontSee('Document en attente')
            ->assertDontSee('Document rejeté');
    }

    public function test_login_page_has_french_fields_and_remember_option(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Adresse e-mail')
            ->assertSee('Mot de passe')
            ->assertSee('Afficher')
            ->assertSee('Se souvenir de moi');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->userWithRole('prestataire');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_null_is_active_is_treated_as_active_for_legacy_accounts(): void
    {
        $user = User::make([
            'name' => 'Legacy User',
            'email' => 'legacy@example.test',
            'password' => 'password',
            'is_active' => null,
        ]);

        $this->assertTrue($user->isAccountActive());
    }

    public function test_explicitly_inactive_accounts_are_rejected(): void
    {
        $user = $this->userWithRole('prestataire', ['is_active' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_remember_option_sets_a_persistent_remember_token(): void
    {
        $user = $this->userWithRole('prestataire');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('prestataire.dashboard'));

        $this->assertNotNull($user->fresh()->remember_token);
    }

    #[DataProvider('userRoleDashboardRoutes')]
    public function test_successful_login_redirects_to_the_assigned_role_dashboard(
        string $roleName,
        string $dashboardRoute,
    ): void {
        $user = $this->userWithRole($roleName);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route($dashboardRoute));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_their_dashboard(): void
    {
        $user = $this->userWithRole('prestataire');

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('prestataire.dashboard'));
    }

    public function test_successful_login_regenerates_the_session_id(): void
    {
        $user = $this->userWithRole('prestataire');
        $this->get(route('login'));
        $originalSessionId = session()->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('prestataire.dashboard'));

        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_logout_invalidates_the_session_and_authentication(): void
    {
        $user = $this->userWithRole('prestataire');

        $this->actingAs($user)
            ->withSession(['sensitive_value' => 'session-data'])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse(session()->has('sensitive_value'));
    }

    public function test_user_without_a_role_cannot_access_a_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('prestataire.dashboard'))
            ->assertForbidden();
    }

    public function test_user_without_a_role_is_not_authenticated_after_login(): void
    {
        $user = User::factory()->create();

        $this->followingRedirects()
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertSee('Aucun rôle n’est associé à ce compte.');

        $this->assertGuest();
    }

    public function test_ouvrier_role_cannot_sign_in(): void
    {
        $worker = $this->userWithRole('ouvrier');

        $this->followingRedirects()
            ->post(route('login.store'), [
                'email' => $worker->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertSee('Aucun rôle n’est associé à ce compte.');

        $this->assertGuest();
    }

    public function test_login_requests_are_rate_limited_per_email_and_ip(): void
    {
        $credentials = [
            'email' => 'unknown-login-rate-limit@example.com',
            'password' => 'incorrect-password',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), $credentials);
        }

        $this->post(route('login.store'), $credentials)
            ->assertTooManyRequests();
    }

    public static function userRoleDashboardRoutes(): array
    {
        return [
            'prestataire' => ['prestataire', 'prestataire.dashboard'],
            'gestionnaire' => ['gestionnaire', 'gestionnaire.dashboard'],
            'responsable securite' => ['responsable_securite', 'responsable-securite.dashboard'],
        ];
    }

    public static function placeholderRoutesByRole(): array
    {
        return [
            'prestataire notifications' => ['prestataire', 'prestataire.notifications.index'],
            'prestataire profile' => ['prestataire', 'prestataire.profile'],
            'gestionnaire notifications' => ['gestionnaire', 'gestionnaire.notifications.index'],
            'gestionnaire profile' => ['gestionnaire', 'gestionnaire.profile'],
            'security notifications' => ['responsable_securite', 'responsable-securite.notifications.index'],
            'security profile' => ['responsable_securite', 'responsable-securite.profile'],
        ];
    }

    private static function dashboardPaths(): array
    {
        return [
            '/prestataire/dashboard',
            '/gestionnaire/dashboard',
            '/responsable-securite/dashboard',
        ];
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by authentication tests.'],
        );
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);
        if ($roleName === 'gestionnaire') {
            $this->designatePrimaryManagerForLegacyTests($user);
        }

        return $user;
    }

    private function createDocument(User $prestataire, string $status, string $title = ''): Document
    {
        static $documentNumber = 0;
        $documentNumber++;

        return Document::create([
            'prestataire_id' => $prestataire->id,
            'title' => $title !== '' ? $title : "Document {$status} {$documentNumber}",
            'reference_number' => 'TEST-DASHBOARD-'.$documentNumber.'-'.uniqid(),
            'status' => $status,
            'submitted_at' => now(),
            'validated_at' => $status === 'validated' ? now() : null,
        ]);
    }
}
