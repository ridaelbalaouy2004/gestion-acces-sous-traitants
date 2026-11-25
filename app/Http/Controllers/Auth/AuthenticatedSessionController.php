<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private const ROLE_DASHBOARD_ROUTES = [
        'prestataire' => 'prestataire.dashboard',
        'gestionnaire' => 'gestionnaire.dashboard',
        'responsable_securite' => 'responsable-securite.dashboard',
    ];

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return view('auth.login');
        }

        $dashboardRoute = $this->destinationRouteFor($user);

        if ($dashboardRoute !== null) {
            return to_route($dashboardRoute);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->withErrors([
            'email' => 'Aucun rôle n’est associé à ce compte. Veuillez contacter un gestionnaire.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            $dashboardRoute = $this->destinationRouteFor($request->user());

            if ($dashboardRoute !== null) {
                return to_route($dashboardRoute);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors([
                'email' => 'Aucun rôle n’est associé à ce compte. Veuillez contacter un gestionnaire.',
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'email.required' => 'Veuillez saisir votre adresse e-mail.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
            'password.required' => 'Veuillez saisir votre mot de passe.',
        ]);

        if (! Auth::attempt(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ],
            $request->boolean('remember'),
        )) {
            return to_route('login')
                ->withErrors(['email' => 'Les identifiants sont incorrects ou le compte est désactivé.'])
                ->withInput($request->only('email'));
        }

        $authenticatedUser = $request->user();

        if ($authenticatedUser instanceof User && ! $authenticatedUser->isAccountActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')
                ->withErrors(['email' => 'Les identifiants sont incorrects ou le compte est désactivé.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $dashboardRoute = $this->destinationRouteFor($request->user());

        if ($dashboardRoute === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors([
                'email' => 'Aucun rôle n’est associé à ce compte. Veuillez contacter un gestionnaire.',
            ]);
        }

        return to_route($dashboardRoute);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    private function dashboardRouteFor(?Authenticatable $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $userRoleNames = $user->roles()->pluck('name')->all();

        foreach (self::ROLE_DASHBOARD_ROUTES as $roleName => $routeName) {
            if (in_array($roleName, $userRoleNames, true)) {
                return $routeName;
            }
        }

        return null;
    }

    private function destinationRouteFor(?Authenticatable $user): ?string
    {
        if (! $user instanceof User || ! $user->must_change_password) {
            return $this->dashboardRouteFor($user);
        }

        if ($user->roles()->where('name', 'prestataire')->exists()) {
            return 'prestataire.password.edit';
        }

        return $this->dashboardRouteFor($user);
    }
}
