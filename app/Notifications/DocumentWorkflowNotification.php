<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification
{
    public const EVENT_SUBMITTED = 'document_submitted';

    public const EVENT_REJECTED = 'document_rejected';

    public const EVENT_VALIDATED = 'document_validated';

    public const EVENT_RESUBMITTED = 'document_resubmitted';

    public const EVENT_UPDATED = 'document_updated';

    public const EVENT_REVISION_REQUESTED = 'document_revision_requested';

    public function __construct(
        public readonly Document $document,
        public readonly string $event,
        public readonly ?string $comment = null,
        public readonly ?string $revisionType = null,
        public readonly ?string $revisionDescription = null,
        public readonly ?string $revisionMemberName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $data = [
            'event' => $this->event,
            'document_id' => $this->document->getKey(),
            'title' => $this->document->title,
            'reference_number' => $this->document->reference_number,
            'status' => $this->document->status,
            'comment' => $this->comment,
            'prestataire_name' => $this->document->prestataire?->name,
            'member_names' => $this->document->relationLoaded('safetyTeamMembers')
                ? $this->document->safetyTeamMembers->pluck('name')->all()
                : $this->document->safetyTeamMembers()->pluck('name')->all(),
            'submitted_at' => $this->document->submitted_at?->toIso8601String(),
            'validated_at' => $this->document->validated_at?->toIso8601String(),
        ];

        if ($this->event === self::EVENT_REVISION_REQUESTED) {
            $data['revision_type'] = $this->revisionType;
            $data['revision_description'] = $this->revisionDescription;
            $data['revision_member_name'] = $this->revisionMemberName;
        }

        return $data;
    }
}
