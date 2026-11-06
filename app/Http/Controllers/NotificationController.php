<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
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

        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        $documentIds = $notifications->getCollection()
            ->map(fn (DatabaseNotification $notification): mixed => data_get($notification->data, 'document_id'))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->unique()
            ->values();

        $documents = Document::query()
            ->whereIn('id', $documentIds)
            ->with('prestataire:id,name')
            ->get(['id', 'prestataire_id', 'title', 'reference_number', 'status'])
            ->keyBy('id');

        $notificationItems = $notifications->getCollection()
            ->map(function (DatabaseNotification $notification) use ($documents, $role, $user): array {
                $data = $notification->data;
                $documentId = data_get($data, 'document_id');
                $document = is_int($documentId) || (is_string($documentId) && ctype_digit($documentId))
                    ? $documents->get((int) $documentId)
                    : null;
                $canShowDocumentDetails = match ($role) {
                    'prestataire' => $document === null || (int) $document->prestataire_id === (int) $user->getKey(),
                    'gestionnaire' => true,
                    'responsable_securite' => $document !== null && $document->status === Document::STATUS_VALIDATED,
                };
                $event = data_get($data, 'event');
                $revisionDocumentTitle = data_get($data, 'title');
                $revisionDocumentTitle = is_string($revisionDocumentTitle) ? $revisionDocumentTitle : 'ce document';

                $labels = match ($event) {
                    DocumentWorkflowNotification::EVENT_SUBMITTED => [
                        'title' => 'Nouveau dossier à examiner',
                        'message' => 'Un prestataire a soumis un nouveau dossier.',
                    ],
                    DocumentWorkflowNotification::EVENT_RESUBMITTED => [
                        'title' => 'Dossier soumis à nouveau',
                        'message' => 'Un dossier corrigé a été transmis pour une nouvelle vérification.',
                    ],
                    DocumentWorkflowNotification::EVENT_REJECTED => [
                        'title' => 'Document refusé',
                        'message' => 'Votre document a été refusé.',
                    ],
                    DocumentWorkflowNotification::EVENT_VALIDATED => [
                        'title' => 'Document validé',
                        'message' => $role === 'responsable_securite'
                            ? 'Un document a été validé et peut être consulté.'
                            : 'Votre document a été accepté.',
                    ],
                    DocumentWorkflowNotification::EVENT_UPDATED => [
                        'title' => 'Dossier mis à jour',
                        'message' => 'L’équipe de sécurité de ce dossier a été mise à jour.',
                    ],
                    DocumentWorkflowNotification::EVENT_REVISION_REQUESTED => [
                        'title' => 'Document nécessitant une nouvelle vérification',
                        'message' => match (data_get($data, 'revision_type')) {
                            DocumentRevision::TYPE_MEMBER_ADDED => "Le Prestataire a ajouté un nouveau membre à l'équipe de sécurité du document {$revisionDocumentTitle}. Une nouvelle validation est nécessaire.",
                            DocumentRevision::TYPE_MEMBER_UPDATED => "Le Prestataire a modifié un membre de l'équipe de sécurité du document {$revisionDocumentTitle}. Une nouvelle validation est nécessaire.",
                            DocumentRevision::TYPE_MEMBER_DELETED => "Le Prestataire a supprimé un membre de l'équipe de sécurité du document {$revisionDocumentTitle}. Une nouvelle validation est nécessaire.",
                            default => "Une modification de l'équipe de sécurité du document {$revisionDocumentTitle} nécessite une nouvelle validation.",
                        },
                    ],
                    default => [
                        'title' => 'Mise à jour du document',
                        'message' => 'Une mise à jour concernant ce dossier est disponible.',
                    ],
                };

                if (! $canShowDocumentDetails) {
                    $labels = [
                        'title' => 'Notification',
                        'message' => 'Une mise à jour est disponible.',
                    ];
                }

                $documentTitle = $canShowDocumentDetails
                    ? ($document?->title ?? data_get($data, 'title'))
                    : null;
                $referenceNumber = $canShowDocumentDetails
                    ? ($document?->reference_number ?? data_get($data, 'reference_number'))
                    : null;
                $prestataireName = $canShowDocumentDetails && $role !== 'prestataire'
                    ? ($document?->prestataire?->name ?? data_get($data, 'prestataire_name'))
                    : null;
                $memberNames = $canShowDocumentDetails && is_array(data_get($data, 'member_names'))
                    ? collect(data_get($data, 'member_names'))
                        ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
                        ->values()
                        ->all()
                    : [];
                $statusLabel = match (data_get($data, 'status')) {
                    Document::STATUS_PENDING => 'En attente',
                    Document::STATUS_VALIDATED => 'Validé',
                    Document::STATUS_REJECTED => 'Refusé',
                    default => null,
                };
                $comment = $canShowDocumentDetails
                    && $role !== 'responsable_securite'
                    && in_array($event, [
                        DocumentWorkflowNotification::EVENT_REJECTED,
                        DocumentWorkflowNotification::EVENT_REVISION_REQUESTED,
                    ], true)
                    && is_string(data_get($data, 'comment'))
                        ? data_get($data, 'comment')
                        : null;
                $revisionMemberName = $canShowDocumentDetails
                    && $role === 'gestionnaire'
                    && is_string(data_get($data, 'revision_member_name'))
                        ? data_get($data, 'revision_member_name')
                        : null;

                $documentUrl = match ($role) {
                    'prestataire' => $document !== null && $canShowDocumentDetails
                        ? route('prestataire.documents.show', $document)
                        : null,
                    'gestionnaire' => $document !== null
                        ? route('gestionnaire.documents.show', $document)
                        : null,
                    'responsable_securite' => $document !== null && $canShowDocumentDetails
                        ? route('responsable-securite.documents.show', $document)
                        : null,
                };

                return [
                    'notification' => $notification,
                    'title' => $labels['title'],
                    'message' => $labels['message'],
                    'documentTitle' => is_string($documentTitle) ? $documentTitle : null,
                    'referenceNumber' => is_string($referenceNumber) ? $referenceNumber : null,
                    'prestataireName' => is_string($prestataireName) ? $prestataireName : null,
                    'memberNames' => $memberNames,
                    'revisionMemberName' => $revisionMemberName,
                    'statusLabel' => $canShowDocumentDetails && is_string($statusLabel) ? $statusLabel : null,
                    'dateLabel' => match (true) {
                        $role === 'gestionnaire' && $event === DocumentWorkflowNotification::EVENT_SUBMITTED => 'Soumis le',
                        $role === 'gestionnaire' && $event === DocumentWorkflowNotification::EVENT_RESUBMITTED => 'Renvoyé le',
                        $role === 'gestionnaire' && $event === DocumentWorkflowNotification::EVENT_UPDATED => 'Mis à jour le',
                        $role === 'gestionnaire' && $event === DocumentWorkflowNotification::EVENT_REVISION_REQUESTED => 'Modification le',
                        $role === 'responsable_securite' && $event === DocumentWorkflowNotification::EVENT_VALIDATED => 'Validé le',
                        default => null,
                    },
                    'comment' => $comment,
                    'documentUrl' => $documentUrl,
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
            'navigation' => DashboardController::navigationForRole($role),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $ownedNotification = $user->notifications()
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
        $updatedCount = $user->unreadNotifications()->update([
            'read_at' => $markedAt,
            'updated_at' => $markedAt,
        ]);

        $message = $updatedCount > 0
            ? 'Toutes les notifications ont été marquées comme lues.'
            : 'Aucune notification non lue.';

        return to_route('notifications.index')->with('status', $message);
    }
}
