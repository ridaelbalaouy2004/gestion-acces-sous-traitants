<?php

namespace App\Notifications;

use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use Illuminate\Notifications\Notification;

class CompanyEmployeeWorkflowNotification extends Notification
{
    public const EVENT_EMPLOYEE_SUBMITTED = 'company_employee_submitted';

    public const EVENT_EMPLOYEE_RESUBMITTED = 'company_employee_resubmitted';

    public const EVENT_EMPLOYEE_VALIDATED = 'company_employee_validated';

    public const EVENT_EMPLOYEE_REJECTED = 'company_employee_rejected';

    public const EVENT_DOCUMENT_SUBMITTED = 'company_employee_document_submitted';

    public const EVENT_DOCUMENT_REPLACED = 'company_employee_document_replaced';

    public const EVENT_DOCUMENT_VALIDATED = 'company_employee_document_validated';

    public const EVENT_DOCUMENT_REJECTED = 'company_employee_document_rejected';

    public function __construct(
        public CompanyEmployee $employee,
        public string $event,
        public ?CompanyEmployeeDocument $document = null,
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
        $this->employee->loadMissing('company.prestataire');

        return [
            'event' => $this->event,
            'employee_id' => $this->employee->getKey(),
            'employee_name' => trim(($this->employee->first_name ?? '').' '.($this->employee->last_name ?? '')),
            'employee_status' => $this->employee->status,
            'company_id' => $this->employee->company_id,
            'company_name' => $this->employee->company?->name,
            'prestataire_id' => $this->employee->company?->prestataire_id,
            'prestataire_name' => $this->employee->company?->prestataire?->name,
            'document_id' => $this->document?->getKey(),
            'document_type' => $this->document?->type,
            'document_label' => $this->document === null
                ? null
                : CompanyEmployeeDocument::labelForType($this->document->type),
            'document_status' => $this->document?->status,
            'comment' => $this->reason,
        ];
    }
}
