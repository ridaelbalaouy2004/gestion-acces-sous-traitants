<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! $user->must_change_password) {
            return to_route($this->dashboardRoute($user));
        }

        return view('prestataire.password.edit', [
            'passwordRoutePrefix' => $this->routePrefix($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                Password::min(12),
                'confirmed',
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (is_string($value) && Hash::check($value, $user->password)) {
                        $fail('Le nouveau mot de passe doit être différent du mot de passe temporaire.');
                    }
                },
            ],
        ], [
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 12 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        return to_route($this->dashboardRoute($user))
            ->with('status', 'Votre mot de passe a été modifié avec succès.');
    }

    private function routePrefix(User $user): string
    {
        if ($user->roles()->where('name', 'responsable_securite')->exists()) {
            return 'responsable-securite';
        }

        if ($user->roles()->where('name', 'gestionnaire')->exists()) {
            return 'gestionnaire';
        }

        return 'prestataire';
    }

    private function dashboardRoute(User $user): string
    {
        return $this->routePrefix($user).'.dashboard';
    }
}
