<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification
{
    public const EVENT_VALIDATED = 'document_validated';

    public function __construct(public readonly Document $document) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => self::EVENT_VALIDATED,
            'document_id' => $this->document->getKey(),
            'title' => $this->document->title,
            'reference_number' => $this->document->reference_number,
            'status' => $this->document->status,
            'prestataire_name' => $this->document->prestataire?->name,
            'validated_at' => $this->document->validated_at?->toIso8601String(),
        ];
    }
}
