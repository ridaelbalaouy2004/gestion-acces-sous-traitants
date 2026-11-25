<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GestionnairePrestataireAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gestionnaire_creates_a_prestataire_with_a_hashed_temporary_password_and_role(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $this->role('prestataire');
        $temporaryPassword = 'Temporary-Password-42!';

        $response = $this->actingAs($gestionnaire)->post(route('gestionnaire.prestataires.store'), [
            'name' => 'Nadia Prestataire',
            'email' => 'nadia@example.test',
            'password' => $temporaryPassword,
            'password_confirmation' => $temporaryPassword,
            'is_active' => false,
            'must_change_password' => false,
            'roles' => ['gestionnaire'],
        ]);
        $response->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('status', 'Le compte Prestataire a été créé.');

        $prestataire = User::query()->where('email', 'nadia@example.test')->firstOrFail();

        $response->assertRedirect(route('gestionnaire.prestataires.show', $prestataire))
            ->assertSessionHas('temporary_password')
            ->assertSessionHas('temporary_password_prestataire_id', $prestataire->id);
        $this->assertDatabaseHas('users', [
            'id' => $prestataire->id,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $this->assertTrue(Hash::check($temporaryPassword, $prestataire->password));
        $this->assertSame('prestataire', $prestataire->roles()->sole()->name);

        $this->get(route('gestionnaire.prestataires.show', $prestataire))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($temporaryPassword);
    }

    public function test_generated_temporary_password_is_shown_only_once(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $this->role('prestataire');

        $response = $this->actingAs($gestionnaire)->post(route('gestionnaire.prestataires.store'), [
            'name' => 'Samira Benali',
            'email' => 'samira@example.test',
        ]);
        $response->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('status', 'Le compte Prestataire a été créé.');

        $prestataire = User::query()->where('email', 'samira@example.test')->firstOrFail();
        $encryptedTemporaryPassword = session('temporary_password');

        $this->assertIsString($encryptedTemporaryPassword);
        $temporaryPassword = Crypt::decryptString($encryptedTemporaryPassword);
        $this->assertGreaterThanOrEqual(12, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $prestataire->password));

        $response->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));
        $this->get(route('gestionnaire.prestataires.show', $prestataire))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($temporaryPassword);
        $this->get(route('gestionnaire.prestataires.show', $prestataire))
            ->assertOk()
            ->assertSessionMissing('temporary_password')
            ->assertDontSee($temporaryPassword);
    }

    public function test_existing_email_is_rejected_without_creating_a_second_account(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $existingUser = User::factory()->create(['email' => 'already-used@example.test']);

        $this->actingAs($gestionnaire)
            ->post(route('gestionnaire.prestataires.store'), [
                'name' => 'Duplicate Account',
                'email' => $existingUser->email,
            ])
            ->assertSessionHasErrors([
                'email' => 'Cette adresse e-mail est déjà utilisée.',
            ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_gestionnaire_can_search_and_filter_prestataire_accounts(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $active = $this->userWithRole('prestataire', ['name' => 'Camille Durand', 'email' => 'camille@example.test']);
        $inactive = $this->userWithRole('prestataire', [
            'name' => 'Alex Martin',
            'email' => 'alex@example.test',
            'is_active' => false,
        ]);

        $response = $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.prestataires.index', ['search' => 'Camille', 'status' => 'active']))
            ->assertOk();

        $response
            ->assertSee($active->name)
            ->assertSee('data-navigate-to="'.route('gestionnaire.prestataires.show', $active).'"', false)
            ->assertSee('Consulter')
            ->assertSee('href="'.route('gestionnaire.prestataires.edit', $active).'"', false)
            ->assertSee('action="'.route('gestionnaire.prestataires.toggle-status', $active).'"', false)
            ->assertDontSee($inactive->name);
    }

    public function test_gestionnaire_can_update_contact_details_without_changing_role_or_status(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['is_active' => false]);

        $this->actingAs($gestionnaire)
            ->put(route('gestionnaire.prestataires.update', $prestataire), [
                'name' => 'Nouveau Nom',
                'email' => 'nouveau@example.test',
                'is_active' => true,
                'roles' => ['gestionnaire'],
            ])
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));

        $prestataire->refresh();
        $this->assertSame('Nouveau Nom', $prestataire->name);
        $this->assertSame('nouveau@example.test', $prestataire->email);
        $this->assertFalse($prestataire->is_active);
        $this->assertSame('prestataire', $prestataire->roles()->sole()->name);
    }

    public function test_update_rejects_an_email_used_by_another_account(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', ['email' => 'first@example.test']);
        $otherPrestataire = $this->userWithRole('prestataire', ['email' => 'second@example.test']);

        $this->actingAs($gestionnaire)
            ->put(route('gestionnaire.prestataires.update', $prestataire), [
                'name' => 'Nouveau Nom',
                'email' => $otherPrestataire->email,
            ])
            ->assertSessionHasErrors([
                'email' => 'Cette adresse e-mail est déjà utilisée.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $prestataire->id,
            'name' => $prestataire->name,
            'email' => 'first@example.test',
        ]);
    }

    public function test_disabling_a_prestataire_keeps_the_account_role_and_documents(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire');
        $document = Document::create([
            'prestataire_id' => $prestataire->id,
            'title' => 'Historique conservé',
            'status' => Document::STATUS_PENDING,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));

        $this->assertDatabaseHas('users', ['id' => $prestataire->id, 'is_active' => false]);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'prestataire_id' => $prestataire->id]);
        $this->assertSame('prestataire', $prestataire->fresh()->roles()->sole()->name);
        $this->assertModelExists($prestataire);
    }

    public function test_disabled_prestataire_cannot_log_in(): void
    {
        $prestataire = $this->userWithRole('prestataire', [
            'email' => 'disabled@example.test',
            'password' => 'Temporary-Password-42!',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $prestataire->email,
            'password' => 'Temporary-Password-42!',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->actingAs($prestataire)
            ->get(route('prestataire.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_gestionnaire_can_reactivate_a_prestataire_account(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $prestataire = $this->userWithRole('prestataire', [
            'is_active' => false,
            'password' => 'Temporary-Password-42!',
            'must_change_password' => true,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('gestionnaire.prestataires.toggle-status', $prestataire))
            ->assertRedirect(route('gestionnaire.prestataires.show', $prestataire));

        $this->assertDatabaseHas('users', ['id' => $prestataire->id, 'is_active' => true]);
        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $prestataire->email,
            'password' => 'Temporary-Password-42!',
        ])->assertRedirect(route('prestataire.password.edit'));
    }

    public function test_prestataire_is_forbidden_from_all_account_management_routes(): void
    {
        $prestataire = $this->userWithRole('prestataire');
        $otherPrestataire = $this->userWithRole('prestataire', ['email' => 'other@example.test']);

        $this->actingAs($prestataire);

        $this->get(route('gestionnaire.prestataires.index'))->assertForbidden();
        $this->get(route('gestionnaire.prestataires.create'))->assertForbidden();
        $this->post(route('gestionnaire.prestataires.store'), [])->assertForbidden();
        $this->get(route('gestionnaire.prestataires.show', $otherPrestataire))->assertForbidden();
        $this->get(route('gestionnaire.prestataires.edit', $otherPrestataire))->assertForbidden();
        $this->put(route('gestionnaire.prestataires.update', $otherPrestataire), [])->assertForbidden();
        $this->patch(route('gestionnaire.prestataires.toggle-status', $otherPrestataire))->assertForbidden();
    }

    public function test_manager_cannot_manage_an_account_without_the_prestataire_role(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire');
        $otherManager = $this->userWithRole('gestionnaire', ['email' => 'other-manager@example.test']);

        $this->actingAs($gestionnaire)
            ->get(route('gestionnaire.prestataires.show', $otherManager))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = $this->role($roleName);
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);
        if ($roleName === 'gestionnaire') {
            $this->designatePrimaryManagerForLegacyTests($user);
        }

        return $user;
    }

    private function role(string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            ['description' => 'Role used by account feature tests.'],
        );
    }
}
