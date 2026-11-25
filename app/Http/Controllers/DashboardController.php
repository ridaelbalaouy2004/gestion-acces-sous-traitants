<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyMachinery;
use App\Models\CompanyTooling;
use App\Models\Document;
use App\Models\SafetyTeamMember;
use App\Models\SharedRequiredDocumentsGuide;
use App\Models\User;
use App\Services\SharedRequiredDocumentsGuideService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const NAVIGATION = [
        'prestataire' => [
            ['label' => 'Tableau de bord', 'route' => 'prestataire.dashboard', 'icon' => 'dashboard'],
            ['label' => 'Mes entreprises', 'route' => 'prestataire.companies.index', 'icon' => 'building'],
            ['label' => 'Mon équipe', 'route' => 'prestataire.employees.index', 'icon' => 'users'],
            ['label' => 'Mes engins', 'route' => 'prestataire.machinery.index', 'icon' => 'truck'],
            ['label' => 'Mes outillages', 'route' => 'prestataire.toolings.index', 'icon' => 'tool'],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
            ['label' => 'Mon profil', 'route' => 'prestataire.profile', 'icon' => 'user'],
        ],
        'gestionnaire' => [
            ['label' => 'Tableau de bord', 'route' => 'gestionnaire.dashboard', 'icon' => 'dashboard'],
            ['label' => 'Gestion des entreprises', 'route' => 'gestionnaire.companies.index', 'icon' => 'building'],
            ['label' => 'Gestion des équipes', 'route' => 'gestionnaire.employees.index', 'icon' => 'users'],
            ['label' => 'Gestion des engins', 'route' => 'gestionnaire.machinery.index', 'icon' => 'truck'],
            ['label' => 'Gestion des outillages', 'route' => 'gestionnaire.toolings.index', 'icon' => 'tool'],
            ['label' => 'Gestion des Prestataires', 'route' => 'gestionnaire.prestataires.index', 'icon' => 'users'],
            ['label' => 'Guide des documents requis', 'route' => 'gestionnaire.guides.required-documents.index', 'icon' => 'documents'],
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
        'gestionnaire.profile' => ['role' => 'gestionnaire', 'title' => 'Mon profil'],
        'responsable-securite.profile' => ['role' => 'responsable_securite', 'title' => 'Mon profil'],
    ];

    public function prestataire(SharedRequiredDocumentsGuideService $guideService): View
    {
        $prestataire = auth()->user();
        abort_unless($prestataire instanceof User, 403);
        $guide = $guideService->current();
        $guideAvailable = $guide !== null && $guideService->exists($guide);
        $companies = $prestataire->companies();
        $employeeCount = CompanyEmployee::query()
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->count();
        $machineryCount = CompanyMachinery::query()
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->count();
        $toolingCount = CompanyTooling::query()
            ->whereHas('company', fn (Builder $query) => $query->where('prestataire_id', $prestataire->getKey()))
            ->count();

        return $this->dashboardView(
            role: 'prestataire',
            roleLabel: 'Prestataire',
            stats: [
                'companies' => $companies->count(),
                'employees' => $employeeCount,
                'machinery' => $machineryCount,
                'toolings' => $toolingCount,
            ],
            documents: collect(),
            showPrestataire: false,
            requiredDocumentsGuide: $guide,
            requiredDocumentsGuideAvailable: $guideAvailable,
        );
    }

    public function gestionnaire(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $accessiblePrestataires = $user->accessiblePrestataires()->select('users.id');

        $stats = [];
        $itemsToReview = collect();

        if ($user->hasPermission('entreprises.view')) {
            $companyQuery = Company::query()
                ->visibleToManager()
                ->whereIn('prestataire_id', $accessiblePrestataires);
            $stats['companies'] = $companyQuery->count();
            $stats['pending_companies'] = (clone $companyQuery)->where('status', Company::STATUS_PENDING)->count();

            $itemsToReview = $itemsToReview->merge((clone $companyQuery)
                ->where('status', Company::STATUS_PENDING)
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get(['id', 'name', 'status'])
                ->map(fn (Company $company): array => [
                    'title' => 'Entreprise à examiner : '.$company->name,
                    'route' => 'gestionnaire.companies.show',
                    'route_parameters' => [$company->getKey()],
                    'label' => 'Entreprise',
                ]));
        } else {
            $stats['companies'] = 0;
            $stats['pending_companies'] = 0;
        }

        if ($user->hasPermission('employees.view')) {
            $employeeQuery = CompanyEmployee::query()
                ->visibleToManager()
                ->whereHas('company', fn (Builder $query) => $query
                    ->whereIn('prestataire_id', $accessiblePrestataires));
            $stats['employees'] = $employeeQuery->count();
            $stats['pending_employees'] = (clone $employeeQuery)->where('status', CompanyEmployee::STATUS_PENDING)->count();

            $itemsToReview = $itemsToReview->merge((clone $employeeQuery)
                ->where('status', CompanyEmployee::STATUS_PENDING)
                ->with('company:id,name')
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get(['id', 'company_id', 'first_name', 'last_name', 'status'])
                ->map(fn (CompanyEmployee $employee): array => [
                    'title' => 'Employé à examiner : '.trim($employee->first_name.' '.$employee->last_name),
                    'route' => 'gestionnaire.employees.show',
                    'route_parameters' => [$employee->getKey()],
                    'label' => 'Employé',
                ]));
        } else {
            $stats['employees'] = 0;
            $stats['pending_employees'] = 0;
        }

        if ($user->hasPermission('machinery.view')) {
            $machineryQuery = CompanyMachinery::query()
                ->visibleToManager()
                ->whereHas('company', fn (Builder $query) => $query
                    ->whereIn('prestataire_id', $accessiblePrestataires));
            $stats['machinery'] = $machineryQuery->count();
            $stats['pending_machinery'] = (clone $machineryQuery)->where('status', CompanyMachinery::STATUS_PENDING)->count();

            $itemsToReview = $itemsToReview->merge((clone $machineryQuery)
                ->where('status', CompanyMachinery::STATUS_PENDING)
                ->with('company:id,name')
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get(['id', 'company_id', 'designation', 'status'])
                ->map(fn (CompanyMachinery $machinery): array => [
                    'title' => 'Engin à examiner : '.$machinery->designation,
                    'route' => 'gestionnaire.machinery.show',
                    'route_parameters' => [$machinery->getKey()],
                    'label' => 'Engin',
                ]));
        } else {
            $stats['machinery'] = 0;
            $stats['pending_machinery'] = 0;
        }

        if ($user->hasPermission('toolings.view')) {
            $toolingQuery = CompanyTooling::query()
                ->visibleToManager()
                ->whereHas('company', fn (Builder $query) => $query
                    ->whereIn('prestataire_id', $accessiblePrestataires));
            $stats['toolings'] = $toolingQuery->count();
            $stats['pending_toolings'] = (clone $toolingQuery)->where('status', CompanyTooling::STATUS_PENDING)->count();

            $itemsToReview = $itemsToReview->merge((clone $toolingQuery)
                ->where('status', CompanyTooling::STATUS_PENDING)
                ->with('company:id,name')
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get(['id', 'company_id', 'designation', 'status'])
                ->map(fn (CompanyTooling $tooling): array => [
                    'title' => 'Outillage à examiner : '.$tooling->designation,
                    'route' => 'gestionnaire.toolings.show',
                    'route_parameters' => [$tooling->getKey()],
                    'label' => 'Outillage',
                ]));
        } else {
            $stats['toolings'] = 0;
            $stats['pending_toolings'] = 0;
        }

        $stats['pending_total'] = array_sum([
            $stats['pending_companies'] ?? 0,
            $stats['pending_employees'] ?? 0,
            $stats['pending_machinery'] ?? 0,
            $stats['pending_toolings'] ?? 0,
        ]);

        $shortcuts = [];

        if ($user->hasPermission('entreprises.view')) {
            $shortcuts[] = ['label' => 'Gestion des entreprises', 'route' => 'gestionnaire.companies.index', 'tone' => 'blue'];
        }

        if ($user->hasPermission('employees.view')) {
            $shortcuts[] = ['label' => 'Gestion des équipes', 'route' => 'gestionnaire.employees.index', 'tone' => 'amber'];
        }

        if ($user->hasPermission('machinery.view')) {
            $shortcuts[] = ['label' => 'Gestion des engins', 'route' => 'gestionnaire.machinery.index', 'tone' => 'blue'];
        }

        if ($user->hasPermission('toolings.view')) {
            $shortcuts[] = ['label' => 'Gestion des outillages', 'route' => 'gestionnaire.toolings.index', 'tone' => 'blue'];
        }

        if ($user->hasPermission('prestataires.view')) {
            $shortcuts[] = ['label' => 'Gestion des Prestataires', 'route' => 'gestionnaire.prestataires.index', 'tone' => 'blue'];
        } elseif ($user->hasPermission('prestataires.create')) {
            $shortcuts[] = ['label' => 'Créer un Prestataire', 'route' => 'gestionnaire.prestataires.create', 'tone' => 'blue'];
        }

        if ($user->isPrimaryManager()) {
            $shortcuts[] = ['label' => 'Employés Gestionnaire', 'route' => 'gestionnaire.employes.index', 'tone' => 'blue'];
            $shortcuts[] = ['label' => 'Gérer les dossiers pour un Prestataire', 'route' => 'gestionnaire.delegated.index', 'tone' => 'blue'];
        }

        $recentNotifications = $user->visibleNotifications()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return $this->dashboardView(
            role: 'gestionnaire',
            roleLabel: 'Gestionnaire',
            stats: $stats,
            documents: collect(),
            showPrestataire: false,
            shortcuts: $shortcuts,
            recentNotifications: $recentNotifications,
            itemsToReview: $itemsToReview->take(6)->values(),
        );
    }

    public function responsableSecurite(): View
    {
        $validatedDocuments = Document::query()->where('status', Document::STATUS_VALIDATED);
        $validatedCount = (clone $validatedDocuments)->count();
        $validatedMembers = SafetyTeamMember::query()->where('status', SafetyTeamMember::STATUS_VALIDATED);

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
            memberStats: $this->memberStats($validatedMembers),
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
            'navigation' => $page['role'] === 'gestionnaire'
                ? self::navigationForUser(auth()->user())
                : self::NAVIGATION[$page['role']],
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
     * @return array<int, array{label: string, route: string, icon: string}>
     */
    public static function navigationForUser(mixed $user): array
    {
        abort_unless($user instanceof User && $user->roles()->where('name', 'gestionnaire')->exists(), 403);

        $navigation = array_values(array_filter(self::NAVIGATION['gestionnaire'], function (array $item) use ($user): bool {
            $permission = match (true) {
                str_starts_with($item['route'], 'gestionnaire.companies.') => 'entreprises.view',
                str_starts_with($item['route'], 'gestionnaire.employees.') => 'employees.view',
                str_starts_with($item['route'], 'gestionnaire.machinery.') => 'machinery.view',
                str_starts_with($item['route'], 'gestionnaire.toolings.') => 'toolings.view',
                str_starts_with($item['route'], 'gestionnaire.guides.') => 'prestataires.view',
                str_starts_with($item['route'], 'gestionnaire.prestataires.') => 'prestataires.view',
                default => null,
            };

            return $permission === null || $user->hasPermission($permission);
        }));

        if ($user->isPrimaryManager()) {
            $navigation[] = ['label' => 'Employés Gestionnaire', 'route' => 'gestionnaire.employes.index', 'icon' => 'users'];
            $navigation[] = ['label' => 'Gestion déléguée des Prestataires', 'route' => 'gestionnaire.delegated.index', 'icon' => 'users'];
        } elseif ($user->hasPermission('prestataires.create') && ! $user->hasPermission('prestataires.view')) {
            $navigation[] = ['label' => 'Créer un Prestataire', 'route' => 'gestionnaire.prestataires.create', 'icon' => 'users'];
        }

        return $navigation;
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
        ?array $memberStats = null,
        ?array $shortcuts = null,
        mixed $recentNotifications = null,
        ?Collection $itemsToReview = null,
        ?SharedRequiredDocumentsGuide $requiredDocumentsGuide = null,
        bool $requiredDocumentsGuideAvailable = false,
    ): View {
        $viewName = $role === 'responsable_securite'
            ? 'responsable-securite.dashboard'
            : "{$role}.dashboard";

        return view($viewName, [
            'role' => $role,
            'routePrefix' => $role === 'responsable_securite' ? 'responsable-securite' : $role,
            'roleLabel' => $roleLabel,
            'navigation' => $role === 'gestionnaire'
                ? self::navigationForUser(auth()->user())
                : self::NAVIGATION[$role],
            'stats' => $stats,
            'documents' => $documents,
            'showPrestataire' => $showPrestataire,
            'memberStats' => $memberStats,
            'shortcuts' => $shortcuts,
            'recentNotifications' => $recentNotifications,
            'itemsToReview' => $itemsToReview,
            'requiredDocumentsGuide' => $requiredDocumentsGuide,
            'requiredDocumentsGuideAvailable' => $requiredDocumentsGuideAvailable,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function memberStats(Builder $members): array
    {
        $counts = (clone $members)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count): int => (int) $count);

        return [
            'total' => $counts->sum(),
            'validated' => $counts->get(SafetyTeamMember::STATUS_VALIDATED, 0),
        ];
    }
}
