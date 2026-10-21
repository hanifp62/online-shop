<?php

namespace App\Http\Controllers;

use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentWebhookService $service): JsonResponse
    {
        $status = $service->handle($request->getContent(), $request->json()->all() ?: $request->all());

        return response()->json(['ok' => $status === 200], $status);
    }
}
