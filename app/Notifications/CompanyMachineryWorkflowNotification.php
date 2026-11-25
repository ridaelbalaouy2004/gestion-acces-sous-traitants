<?php

namespace App\Notifications;

use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use Illuminate\Notifications\Notification;

class CompanyMachineryWorkflowNotification extends Notification
{
    public const EVENT_MACHINERY_SUBMITTED = 'company_machinery_submitted';

    public const EVENT_MACHINERY_RESUBMITTED = 'company_machinery_resubmitted';

    public const EVENT_MACHINERY_VALIDATED = 'company_machinery_validated';

    public const EVENT_MACHINERY_REJECTED = 'company_machinery_rejected';

    public const EVENT_DOCUMENT_SUBMITTED = 'company_machinery_document_submitted';

    public const EVENT_DOCUMENT_REPLACED = 'company_machinery_document_replaced';

    public const EVENT_DOCUMENT_VALIDATED = 'company_machinery_document_validated';

    public const EVENT_DOCUMENT_REJECTED = 'company_machinery_document_rejected';

    public function __construct(
        public CompanyMachinery $machinery,
        public string $event,
        public ?CompanyMachineryDocument $document = null,
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
        $this->machinery->loadMissing('company.prestataire');

        return [
            'event' => $this->event,
            'company_machinery_id' => $this->machinery->getKey(),
            'machinery_name' => $this->machinery->designation,
            'machinery_status' => $this->machinery->status,
            'company_id' => $this->machinery->company_id,
            'company_name' => $this->machinery->company?->name,
            'prestataire_id' => $this->machinery->company?->prestataire_id,
            'prestataire_name' => $this->machinery->company?->prestataire?->name,
            'document_id' => $this->document?->getKey(),
            'document_type' => $this->document?->type,
            'document_label' => $this->document === null
                ? null
                : CompanyMachineryDocument::labelForType($this->document->type),
            'document_status' => $this->document?->status,
            'comment' => $this->reason,
        ];
    }
}
