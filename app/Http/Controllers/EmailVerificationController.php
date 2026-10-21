<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** Tautan bertanda tangan dari email; EmailVerificationRequest mencocokkan id dan hash email dengan pengguna yang login. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('account.show')->with('success', __('customer_messages.auth.verified'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.show');
        }
        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', __('customer_messages.auth.verification_sent'));
    }
}
