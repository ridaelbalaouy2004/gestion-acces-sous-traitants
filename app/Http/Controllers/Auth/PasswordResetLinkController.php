<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use RuntimeException;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Veuillez saisir votre adresse e-mail.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
            'email.max' => 'L’adresse e-mail est trop longue.',
        ]);

        $status = Password::sendResetLink($validated);

        if (! in_array($status, [
            Password::RESET_LINK_SENT,
            Password::INVALID_USER,
            Password::RESET_THROTTLED,
        ], true)) {
            throw new RuntimeException("Unexpected password reset link status [{$status}].");
        }

        return to_route('password.request')
            ->with('status', 'Si un compte est associé à cette adresse, un lien de réinitialisation va lui être envoyé.');
    }
}
