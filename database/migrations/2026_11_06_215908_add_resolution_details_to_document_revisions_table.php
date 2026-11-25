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
        Schema::table('document_revisions', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('reason');
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('resolved_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_revisions', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['resolved_by', 'resolved_at']);
        });
    }
};
