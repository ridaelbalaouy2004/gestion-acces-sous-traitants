<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_machinery', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained(indexName: 'cm_company_fk')->restrictOnDelete();
            $table->string('designation', 150);
            $table->string('category', 100);
            $table->string('assignment')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->date('commissioned_at')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()
                ->constrained(table: 'users', indexName: 'cm_validated_user_fk')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'cm_company_status_idx');
            $table->index(['status', 'submitted_at'], 'cm_status_submitted_idx');
            $table->index('serial_number', 'cm_serial_idx');
            $table->index('validated_by', 'cm_validated_user_idx');
        });

        Schema::create('company_machinery_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_machinery_id')
                ->constrained(table: 'company_machinery', indexName: 'cmd_machinery_fk')
                ->restrictOnDelete();
            $table->string('type', 32);
            $table->string('label', 100);
            $table->string('status', 32)->default('draft');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()
                ->constrained(table: 'users', indexName: 'cmd_validated_user_fk')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_machinery_id', 'type'], 'cmd_machinery_type_uq');
            $table->index(['company_machinery_id', 'status'], 'cmd_machinery_status_idx');
            $table->index('validated_by', 'cmd_validated_user_idx');
        });

        Schema::create('company_machinery_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_machinery_document_id')->constrained(indexName: 'cmdv_document_fk')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->nullable()
                ->constrained(table: 'users', indexName: 'cmdv_uploaded_user_fk')
                ->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['company_machinery_document_id', 'version'], 'cmdv_document_version_uq');
            $table->index(['company_machinery_document_id', 'is_current'], 'cmdv_document_current_idx');
            $table->index('uploaded_by', 'cmdv_uploaded_user_idx');
        });

        Schema::create('company_machinery_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_machinery_id')
                ->constrained(table: 'company_machinery', indexName: 'cmh_machinery_fk')
                ->restrictOnDelete();
            $table->foreignId('company_machinery_document_id')->nullable()
                ->constrained(indexName: 'cmh_document_fk')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained(table: 'users', indexName: 'cmh_user_fk')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_machinery_id', 'created_at'], 'cmh_machinery_created_idx');
            $table->index(['company_machinery_document_id', 'created_at'], 'cmh_document_created_idx');
            $table->index('user_id', 'cmh_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_machinery_histories');
        Schema::dropIfExists('company_machinery_document_versions');
        Schema::dropIfExists('company_machinery_documents');
        Schema::dropIfExists('company_machinery');
    }
};
