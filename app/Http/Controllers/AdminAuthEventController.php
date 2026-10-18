<?php

namespace App\Http\Controllers;

use App\Enums\AuthEventName;
use App\Models\AuthEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminAuthEventController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'event' => ['nullable', Rule::in(AuthEventName::ALL)],
            'outcome' => ['nullable', Rule::in(['success', 'failure'])],
        ]);
        $events = AuthEvent::query()->latest('occurred_at')->latest('id')
            ->when($validated['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($validated['outcome'] ?? null, fn ($q, $outcome) => $q->where('outcome', $outcome))
            ->paginate(25)->withQueryString();
        return view('admin.auth-events', compact('events'));
    }
}
