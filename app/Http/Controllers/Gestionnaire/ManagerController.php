<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\ManagerSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ManagerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'status.in' => 'Le statut sélectionné est invalide.',
        ]);
        $principalId = ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->value('principal_user_id');

        $managers = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'gestionnaire'))
            ->when($principalId !== null, fn (Builder $query) => $query->where('id', '!=', $principalId))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when(isset($filters['search']) && $filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%'));
            })
            ->withCount(['assignedPrestataires', 'permissions'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return $this->page('gestionnaire.employes.index', [
            'managers' => $managers,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return $this->formView('gestionnaire.employes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
            'prestataire_ids' => ['sometimes', 'array'],
            'prestataire_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.required' => 'Le mot de passe temporaire est obligatoire.',
            'password.min' => 'Le mot de passe temporaire doit contenir au moins 12 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe temporaire ne correspond pas.',
            'permission_ids.array' => 'La sélection des permissions est invalide.',
            'permission_ids.*.exists' => 'Une permission sélectionnée est invalide.',
            'prestataire_ids.array' => 'La sélection des Prestataires est invalide.',
            'prestataire_ids.*.exists' => 'Un Prestataire sélectionné est invalide.',
        ]);
        $permissionIds = $this->validatedPermissionIds($validated['permission_ids'] ?? []);
        $prestataireIds = $this->validatedPrestataireIds($validated['prestataire_ids'] ?? []);
        $role = Role::query()->where('name', 'gestionnaire')->firstOrFail();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $manager = DB::transaction(function () use ($validated, $permissionIds, $prestataireIds, $role, $actor): User {
            $manager = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $manager->roles()->attach($role);
            $manager->permissions()->sync($permissionIds);
            $this->syncAssignments($manager, $prestataireIds, $actor);

            return $manager;
        });

        return to_route('gestionnaire.employes.show', $manager)
            ->with('status', 'Le compte Gestionnaire a été créé. Le mot de passe temporaire n’est pas conservé ni réaffiché.');
    }

    public function show(Request $request, int $manager): View
    {
        $manager = $this->findEmployee($manager);
        $manager->load(['permissions', 'assignedPrestataires:id,name,email']);
        $filters = $request->validate([
            'assignment_search' => ['nullable', 'string', 'max:100'],
            'assignment_status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [
            'assignment_search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'assignment_status.in' => 'Le statut sélectionné est invalide.',
        ]);
        $prestataires = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'prestataire'))
            ->when(isset($filters['assignment_search']) && $filters['assignment_search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('name', 'like', '%'.$filters['assignment_search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['assignment_search'].'%'));
            })
            ->when(isset($filters['assignment_status']), fn (Builder $query) => $query->where('is_active', $filters['assignment_status'] === 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active']);
        $assignmentHistory = DB::table('manager_prestataire_assignment_history')
            ->join('users as prestataires', 'prestataires.id', '=', 'manager_prestataire_assignment_history.prestataire_id')
            ->join('users as actors', 'actors.id', '=', 'manager_prestataire_assignment_history.actor_id')
            ->where('manager_prestataire_assignment_history.manager_id', $manager->getKey())
            ->orderByDesc('manager_prestataire_assignment_history.created_at')
            ->orderByDesc('manager_prestataire_assignment_history.id')
            ->get([
                'manager_prestataire_assignment_history.action',
                'manager_prestataire_assignment_history.created_at',
                'prestataires.name as prestataire_name',
                'actors.name as actor_name',
            ]);

        return $this->formView('gestionnaire.employes.show', [
            'manager' => $manager,
            'principalId' => ManagerSettings::query()->whereKey(ManagerSettings::SINGLETON_ID)->value('principal_user_id'),
            'prestataires' => $prestataires,
            'assignmentFilters' => $filters,
            'assignmentHistory' => $assignmentHistory,
        ]);
    }

    public function edit(int $manager): View
    {
        $manager = $this->findEmployee($manager);
        $manager->load(['permissions', 'assignedPrestataires:id']);

        return $this->formView('gestionnaire.employes.edit', [
            'manager' => $manager,
        ]);
    }

    public function update(Request $request, int $manager): RedirectResponse
    {
        $manager = $this->findEmployee($manager);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($manager)],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ]);
        $manager->update($validated);

        return to_route('gestionnaire.employes.show', $manager)
            ->with('status', 'Les informations du Gestionnaire ont été mises à jour.');
    }

    public function updatePermissions(Request $request, int $manager): RedirectResponse
    {
        $manager = $this->findEmployee($manager);
        $moduleNames = array_values(array_unique(array_column(Permission::CATALOG, 'module')));
        $validated = $request->validate([
            'permission_modules' => ['sometimes', 'array', 'min:1'],
            'permission_modules.*' => ['required', 'string', 'distinct', Rule::in($moduleNames)],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
        ]);
        $permissionIds = $this->validatedPermissionIds($validated['permission_ids'] ?? []);

        if (array_key_exists('permission_modules', $validated)) {
            $modules = $validated['permission_modules'];
            $selectedPermissionCount = Permission::query()
                ->whereKey($permissionIds)
                ->whereIn('module', $modules)
                ->count();

            if ($selectedPermissionCount !== count($permissionIds)) {
                throw ValidationException::withMessages([
                    'permission_ids' => 'Une permission sélectionnée ne correspond pas aux modules modifiés.',
                ]);
            }

            $preservedPermissionIds = $manager->permissions()
                ->whereNotIn('module', $modules)
                ->pluck('permissions.id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all();
            $permissionIds = array_values(array_unique(array_merge($preservedPermissionIds, $permissionIds)));
        }

        DB::transaction(fn () => $manager->permissions()->sync($permissionIds));

        return to_route('gestionnaire.employes.show', $manager)
            ->with('status', 'Les permissions du Gestionnaire ont été mises à jour.');
    }

    public function updateAssignments(Request $request, int $manager): RedirectResponse
    {
        $manager = $this->findEmployee($manager);
        $validated = $request->validate([
            'prestataire_ids' => ['sometimes', 'array'],
            'prestataire_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ]);
        $prestataireIds = $this->validatedPrestataireIds($validated['prestataire_ids'] ?? []);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DB::transaction(fn () => $this->syncAssignments($manager, $prestataireIds, $actor));

        return to_route('gestionnaire.employes.show', $manager)
            ->with('status', 'Les affectations du Gestionnaire ont été mises à jour.');
    }

    public function toggleStatus(int $manager): RedirectResponse
    {
        $manager = $this->findEmployee($manager);
        $isActive = DB::transaction(function () use ($manager): bool {
            $lockedManager = User::query()->lockForUpdate()->findOrFail($manager->getKey());
            $this->ensureEmployee($lockedManager);
            $lockedManager->is_active = ! $lockedManager->is_active;
            $lockedManager->save();

            return $lockedManager->is_active;
        });

        return to_route('gestionnaire.employes.show', $manager)
            ->with('status', $isActive
                ? 'Le compte Gestionnaire a été réactivé.'
                : 'Le compte Gestionnaire a été désactivé.');
    }

    public function transferPrincipal(Request $request, int $manager): RedirectResponse
    {
        $manager = $this->findEmployee($manager);
        abort_unless($manager->is_active, 422, 'Un compte inactif ne peut pas devenir Gestionnaire Principal.');
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isPrimaryManager(), 403);

        DB::transaction(function () use ($actor, $manager): void {
            $settings = ManagerSettings::query()
                ->whereKey(ManagerSettings::SINGLETON_ID)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedManager = User::query()->lockForUpdate()->findOrFail($manager->getKey());

            abort_unless((int) $settings->principal_user_id === (int) $actor->getKey(), 409);
            abort_unless(
                $lockedManager->is_active
                    && $lockedManager->roles()->where('name', 'gestionnaire')->exists()
                    && ! $lockedManager->isPrimaryManager(),
                422,
                'Seul un autre Gestionnaire actif peut devenir Gestionnaire Principal.',
            );

            DB::table('manager_principal_transfers')->insert([
                'previous_user_id' => $actor->getKey(),
                'new_user_id' => $lockedManager->getKey(),
                'actor_id' => $actor->getKey(),
                'created_at' => now(),
            ]);

            $settings->principal_user_id = $lockedManager->getKey();
            $settings->save();
        });

        return to_route('gestionnaire.dashboard')
            ->with('status', 'La responsabilité de Gestionnaire Principal a été transférée.');
    }

    private function ensureEmployee(User $manager): void
    {
        abort_unless(
            $manager->roles()->where('name', 'gestionnaire')->exists() && ! $manager->isPrimaryManager(),
            404,
        );
    }

    private function findEmployee(int $managerId): User
    {
        $manager = User::query()->findOrFail($managerId);
        $this->ensureEmployee($manager);

        return $manager;
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     * @return array<int, int>
     */
    private function validatedPermissionIds(array $permissionIds): array
    {
        $allowedIds = Permission::query()
            ->whereIn('name', array_keys(Permission::CATALOG))
            ->whereKey($permissionIds)
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if (count($allowedIds) !== count($permissionIds)) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Une ou plusieurs permissions sélectionnées sont invalides.',
            ]);
        }

        return $allowedIds;
    }

    /**
     * @param  array<int, int|string>  $prestataireIds
     * @return array<int, int>
     */
    private function validatedPrestataireIds(array $prestataireIds): array
    {
        $allowedIds = User::query()
            ->whereKey($prestataireIds)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'prestataire'))
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if (count($allowedIds) !== count($prestataireIds)) {
            throw ValidationException::withMessages([
                'prestataire_ids' => 'Une ou plusieurs affectations ne correspondent pas à un Prestataire.',
            ]);
        }

        return $allowedIds;
    }

    /**
     * @param  array<int, int>  $prestataireIds
     */
    private function syncAssignments(User $manager, array $prestataireIds, User $actor): void
    {
        $currentIds = $manager->assignedPrestataires()->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
        $removeIds = array_values(array_diff($currentIds, $prestataireIds));
        $addIds = array_values(array_diff($prestataireIds, $currentIds));

        if ($removeIds !== []) {
            $manager->assignedPrestataires()->detach($removeIds);
        }

        foreach ($removeIds as $prestataireId) {
            DB::table('manager_prestataire_assignment_history')->insert([
                'manager_id' => $manager->getKey(),
                'prestataire_id' => $prestataireId,
                'actor_id' => $actor->getKey(),
                'action' => 'removed',
                'created_at' => now(),
            ]);
        }

        foreach ($addIds as $prestataireId) {
            $manager->assignedPrestataires()->attach($prestataireId, ['assigned_by' => $actor->getKey()]);
            DB::table('manager_prestataire_assignment_history')->insert([
                'manager_id' => $manager->getKey(),
                'prestataire_id' => $prestataireId,
                'actor_id' => $actor->getKey(),
                'action' => 'assigned',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formView(string $view, array $data = []): View
    {
        return $this->page($view, $data + [
            'moduleLabels' => [
                'prestataires' => 'Prestataires',
                'documents' => 'Documents',
                'entreprises' => 'Entreprises',
                'employees' => 'Mon équipe / Employés',
                'machinery' => 'Mes engins',
                'toolings' => 'Mes outillages',
            ],
            'permissionsByModule' => Permission::query()
                ->whereIn('name', array_keys(Permission::CATALOG))
                ->orderBy('module')
                ->orderBy('name')
                ->get()
                ->groupBy('module'),
            'prestataires' => User::query()
                ->whereHas('roles', fn (Builder $query) => $query->where('name', 'prestataire'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data = []): View
    {
        return view($view, $data + [
            'roleLabel' => 'Gestionnaire Principal',
            'routePrefix' => 'gestionnaire',
            'navigation' => DashboardController::navigationForUser(auth()->user()),
        ]);
    }
}
