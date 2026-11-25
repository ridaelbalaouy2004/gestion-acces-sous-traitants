<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(
                <<<'SQL'
                    UPDATE document_revisions
                    SET resolved_at = (
                            SELECT documents.validated_at
                            FROM documents
                            WHERE documents.id = document_revisions.document_id
                        ),
                        resolved_by = (
                            SELECT documents.validated_by
                            FROM documents
                            WHERE documents.id = document_revisions.document_id
                        )
                    WHERE resolved_at IS NULL
                        AND EXISTS (
                            SELECT 1
                            FROM documents
                            WHERE documents.id = document_revisions.document_id
                                AND documents.status = ?
                                AND documents.validated_at IS NOT NULL
                        )
                    SQL,
                ['validated'],
            );

            return;
        }

        DB::table('document_revisions')
            ->join('documents', 'documents.id', '=', 'document_revisions.document_id')
            ->where('documents.status', 'validated')
            ->whereNotNull('documents.validated_at')
            ->whereNull('document_revisions.resolved_at')
            ->update([
                'document_revisions.resolved_at' => DB::raw('documents.validated_at'),
                'document_revisions.resolved_by' => DB::raw('documents.validated_by'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep the backfilled resolution data to preserve the revision audit history.
    }
};
