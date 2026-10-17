<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Tanpa remember-me: sesi admin tidak boleh bertahan lewat cookie jangka panjang.
        if (Auth::guard('admin')->attempt($validated)) {
            // Rotasi #1 setelah password; rotasi #2 terjadi setelah TOTP lolos.
            $request->session()->regenerate();

            if (Auth::guard('admin')->user()->requiresTotp()) {
                return redirect()->route('admin.totp.challenge');
            }

            return redirect()->route('admin.dashboard')
                ->with('success', 'Selamat datang kembali, Admin.');
        }

        return back()
            ->withErrors(['email' => 'Email atau password admin salah.'])
            ->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        // Guard ganda berbagi sesi: jangan hancurkan sesi bila user
        // masih login di browser yang sama (kompatibilitas admin/user).
        if (! Auth::guard('web')->check()) {
            $request->session()->invalidate();
        } else {
            $request->session()->migrate(true);
        }
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'Anda berhasil keluar dari panel admin.');
    }
}
