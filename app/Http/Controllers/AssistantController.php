<?php

namespace App\Http\Controllers;

use App\Models\AssistantFeedback;
use App\Services\AssistantProductSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// FR-402: read-only catalogue lookup and anonymous answer feedback for the guided assistant.
class AssistantController extends Controller
{
    public const FEEDBACK_TOPICS = ['product', 'results', 'size', 'delivery', 'order', 'care', 'gift', 'personalize', 'payment'];

    public function products(Request $request, AssistantProductSearch $search): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:120']]);

        // The query is answered and discarded; it is not logged or stored.
        $result = $search->search($validated['q']);

        return response()->json([
            'items' => $result['items'],
            'total' => $result['total'],
            'partial' => $result['partial'],
            'terms' => $result['terms'],
            'catalog' => route('catalog.index'),
        ]);
    }

    public function feedback(Request $request): Response
    {
        $validated = $request->validate([
            'topic' => ['required', 'in:'.implode(',', self::FEEDBACK_TOPICS)],
            'helpful' => ['required', 'boolean'],
        ]);

        AssistantFeedback::create($validated + ['locale' => app()->getLocale()]);

        return response()->noContent();
    }
}
