<?php

namespace App\Console\Commands;

use App\Models\ManagerSettings;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:designate-primary-manager {email : Adresse e-mail du compte Gestionnaire existant}')]
#[Description('Désigne explicitement le Gestionnaire Principal initial')]
class DesignatePrimaryManager extends Command
{
    public function handle(): int
    {
        $manager = User::query()
            ->where('email', $this->argument('email'))
            ->where(fn ($query) => $query
                ->whereNull('is_active')
                ->orWhere('is_active', true))
            ->whereHas('roles', fn ($query) => $query->where('name', 'gestionnaire'))
            ->first();

        if ($manager === null) {
            $this->error('Aucun compte Gestionnaire actif ne correspond à cette adresse e-mail.');

            return self::FAILURE;
        }

        if (! $this->confirm("Désigner {$manager->name} ({$manager->email}) comme Gestionnaire Principal ?")) {
            $this->warn('Désignation annulée.');

            return self::FAILURE;
        }

        $designated = DB::transaction(function () use ($manager): bool {
            $settings = ManagerSettings::query()
                ->whereKey(ManagerSettings::SINGLETON_ID)
                ->lockForUpdate()
                ->firstOrFail();

            if ($settings->principal_user_id !== null) {
                return false;
            }

            $settings->principal_user_id = $manager->getKey();
            $settings->save();

            DB::table('manager_principal_transfers')->insert([
                'previous_user_id' => null,
                'new_user_id' => $manager->getKey(),
                'actor_id' => $manager->getKey(),
                'created_at' => now(),
            ]);

            return true;
        });

        if (! $designated) {
            $this->error('Un Gestionnaire Principal est déjà désigné. Utilisez la procédure de transfert dans l’application.');

            return self::FAILURE;
        }

        $this->info('Le Gestionnaire Principal initial a été désigné.');

        return self::SUCCESS;
    }
}
