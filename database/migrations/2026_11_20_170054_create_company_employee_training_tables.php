<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_employee_trainings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_employee_id')
                ->constrained(table: 'company_employees', indexName: 'cetr_employee_fk')
                ->restrictOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->date('training_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('description')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_employee_training_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_employee_training_id')
                ->constrained(table: 'company_employee_trainings', indexName: 'cetrv_training_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->nullable()
                ->constrained(table: 'users', indexName: 'cetrv_uploaded_user_fk')
                ->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(
                ['company_employee_training_id', 'version'],
                'cetrv_training_version_unique',
            );
            $table->index(
                ['company_employee_training_id', 'is_current'],
                'cetrv_training_current_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_employee_training_versions');
        Schema::dropIfExists('company_employee_trainings');
    }
};
