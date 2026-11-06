<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationRbacTest extends TestCase
{
    use DatabaseTransactions;

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
            ->assertSee('Total des documents')
            ->assertSee('Aucun document pour le moment')
            ->assertSee(route('prestataire.documents.index'));
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
            ->assertSee('Soumissions récentes')
            ->assertSee(route('gestionnaire.documents.pending'));
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
            ->get(route('gestionnaire.documents.pending'))
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

        if ($routeName === 'prestataire.safety-team') {
            $response->assertSee('Mon équipe de sécurité')
                ->assertSee('Ajouter un membre')
                ->assertDontSee('Fonctionnalité en cours de développement');

            return;
        }

        if (str_ends_with($routeName, 'notifications.index')) {
            $response->assertSee('Centre de notifications')
                ->assertDontSee('Fonctionnalité en cours de développement');

            return;
        }

        $response->assertSee('Fonctionnalité en cours de développement');
    }

    public function test_prestataire_dashboard_shows_document_status_counts(): void
    {
        $user = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire');

        $this->createDocument($user, 'pending');
        $this->createDocument($user, 'validated');
        $this->createDocument($user, 'rejected');
        $this->createDocument($otherPrestataire, 'pending');

        $this->actingAs($user)
            ->get(route('prestataire.dashboard'))
            ->assertOk()
            ->assertSee('Total des documents')
            ->assertSee('3')
            ->assertSee('En attente')
            ->assertSee('Validés')
            ->assertSee('Rejetés');
    }

    public function test_gestionnaire_dashboard_shows_global_counts_and_recent_documents(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');

        $this->createDocument($prestataire, 'pending', 'Demande de chantier');
        $this->createDocument($prestataire, 'validated', 'Accès atelier');

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.dashboard'))
            ->assertOk()
            ->assertSee('2')
            ->assertSee('Demande de chantier')
            ->assertSee('Accès atelier')
            ->assertSee($prestataire->name);
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
            'prestataire safety team' => ['prestataire', 'prestataire.safety-team'],
            'prestataire notifications' => ['prestataire', 'prestataire.notifications.index'],
            'prestataire profile' => ['prestataire', 'prestataire.profile'],
            'gestionnaire validated documents' => ['gestionnaire', 'gestionnaire.documents.validated'],
            'gestionnaire rejected documents' => ['gestionnaire', 'gestionnaire.documents.rejected'],
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

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by authentication tests.'],
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

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
