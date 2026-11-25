<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_RESET_MESSAGE = 'Si un compte est associé à cette adresse, un lien de réinitialisation va lui être envoyé.';

    public function test_guest_can_open_forgot_and_reset_password_forms(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Mot de passe oublié')
            ->assertSee(route('login'));

        $this->get(route('password.reset', [
            'token' => 'example-token',
            'email' => 'admin@example.test',
        ]))
            ->assertOk()
            ->assertSee('admin@example.test')
            ->assertSee('Nouveau mot de passe');
    }

    public function test_reset_link_request_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_RESET_MESSAGE);

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_RESET_MESSAGE);

        $resetUrl = '';
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user, &$resetUrl): bool {
                $resetUrl = $notification->toMail($user)->actionUrl;

                return true;
            },
        );
        Notification::assertCount(1);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@example.test']);
        $this->assertSame(
            true,
            str_starts_with($resetUrl, rtrim((string) config('app.url'), '/').'/reset-password/'),
        );
    }

    public function test_valid_reset_token_changes_password_and_clears_temporary_password_flag(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'must_change_password' => true,
        ]);
        $token = Password::createToken($user);
        $newPassword = 'New-Admin-Password-98!';

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'Original-Admin-Password-52!',
        ]);

        $this->from(route('password.reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
        ]))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'New-Admin-Password-98!',
                'password_confirmation' => 'New-Admin-Password-98!',
            ])
            ->assertRedirect(route('password.reset', [
                'token' => 'invalid-token',
                'email' => $user->email,
            ]))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Original-Admin-Password-52!', $user->fresh()->password));
    }

    public function test_short_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertSessionHasErrors([
                'password' => 'Le mot de passe doit contenir au moins 12 caractères.',
            ]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'admin@example.test'])
                ->assertRedirect(route('password.request'));
        }

        $this->post(route('password.email'), ['email' => 'admin@example.test'])
            ->assertTooManyRequests();
    }
}
