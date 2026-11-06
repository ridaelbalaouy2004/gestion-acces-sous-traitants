<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const NAVIGATION = [
        ['label' => 'Tableau de bord', 'route' => 'gestionnaire.dashboard', 'icon' => 'dashboard'],
        ['label' => 'Documents', 'route' => 'gestionnaire.documents.index', 'icon' => 'documents'],
        ['label' => 'Documents en attente', 'route' => 'gestionnaire.documents.pending', 'icon' => 'clock'],
        ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
        ['label' => 'Mon profil', 'route' => 'gestionnaire.profile', 'icon' => 'user'],
    ];

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function pending(Request $request): View
    {
        return $this->listing($request, true);
    }

    public function show(Document $document): View
    {
        $this->ensureSubmitted($document);

        $document->load([
            'prestataire:id,name,email',
            'validatedBy:id,name',
            'safetyTeamMembers',
            'revisions' => fn (HasMany $query) => $query
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        ]);

        return $this->page('gestionnaire.documents.show', [
            'document' => $document,
        ]);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->ensureSubmitted($document);

        $path = $document->attachment_path;

        abort_unless(
            is_string($path) && Storage::disk('documents_private')->exists($path),
            404,
        );

        return Storage::disk('documents_private')->download($path, basename($path));
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $gestionnaire = $request->user();
        abort_unless($gestionnaire instanceof User, 403);

        DB::transaction(function () use ($document, $gestionnaire): void {
            $lockedDocument = Document::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            abort_unless(
                $lockedDocument->status === Document::STATUS_PENDING && $lockedDocument->submitted_at !== null,
                409,
                'Seuls les documents soumis et en attente peuvent être validés.',
            );

            $lockedDocument->update([
                'status' => Document::STATUS_VALIDATED,
                'validated_by' => $gestionnaire->getKey(),
                'validated_at' => now(),
                'rejection_comment' => null,
            ]);

            $lockedDocument->load(['prestataire', 'safetyTeamMembers']);
            $lockedDocument->prestataire->notify(new DocumentWorkflowNotification(
                $lockedDocument,
                DocumentWorkflowNotification::EVENT_VALIDATED,
            ));
            $securityLeads = User::query()
                ->whereHas('roles', fn (Builder $query) => $query->where('name', 'responsable_securite'))
                ->get();
            Notification::send($securityLeads, new DocumentWorkflowNotification(
                $lockedDocument,
                DocumentWorkflowNotification::EVENT_VALIDATED,
            ));
        });

        return to_route('gestionnaire.documents.show', $document)
            ->with('status', 'Le document a été validé.');
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Veuillez saisir le motif du rejet.',
            'reason.string' => 'Le motif du rejet doit être un texte valide.',
            'reason.min' => 'Le motif du rejet doit contenir au moins 10 caractères.',
            'reason.max' => 'Le motif du rejet ne peut pas dépasser 2 000 caractères.',
        ], [
            'reason' => 'motif du rejet',
        ]);

        $gestionnaire = $request->user();
        abort_unless($gestionnaire instanceof User, 403);

        DB::transaction(function () use ($document, $validated): void {
            $lockedDocument = Document::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            abort_unless(
                $lockedDocument->status === Document::STATUS_PENDING && $lockedDocument->submitted_at !== null,
                409,
                'Seuls les documents soumis et en attente peuvent être rejetés.',
            );

            $lockedDocument->update([
                'status' => Document::STATUS_REJECTED,
                'rejection_comment' => $validated['reason'],
                'validated_by' => null,
                'validated_at' => null,
            ]);

            $lockedDocument->load(['prestataire', 'safetyTeamMembers']);
            $lockedDocument->prestataire->notify(new DocumentWorkflowNotification(
                $lockedDocument,
                DocumentWorkflowNotification::EVENT_REJECTED,
                $validated['reason'],
            ));
        });

        return to_route('gestionnaire.documents.show', $document)
            ->with('status', 'Le document a été rejeté et le prestataire a été informé.');
    }

    private function listing(Request $request, bool $pendingOnly = false): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    Document::STATUS_PENDING,
                    Document::STATUS_VALIDATED,
                    Document::STATUS_REJECTED,
                ]),
            ],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
        ], [
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'sort.in' => 'Le tri sélectionné est invalide.',
        ]);

        $sortDirection = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $search = isset($filters['search']) && $filters['search'] !== ''
            ? '%'.$filters['search'].'%'
            : null;

        $documents = Document::query()
            ->with('prestataire:id,name')
            ->where(function (Builder $query): void {
                $query->where('status', '!=', Document::STATUS_PENDING)
                    ->orWhereNotNull('submitted_at');
            })
            ->when($pendingOnly, fn (Builder $query) => $query
                ->where('status', Document::STATUS_PENDING)
                ->whereNotNull('submitted_at'))
            ->when(
                ! $pendingOnly && isset($filters['status']),
                fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when($search !== null, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('reference_number', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhereHas('prestataire', function (Builder $prestataireQuery) use ($search): void {
                            $prestataireQuery->where('name', 'like', $search);
                        });
                });
            })
            ->orderByRaw('COALESCE(submitted_at, created_at) '.$sortDirection)
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return $this->page('gestionnaire.documents.index', [
            'documents' => $documents,
            'filters' => $filters,
            'pendingOnly' => $pendingOnly,
        ]);
    }

    private function ensureSubmitted(Document $document): void
    {
        abort_unless(
            $document->status !== Document::STATUS_PENDING || $document->submitted_at !== null,
            404,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data = []): View
    {
        return view($view, $data + [
            'roleLabel' => 'Gestionnaire',
            'routePrefix' => 'gestionnaire',
            'navigation' => self::NAVIGATION,
        ]);
    }
}
