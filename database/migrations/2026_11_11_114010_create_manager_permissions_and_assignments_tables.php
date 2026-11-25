<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('module');
                $table->string('label');
                $table->timestamps();

                $table->index(['module', 'name']);
            });
        }

        if (! Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table): void {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['permission_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('manager_settings')) {
            Schema::create('manager_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->foreignId('principal_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('manager_prestataire_assignments')) {
            Schema::create('manager_prestataire_assignments', function (Blueprint $table): void {
                $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('prestataire_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->primary(['manager_id', 'prestataire_id']);
                $table->index(['prestataire_id', 'manager_id']);
            });
        }

        if (! Schema::hasTable('manager_prestataire_assignment_history')) {
            Schema::create('manager_prestataire_assignment_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('prestataire_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->string('action', 16);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['manager_id', 'created_at']);
                $table->index(['prestataire_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('manager_principal_transfers')) {
            Schema::create('manager_principal_transfers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('previous_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('new_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['new_user_id', 'created_at']);
            });
        }

        DB::table('manager_settings')->insertOrIgnore([
            'id' => 1,
            'principal_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_principal_transfers');
        Schema::dropIfExists('manager_prestataire_assignment_history');
        Schema::dropIfExists('manager_prestataire_assignments');
        Schema::dropIfExists('manager_settings');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
    }
};
