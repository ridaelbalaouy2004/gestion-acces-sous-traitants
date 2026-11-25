<?php

namespace Tests\Feature;

use App\Models\ManagerSettings;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('profileRoutesByRole')]
    public function test_profile_page_is_accessible_for_each_role(string $roleName, string $routeName): void
    {
        $user = $this->userWithRole($roleName);

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Informations personnelles')
            ->assertSee('Changer le mot de passe');
    }

    #[DataProvider('profileRoutesByRole')]
    public function test_profile_update_allows_own_personal_information_only(string $roleName, string $routeName): void
    {
        $user = $this->userWithRole($roleName, [
            'name' => 'Nom initial',
            'email' => 'initial@example.test',
        ]);

        $this->actingAs($user)
            ->put(route(str_replace('.profile', '.profile.update', $routeName)), [
                'name' => 'Nom modifié',
                'email' => 'modifie@example.test',
                'role' => 'prestataire',
                'is_active' => false,
                'must_change_password' => true,
            ])
            ->assertRedirect(route($routeName))
            ->assertSessionHas('status', 'Votre profil a été mis à jour.');

        $user->refresh();
        $this->assertSame('Nom modifié', $user->name);
        $this->assertSame('modifie@example.test', $user->email);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue($user->is_active);
    }

    #[DataProvider('profileRoutesByRole')]
    public function test_profile_updates_reject_duplicate_email(string $roleName, string $routeName): void
    {
        $user = $this->userWithRole($roleName, ['email' => 'owner@example.test']);
        $otherUser = $this->userWithRole($roleName, ['email' => 'taken@example.test']);

        $this->actingAs($user)
            ->from(route($routeName))
            ->put(route(str_replace('.profile', '.profile.update', $routeName)), [
                'name' => 'Nouveau nom',
                'email' => $otherUser->email,
            ])
            ->assertSessionHasErrors('email');
    }

    #[DataProvider('profileRoutesByRole')]
    public function test_profile_password_change_requires_current_password(string $roleName, string $routeName): void
    {
        $user = $this->userWithRole($roleName, [
            'password' => Hash::make('CurrentPassword123!'),
        ]);

        $this->actingAs($user)
            ->from(route($routeName))
            ->put(route(str_replace('.profile', '.profile.password', $routeName)), [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->put(route(str_replace('.profile', '.profile.password', $routeName)), [
                'current_password' => 'CurrentPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route($routeName))
            ->assertSessionHas('status', 'Votre mot de passe a été modifié avec succès.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public static function profileRoutesByRole(): array
    {
        return [
            'prestataire profile' => ['prestataire', 'prestataire.profile'],
            'gestionnaire profile' => ['gestionnaire', 'gestionnaire.profile'],
            'security profile' => ['responsable_securite', 'responsable-securite.profile'],
        ];
    }

    protected function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['description' => 'Role used by profile tests.'],
        );

        $user = User::factory()->create([
            'name' => 'User '.$roleName,
            'email' => strtolower($roleName).'@example.test',
            'password' => Hash::make('Password123!'),
            ...$attributes,
        ]);

        $user->roles()->syncWithoutDetaching($role);

        if ($roleName === 'gestionnaire') {
            $this->designatePrimaryManagerForLegacyTests($user);
        }

        return $user;
    }

    protected function designatePrimaryManagerForLegacyTests(User $user): void
    {
        $settings = ManagerSettings::query()->firstOrNew(['id' => ManagerSettings::SINGLETON_ID]);
        $settings->principal_user_id = $user->getKey();
        $settings->save();
    }
}
