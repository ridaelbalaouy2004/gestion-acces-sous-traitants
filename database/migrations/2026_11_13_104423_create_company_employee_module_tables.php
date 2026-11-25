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
        $this->assertExistingTablesAreCompatible();

        $this->createCompanyEmployees();
        $this->createCompanyEmployeeDocuments();
        $this->createCompanyEmployeeDocumentVersions();
        $this->createCompanyEmployeeHistories();

        foreach ($this->indexes() as [$tableName, $columns, $indexName, $unique]) {
            $this->ensureIndex($tableName, $columns, $indexName, $unique);
        }

        foreach ($this->foreignKeys() as [$tableName, $column, $referencedTable, $constraintName, $deleteAction]) {
            $this->ensureForeignKey($tableName, $column, $referencedTable, $constraintName, $deleteAction);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_employee_histories');
        Schema::dropIfExists('company_employee_document_versions');
        Schema::dropIfExists('company_employee_documents');
        Schema::dropIfExists('company_employees');
    }

    private function createCompanyEmployees(): void
    {
        if (Schema::hasTable('company_employees')) {
            return;
        }

        Schema::create('company_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained(indexName: 'cem_company_fk')
                ->restrictOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('cin', 50)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('locker_number', 50)->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()
                ->constrained(table: 'users', indexName: 'cem_validated_user_fk')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'cin'], 'company_employees_company_cin_unique');
            $table->index(['company_id', 'status'], 'company_employees_company_status_index');
            $table->index(['status', 'submitted_at'], 'company_employees_status_submitted_index');
            $table->index(['first_name', 'last_name'], 'company_employees_name_index');
            $table->index('validated_by', 'cem_validated_user_idx');
        });
    }

    private function createCompanyEmployeeDocuments(): void
    {
        if (Schema::hasTable('company_employee_documents')) {
            return;
        }

        Schema::create('company_employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_employee_id')
                ->constrained(indexName: 'ced_employee_fk')
                ->restrictOnDelete();
            $table->string('type', 32);
            $table->string('label', 100);
            $table->string('status', 32)->default('draft');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->date('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('validated_by')->nullable()
                ->constrained(table: 'users', indexName: 'ced_validated_user_fk')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_employee_id', 'type'], 'company_employee_documents_employee_type_unique');
            $table->index(['company_employee_id', 'status'], 'company_employee_documents_employee_status_index');
            $table->index('validated_by', 'ced_validated_user_idx');
        });
    }

    private function createCompanyEmployeeDocumentVersions(): void
    {
        if (Schema::hasTable('company_employee_document_versions')) {
            return;
        }

        Schema::create('company_employee_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_employee_document_id')
                ->constrained(indexName: 'cedv_document_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->nullable()
                ->constrained(table: 'users', indexName: 'cedv_uploaded_user_fk')
                ->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['company_employee_document_id', 'version'], 'company_employee_versions_document_version_unique');
            $table->index(['company_employee_document_id', 'is_current'], 'company_employee_versions_document_current_index');
            $table->index('uploaded_by', 'cedv_uploaded_user_idx');
        });
    }

    private function createCompanyEmployeeHistories(): void
    {
        if (Schema::hasTable('company_employee_histories')) {
            return;
        }

        Schema::create('company_employee_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_employee_id')
                ->constrained(indexName: 'ceh_employee_fk')
                ->restrictOnDelete();
            $table->foreignId('company_employee_document_id')->nullable()
                ->constrained(indexName: 'ceh_document_fk')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained(table: 'users', indexName: 'ceh_user_fk')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_employee_id', 'created_at'], 'company_employee_history_employee_created_index');
            $table->index(['company_employee_document_id', 'created_at'], 'company_employee_history_document_created_index');
            $table->index('user_id', 'ceh_user_idx');
        });
    }

    private function assertExistingTablesAreCompatible(): void
    {
        $requiredColumns = [
            'company_employees' => [
                'id', 'company_id', 'first_name', 'last_name', 'cin', 'position', 'locker_number',
                'status', 'rejection_reason', 'validated_by', 'validated_at', 'submitted_at', 'created_at', 'updated_at',
            ],
            'company_employee_documents' => [
                'id', 'company_employee_id', 'type', 'label', 'status', 'file_name', 'file_path', 'mime_type',
                'expires_at', 'rejection_reason', 'validated_by', 'validated_at', 'submitted_at', 'created_at', 'updated_at',
            ],
            'company_employee_document_versions' => [
                'id', 'company_employee_document_id', 'version', 'file_name', 'file_path', 'mime_type',
                'uploaded_by', 'uploaded_at', 'notes', 'is_current', 'created_at', 'updated_at',
            ],
            'company_employee_histories' => [
                'id', 'company_employee_id', 'company_employee_document_id', 'user_id', 'action',
                'from_status', 'to_status', 'reason', 'metadata', 'created_at', 'updated_at',
            ],
        ];

        foreach ($requiredColumns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $missingColumns = array_values(array_filter(
                $columns,
                fn (string $column): bool => ! Schema::hasColumn($tableName, $column),
            ));

            if ($missingColumns !== []) {
                throw new RuntimeException(sprintf(
                    'Cannot safely resume %s: existing table %s is missing columns: %s.',
                    self::class,
                    $tableName,
                    implode(', ', $missingColumns),
                ));
            }
        }
    }

    /**
     * @return array<int, array{string, array<int, string>, string, bool}>
     */
    private function indexes(): array
    {
        return [
            ['company_employees', ['company_id', 'cin'], 'company_employees_company_cin_unique', true],
            ['company_employees', ['company_id', 'status'], 'company_employees_company_status_index', false],
            ['company_employees', ['status', 'submitted_at'], 'company_employees_status_submitted_index', false],
            ['company_employees', ['first_name', 'last_name'], 'company_employees_name_index', false],
            ['company_employees', ['validated_by'], 'cem_validated_user_idx', false],
            ['company_employee_documents', ['company_employee_id', 'type'], 'company_employee_documents_employee_type_unique', true],
            ['company_employee_documents', ['company_employee_id', 'status'], 'company_employee_documents_employee_status_index', false],
            ['company_employee_documents', ['validated_by'], 'ced_validated_user_idx', false],
            ['company_employee_document_versions', ['company_employee_document_id', 'version'], 'company_employee_versions_document_version_unique', true],
            ['company_employee_document_versions', ['company_employee_document_id', 'is_current'], 'company_employee_versions_document_current_index', false],
            ['company_employee_document_versions', ['uploaded_by'], 'cedv_uploaded_user_idx', false],
            ['company_employee_histories', ['company_employee_id', 'created_at'], 'company_employee_history_employee_created_index', false],
            ['company_employee_histories', ['company_employee_document_id', 'created_at'], 'company_employee_history_document_created_index', false],
            ['company_employee_histories', ['user_id'], 'ceh_user_idx', false],
        ];
    }

    /**
     * @return array<int, array{string, string, string, string, string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['company_employees', 'company_id', 'companies', 'cem_company_fk', 'restrict'],
            ['company_employees', 'validated_by', 'users', 'cem_validated_user_fk', 'set null'],
            ['company_employee_documents', 'company_employee_id', 'company_employees', 'ced_employee_fk', 'restrict'],
            ['company_employee_documents', 'validated_by', 'users', 'ced_validated_user_fk', 'set null'],
            ['company_employee_document_versions', 'company_employee_document_id', 'company_employee_documents', 'cedv_document_fk', 'restrict'],
            ['company_employee_document_versions', 'uploaded_by', 'users', 'cedv_uploaded_user_fk', 'set null'],
            ['company_employee_histories', 'company_employee_id', 'company_employees', 'ceh_employee_fk', 'restrict'],
            ['company_employee_histories', 'company_employee_document_id', 'company_employee_documents', 'ceh_document_fk', 'restrict'],
            ['company_employee_histories', 'user_id', 'users', 'ceh_user_fk', 'set null'],
        ];
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureIndex(string $tableName, array $columns, string $indexName, bool $unique): void
    {
        $indexes = Schema::getIndexes($tableName);

        foreach ($indexes as $index) {
            if ($index['columns'] === $columns && $index['unique'] === $unique) {
                return;
            }

            if ($index['name'] === $indexName) {
                throw new RuntimeException(sprintf(
                    'Cannot safely resume migration: index name %s already exists on %s with a different definition.',
                    $indexName,
                    $tableName,
                ));
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName, $unique): void {
            if ($unique) {
                $table->unique($columns, $indexName);

                return;
            }

            $table->index($columns, $indexName);
        });
    }

    private function ensureForeignKey(
        string $tableName,
        string $column,
        string $referencedTable,
        string $constraintName,
        string $deleteAction,
    ): void {
        $foreignKeys = Schema::getForeignKeys($tableName);

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['name'] === $constraintName
                && ($foreignKey['columns'] !== [$column]
                    || $foreignKey['foreign_table'] !== $referencedTable
                    || $foreignKey['foreign_columns'] !== ['id'])) {
                throw new RuntimeException(sprintf(
                    'Cannot safely resume migration: foreign key name %s is already used by another relationship on %s.',
                    $constraintName,
                    $tableName,
                ));
            }

            if ($foreignKey['columns'] !== [$column]) {
                continue;
            }

            if ($foreignKey['foreign_table'] !== $referencedTable
                || $foreignKey['foreign_columns'] !== ['id']
                || strtolower((string) $foreignKey['on_delete']) !== $deleteAction) {
                throw new RuntimeException(sprintf(
                    'Cannot safely resume migration: existing foreign key on %s.%s does not match the expected relationship.',
                    $tableName,
                    $column,
                ));
            }

            return;
        }

        Schema::table($tableName, function (Blueprint $table) use (
            $column,
            $referencedTable,
            $constraintName,
            $deleteAction,
        ): void {
            $foreignKey = $table->foreign($column, $constraintName)
                ->references('id')
                ->on($referencedTable);

            if ($deleteAction === 'set null') {
                $foreignKey->nullOnDelete();

                return;
            }

            $foreignKey->restrictOnDelete();
        });
    }
};
