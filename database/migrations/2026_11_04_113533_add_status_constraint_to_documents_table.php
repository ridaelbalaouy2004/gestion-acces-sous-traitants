<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER documents_status_check_insert BEFORE INSERT ON documents FOR EACH ROW WHEN NEW.status NOT IN ('pending', 'validated', 'rejected') BEGIN SELECT RAISE(ABORT, 'Invalid document status'); END");
            DB::unprepared("CREATE TRIGGER documents_status_check_update BEFORE UPDATE OF status ON documents FOR EACH ROW WHEN NEW.status NOT IN ('pending', 'validated', 'rejected') BEGIN SELECT RAISE(ABORT, 'Invalid document status'); END");

            return;
        }

        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('pending', 'validated', 'rejected'))",
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS documents_status_check_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS documents_status_check_update');

            return;
        }

        DB::statement('ALTER TABLE documents DROP CHECK documents_status_check');
    }
};
