<?php

namespace App\Http\Controllers;

use App\Models\CompanyEmployee;
use App\Models\CompanyMachinery;
use App\Models\CompanyTooling;
use App\Models\Document;
use App\Models\User;
use App\Notifications\CompanyEmployeeWorkflowNotification;
use App\Notifications\CompanyMachineryWorkflowNotification;
use App\Notifications\CompanyToolingWorkflowNotification;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    private const ROLE_PRIORITY = [
        'responsable_securite',
        'prestataire',
        'gestionnaire',
    ];

    private const EMPLOYEE_EVENTS = [
        CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
        CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED,
        CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_VALIDATED,
        CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED,
        CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
        CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED,
        CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
        CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED,
    ];

    private const MACHINERY_EVENTS = [
        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED,
        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED,
        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_VALIDATED,
        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_REJECTED,
        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED,
        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED,
    ];

    private const TOOLING_EVENTS = [
        CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED,
        CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED,
        CompanyToolingWorkflowNotification::EVENT_TOOLING_VALIDATED,
        CompanyToolingWorkflowNotification::EVENT_TOOLING_REJECTED,
        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED,
        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $assignedRoles = $user->roles()
            ->whereIn('name', self::ROLE_PRIORITY)
            ->pluck('name');
        $role = collect(self::ROLE_PRIORITY)
            ->first(fn (string $role): bool => $assignedRoles->contains($role));

        abort_unless(is_string($role), 403);

        $notifications = $user->visibleNotifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        $documentIds = $notifications->getCollection()
            ->filter(fn (DatabaseNotification $notification): bool => $notification->type === DocumentWorkflowNotification::class)
            ->map(fn (DatabaseNotification $notification): mixed => data_get($notification->data, 'document_id'))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->unique()
            ->values();
        $documents = Document::query()
            ->whereIn('id', $documentIds)
            ->with('prestataire:id,name')
            ->get(['id', 'prestataire_id', 'title', 'reference_number', 'status'])
            ->keyBy('id');

        $employeeIds = $notifications->getCollection()
            ->filter(fn (DatabaseNotification $notification): bool => $notification->type === CompanyEmployeeWorkflowNotification::class)
            ->map(fn (DatabaseNotification $notification): mixed => data_get($notification->data, 'employee_id'))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->unique()
            ->values();
        $employees = CompanyEmployee::query()
            ->whereIn('id', $employeeIds)
            ->with(['company:id,name,prestataire_id', 'company.prestataire:id,name'])
            ->get(['id', 'company_id', 'first_name', 'last_name', 'status'])
            ->keyBy('id');
        $machineryIds = $notifications->getCollection()
            ->filter(fn (DatabaseNotification $notification): bool => $notification->type === CompanyMachineryWorkflowNotification::class)
            ->map(fn (DatabaseNotification $notification): mixed => data_get($notification->data, 'company_machinery_id'))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->unique()
            ->values();
        $machineryItems = CompanyMachinery::query()
            ->whereIn('id', $machineryIds)
            ->with(['company:id,name,prestataire_id', 'company.prestataire:id,name'])
            ->get(['id', 'company_id', 'designation', 'status'])
            ->keyBy('id');
        $toolingIds = $notifications->getCollection()
            ->filter(fn (DatabaseNotification $notification): bool => $notification->type === CompanyToolingWorkflowNotification::class)
            ->map(fn (DatabaseNotification $notification): mixed => data_get($notification->data, 'company_tooling_id'))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->unique()
            ->values();
        $toolingItems = CompanyTooling::query()
            ->whereIn('id', $toolingIds)
            ->with(['company:id,name,prestataire_id', 'company.prestataire:id,name'])
            ->get(['id', 'company_id', 'designation', 'status'])
            ->keyBy('id');

        $notificationItems = $notifications->getCollection()
            ->map(function (DatabaseNotification $notification) use ($documents, $employees, $machineryItems, $toolingItems, $role, $user): array {
                $data = $notification->data;
                $event = data_get($data, 'event');
                $documentId = data_get($data, 'document_id');
                $document = is_int($documentId) || (is_string($documentId) && ctype_digit($documentId))
                    ? $documents->get((int) $documentId)
                    : null;
                $canShowDocumentDetails = $role === 'responsable_securite'
                    && $event === DocumentWorkflowNotification::EVENT_VALIDATED
                    && $document?->status === Document::STATUS_VALIDATED;
                $employeeId = data_get($data, 'employee_id');
                $employee = is_int($employeeId) || (is_string($employeeId) && ctype_digit($employeeId))
                    ? $employees->get((int) $employeeId)
                    : null;
                $isEmployeeEvent = $notification->type === CompanyEmployeeWorkflowNotification::class
                    && in_array($event, self::EMPLOYEE_EVENTS, true);
                $canShowEmployeeDetails = $isEmployeeEvent
                    && $employee !== null
                    && match ($role) {
                        'prestataire' => (int) $employee->company?->prestataire_id === (int) $user->getKey(),
                        'gestionnaire' => $user->hasPermission('employees.view')
                            && $employee->company !== null
                            && $user->accessiblePrestataires()->whereKey($employee->company->prestataire_id)->exists(),
                        default => false,
                    };
                $machineryId = data_get($data, 'company_machinery_id');
                $machinery = is_int($machineryId) || (is_string($machineryId) && ctype_digit($machineryId))
                    ? $machineryItems->get((int) $machineryId)
                    : null;
                $isMachineryEvent = $notification->type === CompanyMachineryWorkflowNotification::class
                    && in_array($event, self::MACHINERY_EVENTS, true);
                $canShowMachineryDetails = $isMachineryEvent
                    && $machinery !== null
                    && match ($role) {
                        'prestataire' => (int) $machinery->company?->prestataire_id === (int) $user->getKey(),
                        'gestionnaire' => $user->hasPermission('machinery.view')
                            && $machinery->company !== null
                            && $user->accessiblePrestataires()->whereKey($machinery->company->prestataire_id)->exists(),
                        default => false,
                    };
                $toolingId = data_get($data, 'company_tooling_id');
                $tooling = is_int($toolingId) || (is_string($toolingId) && ctype_digit($toolingId))
                    ? $toolingItems->get((int) $toolingId)
                    : null;
                $isToolingEvent = $notification->type === CompanyToolingWorkflowNotification::class
                    && in_array($event, self::TOOLING_EVENTS, true);
                $canShowToolingDetails = $isToolingEvent
                    && $tooling !== null
                    && match ($role) {
                        'prestataire' => (int) $tooling->company?->prestataire_id === (int) $user->getKey(),
                        'gestionnaire' => $user->hasPermission('toolings.view')
                            && $tooling->company !== null
                            && $user->accessiblePrestataires()->whereKey($tooling->company->prestataire_id)->exists(),
                        default => false,
                    };

                $labels = match ($event) {
                    DocumentWorkflowNotification::EVENT_VALIDATED => [
                        'title' => 'Document validé',
                        'message' => 'Un document a été validé et peut être consulté.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED => [
                        'title' => 'Nouvel employé à examiner',
                        'message' => 'Un employé a été soumis pour validation.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED => [
                        'title' => 'Employé soumis à nouveau',
                        'message' => 'Un employé corrigé a été transmis pour une nouvelle vérification.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_VALIDATED => [
                        'title' => 'Employé validé',
                        'message' => 'La fiche employé a été validée.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED => [
                        'title' => 'Employé rejeté',
                        'message' => 'La fiche employé a été rejetée.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED => [
                        'title' => 'Document employé à examiner',
                        'message' => 'Un document employé a été téléversé et attend sa validation.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED => [
                        'title' => 'Document employé remplacé',
                        'message' => 'Un document employé a été remplacé et attend sa validation.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED => [
                        'title' => 'Document employé validé',
                        'message' => 'Un document employé a été validé indépendamment.',
                    ],
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED => [
                        'title' => 'Document employé rejeté',
                        'message' => 'Un document employé a été rejeté.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED => [
                        'title' => 'Nouvel engin à examiner',
                        'message' => 'Un engin a été soumis pour validation.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED => [
                        'title' => 'Engin soumis à nouveau',
                        'message' => 'Un engin corrigé a été transmis pour une nouvelle vérification.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_MACHINERY_VALIDATED => [
                        'title' => 'Engin validé',
                        'message' => 'La fiche engin a été validée.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_MACHINERY_REJECTED => [
                        'title' => 'Engin rejeté',
                        'message' => 'La fiche engin a été rejetée.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED => [
                        'title' => 'Document engin à examiner',
                        'message' => 'Un document engin a été téléversé et attend sa validation.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED => [
                        'title' => 'Document engin remplacé',
                        'message' => 'Un document engin a été remplacé et attend sa validation.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED => [
                        'title' => 'Document engin validé',
                        'message' => 'Un document engin a été validé indépendamment.',
                    ],
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED => [
                        'title' => 'Document engin rejeté',
                        'message' => 'Un document engin a été rejeté.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED => [
                        'title' => 'Nouvel outillage à examiner',
                        'message' => 'Un outillage a été soumis pour validation.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED => [
                        'title' => 'Outillage soumis à nouveau',
                        'message' => 'Un outillage corrigé a été transmis pour une nouvelle vérification.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_TOOLING_VALIDATED => [
                        'title' => 'Outillage validé',
                        'message' => 'La fiche outillage a été validée.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_TOOLING_REJECTED => [
                        'title' => 'Outillage rejeté',
                        'message' => 'La fiche outillage a été rejetée.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED => [
                        'title' => 'Document outillage à examiner',
                        'message' => 'Un document outillage a été téléversé et attend sa validation.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED => [
                        'title' => 'Document outillage remplacé',
                        'message' => 'Un document outillage a été remplacé et attend sa validation.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED => [
                        'title' => 'Document outillage validé',
                        'message' => 'Un document outillage a été validé indépendamment.',
                    ],
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED => [
                        'title' => 'Document outillage rejeté',
                        'message' => 'Un document outillage a été rejeté.',
                    ],
                    default => [
                        'title' => 'Notification',
                        'message' => 'Une mise à jour est disponible.',
                    ],
                };

                if (! $canShowDocumentDetails && ! $canShowEmployeeDetails && ! $canShowMachineryDetails && ! $canShowToolingDetails) {
                    $labels = [
                        'title' => 'Notification',
                        'message' => 'Une mise à jour est disponible.',
                    ];
                }

                $employeeStatus = in_array($event, [
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                    CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                ], true)
                    ? data_get($data, 'document_status')
                    : data_get($data, 'employee_status');
                $employeeStatusLabel = match ($employeeStatus) {
                    CompanyEmployee::STATUS_DRAFT => 'Brouillon',
                    CompanyEmployee::STATUS_PENDING => 'En attente',
                    CompanyEmployee::STATUS_VALIDATED => 'Validé',
                    CompanyEmployee::STATUS_REJECTED => 'Rejeté',
                    default => null,
                };
                $employeeUrl = $canShowEmployeeDetails
                    ? match ($role) {
                        'prestataire' => route('prestataire.employees.show', $employee),
                        'gestionnaire' => route('gestionnaire.employees.show', $employee),
                        default => null,
                    }
                : null;
                $machineryStatus = in_array($event, [
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                    CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                ], true)
                    ? data_get($data, 'document_status')
                    : data_get($data, 'machinery_status');
                $machineryStatusLabel = match ($machineryStatus) {
                    CompanyMachinery::STATUS_DRAFT => 'Brouillon',
                    CompanyMachinery::STATUS_PENDING => 'En attente',
                    CompanyMachinery::STATUS_VALIDATED => 'Validé',
                    CompanyMachinery::STATUS_REJECTED => 'Rejeté',
                    default => null,
                };
                $machineryUrl = $canShowMachineryDetails
                    ? match ($role) {
                        'prestataire' => route('prestataire.machinery.show', $machinery),
                        'gestionnaire' => route('gestionnaire.machinery.show', $machinery),
                        default => null,
                    }
                : null;
                $toolingStatus = in_array($event, [
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_VALIDATED,
                    CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                ], true)
                    ? data_get($data, 'document_status')
                    : data_get($data, 'tooling_status');
                $toolingStatusLabel = match ($toolingStatus) {
                    CompanyTooling::STATUS_DRAFT => 'Brouillon',
                    CompanyTooling::STATUS_PENDING => 'En attente',
                    CompanyTooling::STATUS_VALIDATED => 'Validé',
                    CompanyTooling::STATUS_REJECTED => 'Rejeté',
                    default => null,
                };
                $toolingUrl = $canShowToolingDetails
                    ? match ($role) {
                        'prestataire' => route('prestataire.toolings.show', $tooling),
                        'gestionnaire' => route('gestionnaire.toolings.show', $tooling),
                        default => null,
                    }
                : null;

                return [
                    'notification' => $notification,
                    'title' => $labels['title'],
                    'message' => $labels['message'],
                    'documentTitle' => $canShowDocumentDetails ? ($document?->title ?? data_get($data, 'title')) : null,
                    'referenceNumber' => $canShowDocumentDetails ? ($document?->reference_number ?? data_get($data, 'reference_number')) : null,
                    'prestataireName' => $canShowDocumentDetails ? ($document?->prestataire?->name ?? data_get($data, 'prestataire_name')) : null,
                    'memberNames' => [],
                    'revisionMemberName' => null,
                    'statusLabel' => $canShowDocumentDetails ? 'Validé' : null,
                    'dateLabel' => $canShowDocumentDetails ? 'Validé le' : null,
                    'comment' => (
                        ($canShowEmployeeDetails && in_array($event, [
                            CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_REJECTED,
                            CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                        ], true))
                        || ($canShowMachineryDetails && in_array($event, [
                            CompanyMachineryWorkflowNotification::EVENT_MACHINERY_REJECTED,
                            CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                        ], true))
                        || ($canShowToolingDetails && in_array($event, [
                            CompanyToolingWorkflowNotification::EVENT_TOOLING_REJECTED,
                            CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REJECTED,
                        ], true))
                    )
                        && is_string(data_get($data, 'comment'))
                            ? data_get($data, 'comment')
                            : null,
                    'documentUrl' => $canShowDocumentDetails
                        ? route('responsable-securite.documents.show', $document)
                        : null,
                    'memberUrl' => null,
                    'memberRequestType' => null,
                    'employeeName' => $canShowEmployeeDetails
                        ? (trim($employee->first_name.' '.$employee->last_name) ?: 'Employé')
                        : null,
                    'employeeCompanyName' => $canShowEmployeeDetails ? $employee->company?->name : null,
                    'employeePrestataireName' => $canShowEmployeeDetails && $role === 'gestionnaire'
                        ? $employee->company?->prestataire?->name
                        : null,
                    'employeeDocumentLabel' => $canShowEmployeeDetails ? data_get($data, 'document_label') : null,
                    'employeeStatusLabel' => $canShowEmployeeDetails && is_string($employeeStatusLabel) ? $employeeStatusLabel : null,
                    'employeeUrl' => $employeeUrl,
                    'employeeDateLabel' => $role === 'gestionnaire' && in_array($event, [
                        CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_SUBMITTED,
                        CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED,
                        CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        CompanyEmployeeWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                    ], true)
                        ? ($event === CompanyEmployeeWorkflowNotification::EVENT_EMPLOYEE_RESUBMITTED ? 'Renvoyé le' : 'Soumis le')
                        : null,
                    'machineryName' => $canShowMachineryDetails ? $machinery->designation : null,
                    'machineryUrl' => $machineryUrl,
                    'machineryCompanyName' => $canShowMachineryDetails ? $machinery->company?->name : null,
                    'machineryPrestataireName' => $canShowMachineryDetails && $role === 'gestionnaire'
                        ? $machinery->company?->prestataire?->name
                        : null,
                    'machineryDocumentLabel' => $canShowMachineryDetails ? data_get($data, 'document_label') : null,
                    'machineryStatusLabel' => $canShowMachineryDetails && is_string($machineryStatusLabel) ? $machineryStatusLabel : null,
                    'machineryDateLabel' => $role === 'gestionnaire' && in_array($event, [
                        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_SUBMITTED,
                        CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED,
                        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        CompanyMachineryWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                    ], true)
                        ? ($event === CompanyMachineryWorkflowNotification::EVENT_MACHINERY_RESUBMITTED ? 'Renvoyé le' : 'Soumis le')
                        : null,
                    'toolingName' => $canShowToolingDetails ? $tooling->designation : null,
                    'toolingUrl' => $toolingUrl,
                    'toolingCompanyName' => $canShowToolingDetails ? $tooling->company?->name : null,
                    'toolingPrestataireName' => $canShowToolingDetails && $role === 'gestionnaire'
                        ? $tooling->company?->prestataire?->name
                        : null,
                    'toolingDocumentLabel' => $canShowToolingDetails ? data_get($data, 'document_label') : null,
                    'toolingStatusLabel' => $canShowToolingDetails && is_string($toolingStatusLabel) ? $toolingStatusLabel : null,
                    'toolingDateLabel' => $role === 'gestionnaire' && in_array($event, [
                        CompanyToolingWorkflowNotification::EVENT_TOOLING_SUBMITTED,
                        CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED,
                        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_SUBMITTED,
                        CompanyToolingWorkflowNotification::EVENT_DOCUMENT_REPLACED,
                    ], true)
                        ? ($event === CompanyToolingWorkflowNotification::EVENT_TOOLING_RESUBMITTED ? 'Renvoyé le' : 'Soumis le')
                        : null,
                ];
            });

        $roleLabels = [
            'prestataire' => 'Prestataire',
            'gestionnaire' => 'Gestionnaire',
            'responsable_securite' => 'Responsable sécurité',
        ];

        return view('shared.notifications.index', [
            'notifications' => $notifications,
            'notificationItems' => $notificationItems,
            'roleLabel' => $roleLabels[$role],
            'routePrefix' => $role === 'responsable_securite' ? 'responsable-securite' : $role,
            'navigation' => $role === 'gestionnaire'
                ? DashboardController::navigationForUser($user)
                : DashboardController::navigationForRole($role),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $ownedNotification = $user->visibleNotifications()
            ->whereKey($notification)
            ->firstOrFail();

        $ownedNotification->markAsRead();

        return to_route('notifications.index')
            ->with('status', 'La notification a été marquée comme lue.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $markedAt = now();
        $updatedCount = $user->visibleNotifications()->whereNull('read_at')->update([
            'read_at' => $markedAt,
            'updated_at' => $markedAt,
        ]);

        $message = $updatedCount > 0
            ? 'Toutes les notifications ont été marquées comme lues.'
            : 'Aucune notification non lue.';

        return to_route('notifications.index')->with('status', $message);
    }
}
