<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrestatairePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            if ($user->roles()->where('name', 'prestataire')->exists()) {
                if (! $request->routeIs('prestataire.password.edit', 'prestataire.password.update', 'logout')) {
                    return to_route('prestataire.password.edit');
                }
            } elseif ($user->roles()->where('name', 'gestionnaire')->exists()) {
                if (! $request->routeIs('gestionnaire.password.edit', 'gestionnaire.password.update', 'logout')) {
                    return to_route('gestionnaire.password.edit');
                }
            } elseif ($user->roles()->where('name', 'responsable_securite')->exists()) {
                if (! $request->routeIs('responsable-securite.password.edit', 'responsable-securite.password.update', 'logout')) {
                    return to_route('responsable-securite.password.edit');
                }
            }
        }

        return $next($request);
    }
}
