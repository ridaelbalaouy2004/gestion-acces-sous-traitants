<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    private const DEVELOPMENT_PASSWORD = 'LocalDev!Access2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'development'])) {
            return;
        }

        foreach ([
            'prestataire@example.com' => ['Prestataire Demo', 'prestataire'],
            'gestionnaire@example.com' => ['Gestionnaire Demo', 'gestionnaire'],
            'securite@example.com' => ['Responsable Sécurité Demo', 'responsable_securite'],
        ] as $email => [$name, $roleName]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::DEVELOPMENT_PASSWORD),
                ],
            );
            $role = Role::where('name', $roleName)->firstOrFail();

            $user->roles()->sync([$role->id]);
        }
    }
}
