<?php

namespace Tests\Feature;

use App\Models\ManagerSettings;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignatePrimaryManagerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_designates_only_the_explicitly_confirmed_active_manager(): void
    {
        $this->seed(RoleSeeder::class);
        $manager = User::factory()->create(['name' => 'Manager désigné']);
        $manager->roles()->attach(Role::query()->where('name', 'gestionnaire')->firstOrFail());

        $this->assertNull(ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->value('principal_user_id'));

        $this->artisan('app:designate-primary-manager', ['email' => $manager->email])
            ->expectsConfirmation("Désigner {$manager->name} ({$manager->email}) comme Gestionnaire Principal ?", 'yes')
            ->assertExitCode(0);

        $this->assertTrue($manager->fresh()->isPrimaryManager());
    }

    public function test_declining_designation_leaves_the_primary_manager_unset(): void
    {
        $this->seed(RoleSeeder::class);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::query()->where('name', 'gestionnaire')->firstOrFail());

        $this->artisan('app:designate-primary-manager', ['email' => $manager->email])
            ->expectsConfirmation("Désigner {$manager->name} ({$manager->email}) comme Gestionnaire Principal ?", 'no')
            ->assertExitCode(1);

        $this->assertNull(ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->value('principal_user_id'));
    }
}
