<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Menegakkan Gate untuk guard admin (Gate bawaan memakai guard default `web`). */
class AuthorizeAdmin
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin && Gate::forUser($admin)->allows($ability), 403, __('customer_messages.auth.admin_denied'));

        return $next($request);
    }
}
