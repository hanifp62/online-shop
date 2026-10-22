<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateGuardSession;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\PasswordResetTokenIssuer;
use App\Support\AuthPageProps;
use App\Support\AuthSecurityPageProps;
use App\Support\FrontendPage;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function request(): mixed
    {
        return FrontendPage::render('Auth/ForgotPassword', 'auth.password-email', [], AuthPageProps::forgotPassword(request()));
    }

    public function email(Request $request, PasswordResetTokenIssuer $issuer): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $user = User::where('email', $request->string('email')->lower())->first();
        // Broker throttle (auth.passwords.users.throttle) dihormati: token baru tidak dibuat dalam jeda tersebut.
        if ($user && ! Password::broker('users')->getRepository()->recentlyCreatedToken($user)) {
            try {
                $token = $issuer->issue($user);
                $user->sendPasswordResetNotification($token);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', __('customer_messages.auth.reset_sent'));
    }

    public function reset(string $token, Request $request): mixed
    {
        $email = $request->query('email');
        $response = FrontendPage::render('Auth/ResetPassword', 'auth.password-reset', [
            'token' => $token, 'email' => is_string($email) ? $email : '',
        ], AuthSecurityPageProps::reset($request));

        // Flash is request-scoped in Inertia v3 and excluded from history props.
        return $response instanceof Response ? $response->flash('resetToken', $token) : $response;
    }

    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $status = Password::broker('users')->reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'has_password' => true, 'remember_token' => Str::random(60)])->save();
            // Guard fingerprints revoke old customer sessions without deleting another guard's shared session row.
            event(new PasswordReset($user));
            try {
                $user->notify(new PasswordChanged);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __('customer_messages.auth.reset_invalid')]);
        }

        // Revoke this customer guard too, without destroying an independent admin login.
        Auth::guard('web')->logoutCurrentDevice();
        AuthenticateGuardSession::clear($request, 'web');
        if (Auth::guard('admin')->check()) {
            AuthenticateGuardSession::rotate($request, 'web');
        } else {
            $request->session()->invalidate();
        }
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('customer_messages.auth.reset_done'));
    }
}
