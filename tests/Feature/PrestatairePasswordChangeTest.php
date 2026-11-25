<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrestatairePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestataire_is_redirected_to_password_change_after_first_login(): void
    {
        $prestataire = $this->prestataireWithTemporaryPassword();

        $this->post(route('login.store'), [
            'email' => $prestataire->email,
            'password' => 'Temporary-Password-42!',
        ])
            ->assertRedirect(route('prestataire.password.edit'));

        $this->assertAuthenticatedAs($prestataire);
    }

    public function test_prestataire_cannot_open_protected_pages_before_changing_password(): void
    {
        $prestataire = $this->prestataireWithTemporaryPassword();

        $this->actingAs($prestataire)
            ->get(route('prestataire.dashboard'))
            ->assertRedirect(route('prestataire.password.edit'));

        $this->get(route('notifications.index'))
            ->assertRedirect(route('prestataire.password.edit'));
    }

    public function test_successful_password_change_allows_prestataire_to_continue(): void
    {
        $prestataire = $this->prestataireWithTemporaryPassword();
        $newPassword = 'My-New-Strong-Password-72!';

        $response = $this->actingAs($prestataire)
            ->put(route('prestataire.password.update'), [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertRedirect(route('prestataire.dashboard'))
            ->assertSessionHas('status', 'Votre mot de passe a été modifié avec succès.');
        $this->assertDatabaseHas('users', [
            'id' => $prestataire->id,
            'must_change_password' => false,
        ]);
        $this->assertTrue(Hash::check($newPassword, $prestataire->fresh()->password));
        $this->get(route('prestataire.dashboard'))->assertOk();
    }

    public function test_temporary_password_cannot_be_reused_as_the_new_password(): void
    {
        $prestataire = $this->prestataireWithTemporaryPassword();

        $this->actingAs($prestataire)
            ->put(route('prestataire.password.update'), [
                'password' => 'Temporary-Password-42!',
                'password_confirmation' => 'Temporary-Password-42!',
            ])
            ->assertSessionHasErrors([
                'password' => 'Le nouveau mot de passe doit être différent du mot de passe temporaire.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $prestataire->id,
            'must_change_password' => true,
        ]);
    }

    public function test_short_new_password_is_rejected(): void
    {
        $prestataire = $this->prestataireWithTemporaryPassword();

        $this->actingAs($prestataire)
            ->put(route('prestataire.password.update'), [
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors([
                'password' => 'Le mot de passe doit contenir au moins 12 caractères.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $prestataire->id,
            'must_change_password' => true,
        ]);
    }

    public function test_gestionnaire_is_forced_to_change_a_temporary_password(): void
    {
        $gestionnaire = $this->userWithRole('gestionnaire', [
            'password' => 'Temporary-Password-42!',
            'must_change_password' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $gestionnaire->email,
            'password' => 'Temporary-Password-42!',
        ])
            ->assertRedirect(route('gestionnaire.dashboard'));

        $this->get(route('gestionnaire.dashboard'))
            ->assertRedirect(route('gestionnaire.password.edit'));
        $this->get(route('gestionnaire.password.edit'))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by password-change feature tests.'],
        );
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);
        if ($roleName === 'gestionnaire') {
            $this->designatePrimaryManagerForLegacyTests($user);
        }

        return $user;
    }

    private function prestataireWithTemporaryPassword(): User
    {
        return $this->userWithRole('prestataire', [
            'email' => 'first-login@example.test',
            'password' => 'Temporary-Password-42!',
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }
}
