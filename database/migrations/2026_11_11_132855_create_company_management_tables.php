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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestataire_id')->constrained('users');
            $table->string('name');
            $table->string('activity')->nullable();
            $table->string('assignment')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['prestataire_id', 'status']);
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->string('status')->default('draft')->index();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'type']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('company_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained('company_documents')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['company_document_id', 'version']);
        });

        Schema::create('company_review_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('company_document_id')->nullable()->constrained('company_documents')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_document_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_review_histories');
        Schema::dropIfExists('company_document_versions');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('companies');
    }
};
