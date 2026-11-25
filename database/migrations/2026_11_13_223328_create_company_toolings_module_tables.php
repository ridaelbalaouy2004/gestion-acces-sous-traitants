<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_toolings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained(indexName: 'ct_company_fk')->restrictOnDelete();
            $table->string('designation', 150);
            $table->string('category', 100);
            $table->string('internal_reference', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('physical_condition', 32)->nullable();
            $table->date('acquired_at')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('observations')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()
                ->constrained(table: 'users', indexName: 'ct_validated_user_fk')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'ct_company_status_idx');
            $table->index(['status', 'submitted_at'], 'ct_status_submitted_idx');
            $table->index('category', 'ct_category_idx');
            $table->index('serial_number', 'ct_serial_idx');
        });

        Schema::create('company_tooling_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_tooling_id')
                ->constrained(table: 'company_toolings', indexName: 'ctd_tooling_fk')
                ->restrictOnDelete();
            $table->string('type', 64);
            $table->string('label', 100);
            $table->string('status', 32)->default('draft');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()
                ->constrained(table: 'users', indexName: 'ctd_validated_user_fk')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_tooling_id', 'type'], 'ctd_tooling_type_uq');
            $table->index(['company_tooling_id', 'status'], 'ctd_tooling_status_idx');
        });

        Schema::create('company_tooling_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_tooling_document_id')
                ->constrained(table: 'company_tooling_documents', indexName: 'ctdv_document_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->nullable()
                ->constrained(table: 'users', indexName: 'ctdv_uploaded_user_fk')
                ->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['company_tooling_document_id', 'version'], 'ctdv_document_version_uq');
            $table->index(['company_tooling_document_id', 'is_current'], 'ctdv_document_current_idx');
        });

        Schema::create('company_tooling_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_tooling_id')
                ->constrained(table: 'company_toolings', indexName: 'cth_tooling_fk')
                ->restrictOnDelete();
            $table->foreignId('company_tooling_document_id')->nullable()
                ->constrained(table: 'company_tooling_documents', indexName: 'cth_document_fk')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained(table: 'users', indexName: 'cth_user_fk')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_tooling_id', 'created_at'], 'cth_tooling_created_idx');
            $table->index(['company_tooling_document_id', 'created_at'], 'cth_document_created_idx');
            $table->index('user_id', 'cth_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_tooling_histories');
        Schema::dropIfExists('company_tooling_document_versions');
        Schema::dropIfExists('company_tooling_documents');
        Schema::dropIfExists('company_toolings');
    }
};
