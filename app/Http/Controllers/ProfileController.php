<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('profile.show', [
            'user' => $user,
            'roleKey' => $this->roleKey($user),
            'roleLabel' => $this->roleLabel($user),
            'routePrefix' => $this->routePrefix($user),
            'navigation' => $this->navigationForUser($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'L’adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return to_route($this->routePrefix($user).'.profile')
            ->with('status', 'Votre profil a été mis à jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                if (! Hash::check((string) $value, $user->password)) {
                    $fail('Le mot de passe actuel est incorrect.');
                }
            }],
            'password' => ['required', 'string', PasswordRule::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 12 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        return to_route($this->routePrefix($user).'.profile')
            ->with('status', 'Votre mot de passe a été modifié avec succès.');
    }

    private function roleKey(User $user): string
    {
        foreach (['prestataire', 'gestionnaire', 'responsable_securite'] as $roleName) {
            if ($user->roles()->where('name', $roleName)->exists()) {
                return $roleName;
            }
        }

        return 'prestataire';
    }

    private function roleLabel(User $user): string
    {
        return match ($this->roleKey($user)) {
            'prestataire' => 'Prestataire',
            'gestionnaire' => 'Gestionnaire',
            'responsable_securite' => 'Responsable sécurité',
            default => 'Utilisateur',
        };
    }

    private function routePrefix(User $user): string
    {
        return match ($this->roleKey($user)) {
            'gestionnaire' => 'gestionnaire',
            'responsable_securite' => 'responsable-securite',
            default => 'prestataire',
        };
    }

    private function navigationForUser(User $user): array
    {
        $role = $this->roleKey($user);

        if ($role === 'gestionnaire') {
            return DashboardController::navigationForUser($user);
        }

        return DashboardController::navigationForRole($role);
    }
}
