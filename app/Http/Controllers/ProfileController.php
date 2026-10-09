<?php

namespace App\Http\Controllers;

use App\Models\ConciergeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'history' => ConciergeRequest::where('user_id', $user->id)
                ->orWhere(fn ($query) => $query->whereNull('user_id')->where('email', $user->email))
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address1' => ['nullable', 'string', 'max:300'],
            'address2' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated)->save();

        return back()->with('success', 'Profil Anda telah diperbarui.');
    }
}
