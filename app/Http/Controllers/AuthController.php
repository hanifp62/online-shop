<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address1' => ['nullable', 'string', 'max:300'],
            'address2' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->attributes->set('cart_allow_merge', true);
        \App\Services\CheckoutService::cart($request);

        return redirect('/')
            ->with('success', 'Registrasi berhasil. Anda sudah login.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (Auth::attempt($credentials, (bool) ($validated['remember'] ?? false))) {
            $request->session()->regenerate();
        $request->attributes->set('cart_allow_merge', true);
            \App\Services\CheckoutService::cart($request);

            return redirect('/')
                ->with('success', 'Login berhasil.');
        }

        return back()
            ->withErrors([
                'email' => 'Email atau password salah.',
            ])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        // Pertahankan keranjang akun (lintas perangkat); lepaskan tautan browser saja.
        \App\Services\CartStore::detach($request);

        // Guard ganda berbagi sesi: jangan hancurkan sesi bila admin
        // masih login di browser yang sama (kompatibilitas user/admin).
        if (! Auth::guard('admin')->check()) {
            $request->session()->invalidate();
        } else {
            $request->session()->forget('cart');
            $request->session()->migrate(true);
        }
        $request->session()->regenerateToken();

        return redirect('/')
            ->with('success', 'Anda berhasil logout.');
    }
}
