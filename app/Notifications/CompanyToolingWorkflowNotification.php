<?php

namespace App\Notifications;

use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use Illuminate\Notifications\Notification;

class CompanyToolingWorkflowNotification extends Notification
{
    public const EVENT_TOOLING_SUBMITTED = 'company_tooling_submitted';

    public const EVENT_TOOLING_RESUBMITTED = 'company_tooling_resubmitted';

    public const EVENT_TOOLING_VALIDATED = 'company_tooling_validated';

    public const EVENT_TOOLING_REJECTED = 'company_tooling_rejected';

    public const EVENT_DOCUMENT_SUBMITTED = 'company_tooling_document_submitted';

    public const EVENT_DOCUMENT_REPLACED = 'company_tooling_document_replaced';

    public const EVENT_DOCUMENT_VALIDATED = 'company_tooling_document_validated';

    public const EVENT_DOCUMENT_REJECTED = 'company_tooling_document_rejected';

    public function __construct(
        public CompanyTooling $tooling,
        public string $event,
        public ?CompanyToolingDocument $document = null,
        public ?string $reason = null,
    ) {}

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
        $this->tooling->loadMissing('company.prestataire');

        return [
            'event' => $this->event,
            'company_tooling_id' => $this->tooling->getKey(),
            'tooling_name' => $this->tooling->designation,
            'tooling_status' => $this->tooling->status,
            'company_id' => $this->tooling->company_id,
            'company_name' => $this->tooling->company?->name,
            'prestataire_id' => $this->tooling->company?->prestataire_id,
            'prestataire_name' => $this->tooling->company?->prestataire?->name,
            'document_id' => $this->document?->getKey(),
            'document_type' => $this->document?->type,
            'document_label' => $this->document?->label,
            'document_status' => $this->document?->status,
            'comment' => $this->reason,
        ];
    }
}
