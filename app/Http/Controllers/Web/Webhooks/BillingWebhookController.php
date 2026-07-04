<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBillingWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingWebhookController extends Controller
{
    public function store(Request $request, string $provider): JsonResponse
    {
        $secret = match ($provider) {
            'asaas' => config('docflow.billing.asaas_webhook_secret'),
            'manual' => config('docflow.billing.webhook_secret'),
            default => null,
        };

        if ($secret && $request->header('X-Billing-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();
        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? $request->header('X-Event-Id'));

        if ($eventId === '') {
            return response()->json(['message' => 'event_id is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        ProcessBillingWebhook::dispatch($provider, $eventId, $payload);

        return response()->json(['received' => true]);
    }
}
