<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_json_roles_and_safety_team_relationships_work(): void
    {
        $prestataire = User::factory()->create();
        $validator = User::factory()->create();
        $role = Role::create([
            'name' => 'database-test-role',
            'description' => 'Role used to verify Eloquent relationships.',
        ]);
        $prestataire->roles()->attach($role);

        $customFields = [
            'CIN Number' => 'AB1234',
            'Expiration Date' => '2027-12-31',
            'Company Name' => 'ABC Company',
        ];
        $document = Document::create([
            'prestataire_id' => $prestataire->id,
            'title' => 'Access request',
            'status' => Document::STATUS_PENDING,
            'custom_fields' => $customFields,
            'validated_by' => $validator->id,
        ]);
        $member = $document->safetyTeamMembers()->create([
            'name' => 'Safety Contact',
            'cin' => 'CD5678',
            'position' => 'Supervisor',
        ]);

        $document->refresh();

        $this->assertSame($customFields, $document->custom_fields);
        $this->assertTrue($document->prestataire->is($prestataire));
        $this->assertTrue($document->validatedBy->is($validator));
        $this->assertTrue($document->safetyTeamMembers->sole()->is($member));
        $this->assertTrue($member->document->is($document));
        $this->assertSame($role->id, $prestataire->roles->sole()->id);
        $this->assertTrue($role->users->sole()->is($prestataire));
    }

    public function test_document_status_is_limited_to_the_allowed_values(): void
    {
        $prestataire = User::factory()->create();

        $this->expectException(QueryException::class);

        Document::create([
            'prestataire_id' => $prestataire->id,
            'title' => 'Invalid status request',
            'status' => 'approved',
        ]);
    }

    public function test_security_document_notifications_are_stored_with_uuid_identifiers(): void
    {
        $recipient = User::factory()->create();
        $prestataire = User::factory()->create();
        $document = Document::create([
            'prestataire_id' => $prestataire->id,
            'title' => 'Access request',
            'status' => Document::STATUS_VALIDATED,
            'validated_at' => now(),
        ]);

        $recipient->notify(new DocumentWorkflowNotification($document));

        $notification = $recipient->notifications()->sole();

        $this->assertTrue(Str::isUuid($notification->id));
        $this->assertSame(DocumentWorkflowNotification::EVENT_VALIDATED, $notification->data['event']);
        $this->assertSame($document->id, $notification->data['document_id']);
        $this->assertSame($prestataire->name, $notification->data['prestataire_name']);
        $this->assertArrayHasKey('validated_at', $notification->data);
    }

    public function test_removed_workflow_schema_is_absent_while_security_data_tables_are_retained(): void
    {
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertTrue(Schema::hasTable('safety_team_members'));
        $this->assertFalse(Schema::hasTable('document_revisions'));
        $this->assertFalse(Schema::hasTable('safety_team_member_validations'));
        $this->assertTrue(Schema::hasColumn('safety_team_members', 'status'));
        $this->assertFalse(Schema::hasColumn('safety_team_members', 'submitted_for_review_at'));
        $this->assertFalse(Schema::hasColumn('safety_team_members', 'validated_by'));

        foreach ([
            'documents.view',
            'documents.update',
            'documents.approve',
            'documents.reject',
            'safety_team.view',
            'safety_team.update',
            'safety_team.approve',
            'safety_team.reject',
        ] as $removedPermission) {
            $this->assertArrayNotHasKey($removedPermission, Permission::CATALOG);
        }

        $this->assertArrayHasKey('documents.download', Permission::CATALOG);

        $migration = require database_path('migrations'.DIRECTORY_SEPARATOR.'2026_11_13_122106_remove_legacy_document_and_access_team_workflows.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('safety_team_members'));
        $this->assertFalse(Schema::hasTable('safety_team_member_validations'));
    }

    public function test_legacy_cleanup_rollback_restores_schema_and_permission_definitions(): void
    {
        $migration = require database_path('migrations'.DIRECTORY_SEPARATOR.'2026_11_13_122106_remove_legacy_document_and_access_team_workflows.php');
        $migration->down();

        $this->assertTrue(Schema::hasTable('document_revisions'));
        $this->assertTrue(Schema::hasTable('safety_team_member_validations'));
        $this->assertTrue(Schema::hasColumn('safety_team_members', 'submitted_for_review_at'));
        $this->assertTrue(Schema::hasColumn('safety_team_members', 'validated_by'));
        $this->assertTrue(Schema::hasColumn('safety_team_members', 'validated_at'));
        $this->assertTrue(Schema::hasColumn('safety_team_members', 'rejection_reason'));
        $this->assertDatabaseHas('permissions', ['name' => 'documents.view']);
    }
}
