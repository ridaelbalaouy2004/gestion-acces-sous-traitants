<?php

namespace App\Http\Controllers\ResponsableSecurite;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const NAVIGATION = [
        ['label' => 'Tableau de bord', 'route' => 'responsable-securite.dashboard', 'icon' => 'dashboard'],
        ['label' => 'Documents validés', 'route' => 'responsable-securite.documents.index', 'icon' => 'check'],
        ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
        ['label' => 'Mon profil', 'route' => 'responsable-securite.profile', 'icon' => 'user'],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
        ], [
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'sort.in' => 'Le tri sélectionné est invalide.',
        ]);

        $search = isset($filters['search']) && $filters['search'] !== ''
            ? '%'.$filters['search'].'%'
            : null;
        $sortDirection = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        $documents = Document::query()
            ->where('status', Document::STATUS_VALIDATED)
            ->with([
                'prestataire:id,name',
                'validatedBy:id,name',
                'safetyTeamMembers:id,document_id,name,cin,position,email,phone',
            ])
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
            ->orderBy('validated_at', $sortDirection)
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return $this->page('responsable-securite.documents.index', [
            'documents' => $documents,
            'filters' => $filters,
        ]);
    }

    public function show(Document $document): View
    {
        $this->ensureValidated($document);

        $document->load([
            'prestataire:id,name,email',
            'validatedBy:id,name,email',
            'safetyTeamMembers',
        ]);

        return $this->page('responsable-securite.documents.show', [
            'document' => $document,
        ]);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->ensureValidated($document);

        $path = $document->attachment_path;

        abort_unless(
            is_string($path) && $path !== '' && Storage::disk('documents_private')->exists($path),
            404,
        );

        return Storage::disk('documents_private')->download($path, basename($path));
    }

    private function ensureValidated(Document $document): void
    {
        abort_unless($document->status === Document::STATUS_VALIDATED, 404);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data = []): View
    {
        return view($view, $data + [
            'roleLabel' => 'Responsable sécurité',
            'routePrefix' => 'responsable-securite',
            'navigation' => self::NAVIGATION,
        ]);
    }
}
