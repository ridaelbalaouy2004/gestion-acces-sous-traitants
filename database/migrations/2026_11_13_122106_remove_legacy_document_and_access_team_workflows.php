<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REMOVED_PERMISSIONS = [
        'documents.view' => ['module' => 'documents', 'label' => 'Consulter les documents'],
        'documents.update' => ['module' => 'documents', 'label' => 'Modifier les documents'],
        'documents.approve' => ['module' => 'documents', 'label' => 'Valider les documents'],
        'documents.reject' => ['module' => 'documents', 'label' => 'Rejeter les documents'],
        'safety_team.view' => ['module' => 'safety_team', 'label' => 'Consulter l’équipe de sécurité'],
        'safety_team.update' => ['module' => 'safety_team', 'label' => 'Modifier l’équipe de sécurité'],
        'safety_team.approve' => ['module' => 'safety_team', 'label' => 'Valider les membres'],
        'safety_team.reject' => ['module' => 'safety_team', 'label' => 'Rejeter les membres'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('safety_team_member_validations');

        $columnsToDrop = array_values(array_filter(
            ['submitted_for_review_at', 'validated_by', 'validated_at', 'rejection_reason'],
            fn (string $column): bool => Schema::hasColumn('safety_team_members', $column),
        ));
        $hasValidatedByForeignKey = Schema::hasForeignKey(
            'safety_team_members',
            'safety_team_members_validated_by_foreign',
        ) || Schema::hasForeignKey('safety_team_members', ['validated_by']);
        $hasReviewQueueIndex = Schema::hasIndex(
            'safety_team_members',
            'safety_team_members_review_queue_idx',
        );

        if ($hasValidatedByForeignKey || $hasReviewQueueIndex || $columnsToDrop !== []) {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                if ($hasValidatedByForeignKey) {
                    Schema::table('safety_team_members', function (Blueprint $table): void {
                        $table->dropForeign(['validated_by']);
                    });
                }

                if ($hasReviewQueueIndex && Schema::hasIndex(
                    'safety_team_members',
                    'safety_team_members_review_queue_idx',
                )) {
                    Schema::table('safety_team_members', function (Blueprint $table): void {
                        $table->dropIndex('safety_team_members_review_queue_idx');
                    });
                }

                if ($columnsToDrop !== []) {
                    Schema::table('safety_team_members', function (Blueprint $table) use ($columnsToDrop): void {
                        $table->dropColumn($columnsToDrop);
                    });
                }
            } else {
                Schema::table('safety_team_members', function (Blueprint $table) use (
                    $hasValidatedByForeignKey,
                    $hasReviewQueueIndex,
                    $columnsToDrop,
                ): void {
                    if ($hasValidatedByForeignKey) {
                        $table->dropForeign('safety_team_members_validated_by_foreign');
                    }

                    if ($hasReviewQueueIndex) {
                        $table->dropIndex('safety_team_members_review_queue_idx');
                    }

                    if ($columnsToDrop !== []) {
                        $table->dropColumn($columnsToDrop);
                    }
                });
            }
        }

        $securityRecipients = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'responsable_securite')
            ->select('role_user.user_id');

        DB::table('notifications')
            ->where('type', 'App\\Notifications\\DocumentWorkflowNotification')
            ->where('notifiable_type', 'App\\Models\\User')
            ->whereNotIn('notifiable_id', $securityRecipients)
            ->delete();

        DB::table('permissions')
            ->whereIn('name', array_keys(self::REMOVED_PERMISSIONS))
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safety_team_members', function (Blueprint $table): void {
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->index(
                ['status', 'submitted_for_review_at'],
                'safety_team_members_review_queue_idx',
            );
        });

        Schema::create('document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->text('description');
            $table->string('member_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['document_id', 'created_at']);
            $table->timestamps();
        });

        Schema::create('safety_team_member_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('safety_team_member_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['safety_team_member_id', 'created_at'], 'member_validations_timeline_idx');
        });

        foreach (self::REMOVED_PERMISSIONS as $name => $attributes) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                ...$attributes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
