<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const NAVIGATION = [
        'prestataire' => [
            ['label' => 'Tableau de bord', 'route' => 'prestataire.dashboard', 'icon' => 'dashboard'],
            ['label' => 'Mes documents', 'route' => 'prestataire.documents.index', 'icon' => 'documents'],
            ['label' => 'Nouveau document', 'route' => 'prestataire.documents.create', 'icon' => 'plus'],
            ['label' => 'Mon équipe de sécurité', 'route' => 'prestataire.safety-team', 'icon' => 'users'],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
            ['label' => 'Mon profil', 'route' => 'prestataire.profile', 'icon' => 'user'],
        ],
        'gestionnaire' => [
            ['label' => 'Tableau de bord', 'route' => 'gestionnaire.dashboard', 'icon' => 'dashboard'],
            ['label' => 'Documents', 'route' => 'gestionnaire.documents.index', 'icon' => 'documents'],
            ['label' => 'Documents en attente', 'route' => 'gestionnaire.documents.pending', 'icon' => 'clock'],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
            ['label' => 'Mon profil', 'route' => 'gestionnaire.profile', 'icon' => 'user'],
        ],
        'responsable_securite' => [
            ['label' => 'Tableau de bord', 'route' => 'responsable-securite.dashboard', 'icon' => 'dashboard'],
            ['label' => 'Documents validés', 'route' => 'responsable-securite.documents.index', 'icon' => 'check'],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
            ['label' => 'Mon profil', 'route' => 'responsable-securite.profile', 'icon' => 'user'],
        ],
    ];

    private const PLACEHOLDER_PAGES = [
        'prestataire.profile' => ['role' => 'prestataire', 'title' => 'Mon profil'],
        'gestionnaire.documents.validated' => ['role' => 'gestionnaire', 'title' => 'Documents validés'],
        'gestionnaire.documents.rejected' => ['role' => 'gestionnaire', 'title' => 'Documents rejetés'],
        'gestionnaire.profile' => ['role' => 'gestionnaire', 'title' => 'Mon profil'],
        'responsable-securite.profile' => ['role' => 'responsable_securite', 'title' => 'Mon profil'],
    ];

    public function prestataire(): View
    {
        $documents = auth()->user()->documents();

        return $this->dashboardView(
            role: 'prestataire',
            roleLabel: 'Prestataire',
            stats: $this->documentStats($documents->getQuery()),
            documents: $documents->latest()->limit(5)->get([
                'id',
                'title',
                'reference_number',
                'status',
                'created_at',
            ]),
            showPrestataire: false,
        );
    }

    public function gestionnaire(): View
    {
        return $this->dashboardView(
            role: 'gestionnaire',
            roleLabel: 'Gestionnaire',
            stats: $this->documentStats(Document::query()),
            documents: Document::query()
                ->where(function (Builder $query): void {
                    $query->where('status', '!=', Document::STATUS_PENDING)
                        ->orWhereNotNull('submitted_at');
                })
                ->with('prestataire:id,name')
                ->orderByRaw('COALESCE(submitted_at, created_at) DESC')
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'prestataire_id', 'title', 'reference_number', 'status', 'submitted_at', 'created_at']),
            showPrestataire: true,
        );
    }

    public function responsableSecurite(): View
    {
        $validatedDocuments = Document::query()->where('status', Document::STATUS_VALIDATED);
        $validatedCount = (clone $validatedDocuments)->count();

        return $this->dashboardView(
            role: 'responsable_securite',
            roleLabel: 'Responsable sécurité',
            stats: [
                'total' => $validatedCount,
                'pending' => 0,
                'validated' => $validatedCount,
                'rejected' => 0,
            ],
            documents: (clone $validatedDocuments)
                ->with('prestataire:id,name')
                ->orderByDesc('validated_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'prestataire_id', 'title', 'reference_number', 'status', 'validated_at']),
            showPrestataire: true,
        );
    }

    public function placeholder(Request $request): View
    {
        $routeName = $request->route()?->getName();
        $page = is_string($routeName) ? (self::PLACEHOLDER_PAGES[$routeName] ?? null) : null;

        abort_if($page === null, 404);

        $roleLabels = [
            'prestataire' => 'Prestataire',
            'gestionnaire' => 'Gestionnaire',
            'responsable_securite' => 'Responsable sécurité',
        ];

        return view('shared.placeholder', [
            'role' => $page['role'],
            'routePrefix' => $page['role'] === 'responsable_securite' ? 'responsable-securite' : $page['role'],
            'roleLabel' => $roleLabels[$page['role']],
            'pageTitle' => $page['title'],
            'navigation' => self::NAVIGATION[$page['role']],
        ]);
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string}>
     */
    public static function navigationForRole(string $role): array
    {
        abort_unless(isset(self::NAVIGATION[$role]), 403);

        return self::NAVIGATION[$role];
    }

    /**
     * @param  array<string, int>  $stats
     * @param  Collection<int, Document>  $documents
     */
    private function dashboardView(
        string $role,
        string $roleLabel,
        array $stats,
        Collection $documents,
        bool $showPrestataire,
    ): View {
        $viewName = $role === 'responsable_securite'
            ? 'responsable-securite.dashboard'
            : "{$role}.dashboard";

        return view($viewName, [
            'role' => $role,
            'routePrefix' => $role === 'responsable_securite' ? 'responsable-securite' : $role,
            'roleLabel' => $roleLabel,
            'navigation' => self::NAVIGATION[$role],
            'stats' => $stats,
            'documents' => $documents,
            'showPrestataire' => $showPrestataire,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function documentStats(Builder $documents): array
    {
        $counts = (clone $documents)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count): int => (int) $count);

        return [
            'total' => $counts->sum(),
            'pending' => $counts->get(Document::STATUS_PENDING, 0),
            'validated' => $counts->get(Document::STATUS_VALIDATED, 0),
            'rejected' => $counts->get(Document::STATUS_REJECTED, 0),
        ];
    }
}
