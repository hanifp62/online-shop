<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\PasswordResetTokenIssuer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function request(): mixed
    {
        return view('auth.password-email');
    }

    public function email(Request $request, PasswordResetTokenIssuer $issuer): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
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
        return view('auth.password-reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $status = Password::broker('users')->reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'has_password' => true, 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table'))
                    ->where('user_id', $user->id)->delete();
            }
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

        // Jika pelaku reset sedang masuk, sesi ini pun dicabut agar tidak ditulis ulang setelah dihapus.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('customer_messages.auth.reset_done'));
    }
}
