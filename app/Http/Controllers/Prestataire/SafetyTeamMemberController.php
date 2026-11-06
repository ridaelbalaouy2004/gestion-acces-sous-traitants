<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\SafetyTeamMember;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SafetyTeamMemberController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ], [
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
        ]);
        $prestataire = $this->currentPrestataire($request);
        $search = isset($validated['search']) && $validated['search'] !== ''
            ? '%'.$validated['search'].'%'
            : null;

        $members = SafetyTeamMember::query()
            ->whereHas('document', fn (Builder $query) => $query->whereBelongsTo($prestataire, 'prestataire'))
            ->with('document:id,prestataire_id,title,reference_number,status')
            ->when($search !== null, function (Builder $query) use ($search): void {
                $query->where(function (Builder $memberQuery) use ($search): void {
                    $memberQuery->where('name', 'like', $search)
                        ->orWhere('cin', 'like', $search)
                        ->orWhereHas('document', function (Builder $documentQuery) use ($search): void {
                            $documentQuery->where('title', 'like', $search)
                                ->orWhere('reference_number', 'like', $search);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return $this->page('prestataire.safety-team.index', [
            'members' => $members,
            'filters' => $validated,
        ]);
    }

    public function create(Request $request): View
    {
        $prestataire = $this->currentPrestataire($request);
        $documents = $prestataire->documents()
            ->where('status', Document::STATUS_VALIDATED)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'reference_number', 'status']);

        return $this->page('prestataire.safety-team.form', [
            'member' => null,
            'documents' => $documents,
            'pageTitle' => 'Ajouter un membre',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
        $validated = $request->validate($this->rules($prestataire, $request), [
            'document_id.exists' => 'Le document sélectionné ne vous appartient pas ou n’est pas validé.',
        ]);

        $member = DB::transaction(function () use ($prestataire, $validated): SafetyTeamMember {
            $document = $prestataire->documents()
                ->lockForUpdate()
                ->findOrFail($validated['document_id']);

            if ($document->status !== Document::STATUS_VALIDATED) {
                throw ValidationException::withMessages([
                    'document_id' => 'Le document sélectionné ne vous appartient pas ou n’est pas validé.',
                ]);
            }

            $member = $document->safetyTeamMembers()->create([
                'name' => $validated['name'],
                'cin' => $validated['cin'],
                'position' => $validated['position'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);

            $this->recordRevision(
                $document,
                $prestataire,
                DocumentRevision::TYPE_MEMBER_ADDED,
                $member->name,
                "Ajout d'un nouveau membre à l'équipe de sécurité. Une nouvelle vérification est nécessaire.",
            );

            return $member;
        });

        return to_route('prestataire.safety-team')
            ->with('status', 'Le membre a été ajouté à votre équipe de sécurité.');
    }

    public function edit(Request $request, int $member): View
    {
        $member = $this->ownedMember($request, $member);
        $this->ensureDocumentCanBeManaged($member->document);

        return $this->page('prestataire.safety-team.form', [
            'member' => $member,
            'documents' => collect([$member->document]),
            'pageTitle' => 'Modifier un membre',
        ]);
    }

    public function update(Request $request, int $member): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
        $member = $this->ownedMember($request, $member);
        $this->ensureDocumentCanBeManaged($member->document);
        $validated = $request->validate($this->rules(
            $prestataire,
            $request,
            $member,
        ));

        DB::transaction(function () use ($member, $validated, $prestataire): void {
            $document = $member->document()
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDocumentCanBeManaged($document);

            $member->fill([
                'name' => $validated['name'],
                'cin' => $validated['cin'],
                'position' => $validated['position'],
                'email' => $validated['email'] ?? $member->email,
                'phone' => $validated['phone'] ?? $member->phone,
            ]);

            if (! $member->isDirty()) {
                return;
            }

            $previousName = $member->getOriginal('name');
            $member->save();

            if ($document->status === Document::STATUS_PENDING && $document->submitted_at !== null) {
                $this->recordRevision(
                    $document,
                    $prestataire,
                    DocumentRevision::TYPE_MEMBER_UPDATED,
                    $member->name,
                    "Modification d'un membre de l'équipe de sécurité. Une nouvelle vérification est nécessaire.",
                    is_string($previousName) ? $previousName : null,
                );
            }
        });

        return to_route('prestataire.safety-team')
            ->with('status', 'Les informations du membre ont été mises à jour.');
    }

    public function destroy(Request $request, int $member): RedirectResponse
    {
        $prestataire = $this->currentPrestataire($request);
        $member = $this->ownedMember($request, $member);

        DB::transaction(function () use ($member, $prestataire): void {
            $document = $member->document()
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDocumentCanBeManaged($document);
            $memberName = $member->name;
            $member->delete();

            if ($document->status === Document::STATUS_PENDING && $document->submitted_at !== null) {
                $this->recordRevision(
                    $document,
                    $prestataire,
                    DocumentRevision::TYPE_MEMBER_DELETED,
                    $memberName,
                    "Suppression d'un membre de l'équipe de sécurité. Une nouvelle vérification est nécessaire.",
                );
            }
        });

        return to_route('prestataire.safety-team')
            ->with('status', 'Le membre a été supprimé de votre équipe de sécurité.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $prestataire, Request $request, ?SafetyTeamMember $member = null): array
    {
        return [
            'document_id' => [
                'required',
                'integer',
                ...($member === null ? [] : [Rule::in([$member->document_id])]),
                Rule::exists('documents', 'id')->where(
                    fn (QueryBuilder $query) => $query
                        ->where('prestataire_id', $prestataire->getKey())
                        ->where(
                            'status',
                            $member === null ? '=' : '!=',
                            Document::STATUS_VALIDATED,
                        ),
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cin' => [
                'required',
                'string',
                'max:255',
                Rule::unique('safety_team_members', 'cin')
                    ->where('document_id', $member?->document_id ?? $request->input('document_id'))
                    ->ignore($member?->getKey()),
            ],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function currentPrestataire(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function ownedMember(Request $request, int $memberId): SafetyTeamMember
    {
        $prestataire = $this->currentPrestataire($request);

        return SafetyTeamMember::query()
            ->whereHas('document', fn (Builder $query) => $query->whereBelongsTo($prestataire, 'prestataire'))
            ->with('document')
            ->findOrFail($memberId);
    }

    private function ensureDocumentCanBeManaged(Document $document): void
    {
        abort_unless(
            $document->status !== Document::STATUS_VALIDATED,
            409,
            'Les membres d’un document validé ne peuvent plus être modifiés.',
        );
    }

    private function recordRevision(
        Document $document,
        User $prestataire,
        string $type,
        string $memberName,
        string $reason,
        ?string $previousMemberName = null,
    ): void
    {
        $description = match ($type) {
            DocumentRevision::TYPE_MEMBER_ADDED => "Ajout du membre « {$memberName} » à l’équipe de sécurité.",
            DocumentRevision::TYPE_MEMBER_UPDATED => $previousMemberName !== null && $previousMemberName !== $memberName
                ? "Modification du membre « {$previousMemberName} » en « {$memberName} » dans l’équipe de sécurité."
                : "Modification du membre « {$memberName} » de l’équipe de sécurité.",
            DocumentRevision::TYPE_MEMBER_DELETED => "Suppression du membre « {$memberName} » de l’équipe de sécurité.",
            default => throw new \InvalidArgumentException('Type de révision inconnu.'),
        };

        $document->update([
            'status' => Document::STATUS_PENDING,
            'validated_by' => null,
            'validated_at' => null,
            'submitted_at' => now(),
        ]);
        $document->revisions()->create([
            'type' => $type,
            'description' => $description,
            'member_name' => $memberName,
            'user_id' => $prestataire->getKey(),
            'reason' => $reason,
        ]);
        $document->load(['prestataire:id,name', 'safetyTeamMembers:id,document_id,name']);

        $reviewers = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'gestionnaire'))
            ->get();

        Notification::send($reviewers, new DocumentWorkflowNotification(
            $document,
            DocumentWorkflowNotification::EVENT_REVISION_REQUESTED,
            $reason,
            $type,
            $description,
            $memberName,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data = []): View
    {
        return view($view, $data + [
            'roleLabel' => 'Prestataire',
            'routePrefix' => 'prestataire',
            'navigation' => DashboardController::navigationForRole('prestataire'),
        ]);
    }
}
