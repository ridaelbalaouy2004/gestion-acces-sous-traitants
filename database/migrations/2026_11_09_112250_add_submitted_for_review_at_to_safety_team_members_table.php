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
            $table->timestamp('submitted_for_review_at')->nullable()->after('status');
            $table->index(
                ['status', 'submitted_for_review_at'],
                'safety_team_members_review_queue_idx',
            );
        });

        DB::table('safety_team_members')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($members): void {
                $memberIds = $members->pluck('id');
                $submissionDates = DB::table('safety_team_member_validations')
                    ->select('safety_team_member_id')
                    ->selectRaw('MAX(created_at) as submitted_at')
                    ->whereIn('safety_team_member_id', $memberIds)
                    ->whereIn('event_type', ['created', 'submitted', 'resubmitted'])
                    ->groupBy('safety_team_member_id')
                    ->pluck('submitted_at', 'safety_team_member_id');

                foreach ($members as $member) {
                    DB::table('safety_team_members')
                        ->where('id', $member->id)
                        ->update([
                            'submitted_for_review_at' => $submissionDates[$member->id] ?? $member->created_at,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safety_team_members', function (Blueprint $table) {
            $table->dropIndex('safety_team_members_review_queue_idx');
            $table->dropColumn('submitted_for_review_at');
        });
    }
};
