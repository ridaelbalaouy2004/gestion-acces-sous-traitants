<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Mail\RequiredDocumentsGuide;
use App\Models\Role;
use App\Models\User;
use App\Services\SharedRequiredDocumentsGuideService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PrestataireController extends Controller
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

        $search = $filters['search'] ?? null;

        $manager = $request->user();
        abort_unless($manager instanceof User, 403);

        $prestataires = $manager->accessiblePrestataires()
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when(
                isset($filters['status']),
                fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('gestionnaire.prestataires.index', $this->managerViewData([
            'prestataires' => $prestataires,
            'filters' => $filters,
        ]));
    }

    public function create(): View
    {
        return view('gestionnaire.prestataires.create', $this->managerViewData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', Password::min(12), 'confirmed'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.min' => 'Le mot de passe temporaire doit contenir au moins 12 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe temporaire ne correspond pas.',
        ]);

        $role = Role::query()->where('name', 'prestataire')->firstOrFail();
        $temporaryPassword = $validated['password'] ?? Str::password(24);

        $prestataire = DB::transaction(function () use ($validated, $role, $temporaryPassword): User {
            $prestataire = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $prestataire->roles()->attach($role);

            return $prestataire;
        });

        return to_route('gestionnaire.prestataires.show', $prestataire)
            ->with('status', 'Le compte Prestataire a été créé.')
            ->with('temporary_password', Crypt::encryptString($temporaryPassword))
            ->with('temporary_password_prestataire_id', $prestataire->getKey());
    }

    public function show(
        Request $request,
        User $prestataire,
        SharedRequiredDocumentsGuideService $guideService,
    ): Response {
        $this->ensurePrestataire($prestataire);
        $encryptedTemporaryPassword = null;
        $guide = $guideService->current();

        if ((int) $request->session()->get('temporary_password_prestataire_id') === $prestataire->getKey()) {
            $encryptedTemporaryPassword = $request->session()->pull('temporary_password');
            $request->session()->forget('temporary_password_prestataire_id');
        }

        return response()
            ->view('gestionnaire.prestataires.show', $this->managerViewData([
                'prestataire' => $prestataire,
                'temporaryPassword' => is_string($encryptedTemporaryPassword)
                    ? Crypt::decryptString($encryptedTemporaryPassword)
                    : null,
                'guide' => $guide,
                'guideAvailable' => $guide !== null && $guideService->exists($guide),
                'guideSendToken' => Str::random(64),
                'defaultSubject' => RequiredDocumentsGuide::DEFAULT_SUBJECT,
                'defaultMessage' => RequiredDocumentsGuide::DEFAULT_MESSAGE,
            ]))
            ->header('Cache-Control', 'no-store, private');
    }

    public function edit(User $prestataire): View
    {
        $this->ensurePrestataire($prestataire);

        return view('gestionnaire.prestataires.edit', $this->managerViewData([
            'prestataire' => $prestataire,
        ]));
    }

    public function update(Request $request, User $prestataire): RedirectResponse
    {
        $this->ensurePrestataire($prestataire);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($prestataire),
            ],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ]);

        $prestataire->update($validated);

        return to_route('gestionnaire.prestataires.show', $prestataire)
            ->with('status', 'Les informations du Prestataire ont été mises à jour.');
    }

    public function toggleStatus(User $prestataire): RedirectResponse
    {
        $this->ensurePrestataire($prestataire);

        $prestataire->is_active = ! $prestataire->is_active;
        $prestataire->save();

        $message = $prestataire->is_active
            ? 'Le compte Prestataire a été réactivé.'
            : 'Le compte Prestataire a été désactivé.';

        return to_route('gestionnaire.prestataires.show', $prestataire)
            ->with('status', $message);
    }

    private function ensurePrestataire(User $user): void
    {
        $manager = auth()->user();
        abort_unless(
            $user->roles()->where('name', 'prestataire')->exists()
                && $manager instanceof User
                && $manager->accessiblePrestataires()->whereKey($user->getKey())->exists(),
            404,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function managerViewData(array $data = []): array
    {
        return array_merge([
            'role' => 'gestionnaire',
            'routePrefix' => 'gestionnaire',
            'roleLabel' => 'Gestionnaire',
            'navigation' => DashboardController::navigationForUser(auth()->user()),
        ], $data);
    }
}
