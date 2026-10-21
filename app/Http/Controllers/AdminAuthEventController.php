<?php

namespace App\Http\Controllers;

use App\Enums\AuthEventName;
use App\Models\AuthEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAuthEventController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'event' => ['nullable', Rule::in(AuthEventName::ALL)],
            'outcome' => ['nullable', Rule::in(['success', 'failure'])],
            'request_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);
        $events = AuthEvent::query()->latest('occurred_at')->latest('id')
            ->when($validated['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($validated['outcome'] ?? null, fn ($q, $outcome) => $q->where('outcome', $outcome))
            ->when($validated['request_id'] ?? null, fn ($q, $id) => $q->where('request_id', $id))
            ->paginate(25)->withQueryString();

        return view('admin.auth-events', compact('events'));
    }
}
