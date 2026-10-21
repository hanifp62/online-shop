<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Services\AuthEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AuthEventRecorder $events) {}

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
            $admin = Auth::guard('admin')->user();
            $this->events->recordSessionRotated('admin', $admin->id, $request->ip(), 'login');

            if ($admin->requiresTotp()) {
                return redirect()->route('admin.totp.challenge');
            }

            $this->events->recordLoginSuccess($admin->id, $request->ip(), 'admin', 'login');

            return redirect()->route('admin.dashboard')
                ->with('success', __('admin_ui.flash.welcome'));
        }

        $failedAdmin = Admin::where('email', $validated['email'])->first();
        $this->events->recordLoginFailure($failedAdmin?->id, $request->ip(), 'invalid_credentials', 'admin');

        return back()
            ->withErrors(['email' => __('admin_ui.flash.invalid_login')])
            ->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        Auth::guard('admin')->logout();

        // Guard ganda berbagi sesi: jangan hancurkan sesi bila user
        // masih login di browser yang sama (kompatibilitas admin/user).
        if (! Auth::guard('web')->check()) {
            $request->session()->invalidate();
        } else {
            $request->session()->migrate(true);
        }
        $request->session()->regenerateToken();

        if ($admin) {
            $this->events->recordSessionRotated('admin', $admin->id, $request->ip(), 'logout');
        }

        return redirect()->route('admin.login')
            ->with('success', __('admin_ui.flash.logged_out'));
    }
}
