<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_document_workflow_notifications_are_stored_with_uuid_identifiers(): void
    {
        $recipient = User::factory()->create();
        $prestataire = User::factory()->create();
        $document = Document::create([
            'prestataire_id' => $prestataire->id,
            'title' => 'Access request',
            'status' => Document::STATUS_REJECTED,
            'rejection_comment' => 'Please update the submitted details.',
        ]);

        $recipient->notify(new DocumentWorkflowNotification(
            $document,
            DocumentWorkflowNotification::EVENT_REJECTED,
            $document->rejection_comment,
        ));

        $notification = $recipient->notifications()->sole();

        $this->assertTrue(Str::isUuid($notification->id));
        $this->assertSame(DocumentWorkflowNotification::EVENT_REJECTED, $notification->data['event']);
        $this->assertSame($document->id, $notification->data['document_id']);
        $this->assertSame($document->rejection_comment, $notification->data['comment']);
        $this->assertSame($prestataire->name, $notification->data['prestataire_name']);
        $this->assertSame([], $notification->data['member_names']);
        $this->assertArrayHasKey('submitted_at', $notification->data);
        $this->assertArrayHasKey('validated_at', $notification->data);
    }
}
