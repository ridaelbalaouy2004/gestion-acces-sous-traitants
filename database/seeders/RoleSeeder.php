<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'prestataire' => 'External subcontractor submitting access documents.',
            'gestionnaire' => 'Manager reviewing subcontractor access documents.',
            'responsable_securite' => 'Safety lead reviewing access and safety information.',
        ] as $name => $description) {
            Role::updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
