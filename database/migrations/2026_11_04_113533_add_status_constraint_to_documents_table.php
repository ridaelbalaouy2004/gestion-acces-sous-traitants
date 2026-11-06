<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'validated', 'rejected'))",
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documents DROP CHECK documents_status_check');
    }
};
