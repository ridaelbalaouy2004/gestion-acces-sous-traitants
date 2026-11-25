<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permission::CATALOG as $name => $attributes) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                $attributes,
            );
        }
    }
}
