<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyEmployeeMigrationRecoveryTest extends TestCase
{
    public function test_rerunning_migration_completes_partial_schema_without_replacing_existing_records(): void
    {
        $connectionName = 'company_employee_recovery_test';
        $previousConnection = DB::getDefaultConnection();
        config([
            "database.connections.{$connectionName}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge($connectionName);
        DB::setDefaultConnection($connectionName);

        try {
            Schema::create('companies', fn (Blueprint $table) => $table->id());
            Schema::create('users', fn (Blueprint $table) => $table->id());
            $this->createExistingEmployeeTables();
            $this->createPartialVersionsTable();
            $this->insertExistingRecords();

            $migration = require database_path('migrations/2026_11_13_104423_create_company_employee_module_tables.php');
            $migration->up();

            $this->assertSame(1, DB::table('company_employees')->count());
            $this->assertSame(1, DB::table('company_employee_documents')->count());
            $this->assertSame(1, DB::table('company_employee_document_versions')->count());
            $this->assertSame(
                'private/retained.pdf',
                DB::table('company_employee_document_versions')->value('file_path'),
            );
            $this->assertTrue(Schema::hasTable('company_employee_histories'));
            $this->assertTrue(Schema::hasIndex(
                'company_employee_document_versions',
                ['company_employee_document_id', 'version'],
                'unique',
            ));
            $this->assertTrue(Schema::hasIndex(
                'company_employee_document_versions',
                ['uploaded_by'],
            ));
            $this->assertSame(9, $this->foreignKeyCount());

            $migration->up();

            $this->assertSame(9, $this->foreignKeyCount());
            $this->assertSame(1, DB::table('company_employee_document_versions')->count());
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge($connectionName);
        }
    }

    private function createExistingEmployeeTables(): void
    {
        Schema::create('company_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('cin', 50)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('locker_number', 50)->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'cin'], 'company_employees_company_cin_unique');
            $table->index(['company_id', 'status'], 'company_employees_company_status_index');
            $table->index(['status', 'submitted_at'], 'company_employees_status_submitted_index');
            $table->index(['first_name', 'last_name'], 'company_employees_name_index');
            $table->index('validated_by', 'company_employees_validated_by_foreign');
        });

        Schema::create('company_employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('label', 100);
            $table->string('status', 32)->default('draft');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_employee_id', 'type'], 'company_employee_documents_employee_type_unique');
            $table->index(['company_employee_id', 'status'], 'company_employee_documents_employee_status_index');
            $table->index('validated_by', 'company_employee_documents_validated_by_foreign');
        });
    }

    private function createPartialVersionsTable(): void
    {
        Schema::create('company_employee_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_employee_document_id');
            $table->unsignedInteger('version');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    private function insertExistingRecords(): void
    {
        DB::table('companies')->insert(['id' => 1]);
        DB::table('users')->insert(['id' => 1]);
        DB::table('company_employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'first_name' => 'Existing',
            'last_name' => 'Employee',
            'cin' => 'AB12345',
            'position' => 'Technician',
            'status' => 'draft',
        ]);
        DB::table('company_employee_documents')->insert([
            'id' => 1,
            'company_employee_id' => 1,
            'type' => 'cin',
            'label' => 'CIN',
            'status' => 'pending',
        ]);
        DB::table('company_employee_document_versions')->insert([
            'id' => 1,
            'company_employee_document_id' => 1,
            'version' => 1,
            'file_name' => 'retained.pdf',
            'file_path' => 'private/retained.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => 1,
            'uploaded_at' => '2026-11-13 10:00:00',
            'is_current' => true,
        ]);
    }

    private function foreignKeyCount(): int
    {
        return collect([
            'company_employees',
            'company_employee_documents',
            'company_employee_document_versions',
            'company_employee_histories',
        ])->sum(fn (string $tableName): int => count(Schema::getForeignKeys($tableName)));
    }
}
