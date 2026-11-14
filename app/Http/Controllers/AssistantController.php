<?php

namespace App\Http\Controllers;

use App\Models\AssistantFeedback;
use App\Services\AssistantProductSearch;
use App\Services\GeminiConciergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// FR-402: read-only catalogue lookup, real-time Gemini AI CS advisor, and anonymous answer feedback.
class AssistantController extends Controller
{
    public const FEEDBACK_TOPICS = ['product', 'results', 'size', 'delivery', 'order', 'care', 'gift', 'personalize', 'payment', 'ai_chat', 'ai_reply'];

    public function chat(Request $request, GeminiConciergeService $gemini): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:600'],
            'product_slug' => ['nullable', 'string', 'max:120'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,bot,model,assistant'],
            'history.*.text' => ['required_with:history', 'string', 'max:800'],
        ]);

        $payload = $gemini->respond(
            $request,
            $validated['message'],
            $validated['history'] ?? [],
            $validated['product_slug'] ?? null
        );

        return response()->json($payload)->header('Cache-Control', 'no-store, private');
    }

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
