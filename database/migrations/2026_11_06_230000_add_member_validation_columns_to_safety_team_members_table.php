<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('safety_team_members', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('phone')->index();
            $table->foreignId('validated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->text('rejection_reason')->nullable()->after('validated_at');
        });

        DB::table('safety_team_members')->update([
            'status' => 'pending',
            'validated_by' => null,
            'validated_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safety_team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['status', 'validated_at', 'rejection_reason']);
        });
    }
};
