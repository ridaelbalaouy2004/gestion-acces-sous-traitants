<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const NAVIGATION = [
        ['label' => 'Tableau de bord', 'route' => 'prestataire.dashboard', 'icon' => 'dashboard'],
        ['label' => 'Mes documents', 'route' => 'prestataire.documents.index', 'icon' => 'documents'],
        ['label' => 'Nouveau document', 'route' => 'prestataire.documents.create', 'icon' => 'plus'],
        ['label' => 'Mon équipe de sécurité', 'route' => 'prestataire.safety-team', 'icon' => 'users'],
        ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
        ['label' => 'Mon profil', 'route' => 'prestataire.profile', 'icon' => 'user'],
    ];

    public function index(Request $request): View
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
        ], [
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'status.in' => 'Le statut sélectionné est invalide.',
        ]);

        $documents = $this->currentPrestataire($request)
            ->documents()
            ->when(isset($filters['search']) && $filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('reference_number', 'like', $search)
                        ->orWhere('company_name', 'like', $search);
                });
            })
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByRaw('COALESCE(submitted_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return $this->page('prestataire.documents.index', [
            'documents' => $documents,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return $this->page('prestataire.documents.create', [
            'document' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedDocument($request);
        $prestataire = $this->currentPrestataire($request);

        $document = DB::transaction(function () use ($request, $prestataire, $validated): Document {
            $document = $prestataire->documents()->create([
                'title' => $validated['title'],
                'company_name' => $validated['company_name'],
                'description' => $validated['description'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'attachment_path' => null,
                'custom_fields' => $this->customFields($validated['custom_fields'] ?? []),
                'status' => Document::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            if ($request->hasFile('attachment')) {
                $document->update([
                    'attachment_path' => $this->storeAttachment($request, $prestataire),
                ]);
            }

            $this->notifyReviewers($document, DocumentWorkflowNotification::EVENT_SUBMITTED);

            return $document;
        });

        return to_route('prestataire.documents.show', $document)
            ->with('status', 'Votre document a été transmis avec succès.');
    }

    public function show(Request $request, int $document): View
    {
        return $this->page('prestataire.documents.show', [
            'document' => $this->ownedDocument($request, $document),
        ]);
    }

    public function edit(Request $request, int $document): View
    {
        $document = $this->ownedDocument($request, $document);
        $this->ensureRejected($document);

        return $this->page('prestataire.documents.edit', [
            'document' => $document,
        ]);
    }

    public function update(Request $request, int $document): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
        $ownedDocument = $prestataire->documents()->findOrFail($document);
        $this->ensureRejected($ownedDocument);
        $validated = $this->validatedDocument($request);
        $oldAttachmentPath = null;
        $newAttachmentPath = null;

        $document = DB::transaction(function () use (
            $request,
            $prestataire,
            $document,
            $validated,
            &$oldAttachmentPath,
            &$newAttachmentPath,
        ): Document {
            $document = $prestataire->documents()->lockForUpdate()->findOrFail($document);
            $this->ensureRejected($document);
            $oldAttachmentPath = $document->attachment_path;

            if ($request->hasFile('attachment')) {
                $newAttachmentPath = $this->storeAttachment($request, $prestataire);
            }

            $document->update([
                'title' => $validated['title'],
                'company_name' => $validated['company_name'],
                'description' => $validated['description'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'attachment_path' => $newAttachmentPath ?? $oldAttachmentPath,
                'custom_fields' => $this->customFields($validated['custom_fields'] ?? []),
                'status' => Document::STATUS_PENDING,
                'rejection_comment' => null,
                'validated_by' => null,
                'validated_at' => null,
                'submitted_at' => now(),
            ]);

            $this->notifyReviewers($document, DocumentWorkflowNotification::EVENT_RESUBMITTED);

            return $document;
        });

        if ($newAttachmentPath !== null && $oldAttachmentPath !== null) {
            Storage::disk('documents_private')->delete($oldAttachmentPath);
        }

        return to_route('prestataire.documents.show', $document)
            ->with('status', 'Votre document corrigé a été transmis pour une nouvelle vérification.');
    }

    public function download(Request $request, int $document): StreamedResponse
    {
        $document = $this->ownedDocument($request, $document);
        $path = $document->attachment_path;

        abort_unless(
            is_string($path) && Storage::disk('documents_private')->exists($path),
            404,
        );

        return Storage::disk('documents_private')->download($path, basename($path));
    }

    private function currentPrestataire(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function ownedDocument(Request $request, int $documentId): Document
    {
        return $this->currentPrestataire($request)
            ->documents()
            ->findOrFail($documentId);
    }

    private function ensureRejected(Document $document): void
    {
        abort_unless(
            $document->status === Document::STATUS_REJECTED,
            403,
            'Seuls les documents rejetés peuvent être modifiés.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDocument(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'extensions:pdf,jpg,jpeg,png',
                'max:10240',
            ],
            'custom_fields' => ['sometimes', 'array', 'max:10'],
            'custom_fields.*' => ['required', 'array:name,value'],
            'custom_fields.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'custom_fields.*.value' => ['required', 'string', 'max:500'],
        ], [
            'title.required' => 'Veuillez saisir le titre du document.',
            'company_name.required' => 'Veuillez saisir le nom de l’entreprise.',
            'description.required' => 'Veuillez décrire le document.',
            'start_date.required' => 'Veuillez indiquer la date de début.',
            'start_date.date' => 'La date de début doit être une date valide.',
            'end_date.required' => 'Veuillez indiquer la date de fin.',
            'end_date.date' => 'La date de fin doit être une date valide.',
            'end_date.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
            'attachment.mimes' => 'Le fichier doit être au format PDF, JPG ou PNG.',
            'attachment.extensions' => 'L’extension du fichier doit être PDF, JPG ou PNG.',
            'attachment.max' => 'Le fichier ne peut pas dépasser 10 Mo.',
            'custom_fields.max' => 'Vous pouvez ajouter au maximum 10 champs personnalisés.',
            'custom_fields.*.name.distinct' => 'Chaque champ personnalisé doit avoir un nom différent.',
        ], [
            'title' => 'titre',
            'company_name' => 'nom de l’entreprise',
            'description' => 'description',
            'start_date' => 'date de début',
            'end_date' => 'date de fin',
            'attachment' => 'fichier joint',
        ]);
    }

    /**
     * @param  array<int, array{name: string, value: string}>  $fields
     * @return array<string, string>
     */
    private function customFields(array $fields): array
    {
        $customFields = [];

        foreach ($fields as $field) {
            $customFields[trim($field['name'])] = trim($field['value']);
        }

        return $customFields;
    }

    private function storeAttachment(Request $request, User $prestataire): string
    {
        $attachment = $request->file('attachment');
        $path = $attachment?->store('prestataires/'.$prestataire->id, 'documents_private');

        if (! is_string($path)) {
            throw new RuntimeException('Le fichier joint n’a pas pu être enregistré.');
        }

        return $path;
    }

    private function notifyReviewers(Document $document, string $event): void
    {
        $document->load(['prestataire:id,name', 'safetyTeamMembers:id,document_id,name']);
        $reviewers = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'gestionnaire'))
            ->get();

        Notification::send($reviewers, new DocumentWorkflowNotification($document, $event));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data = []): View
    {
        return view($view, $data + [
            'roleLabel' => 'Prestataire',
            'routePrefix' => 'prestataire',
            'navigation' => self::NAVIGATION,
        ]);
    }
}
