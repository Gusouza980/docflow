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
        if (! $this->isAuthorized($request, $provider)) {
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

    private function isAuthorized(Request $request, string $provider): bool
    {
        return match ($provider) {
            'asaas' => $this->isAuthorizedAsaas($request),
            'manual' => $this->isAuthorizedManual($request),
            default => false,
        };
    }

    private function isAuthorizedAsaas(Request $request): bool
    {
        $secret = (string) config('docflow.billing.asaas_webhook_secret');

        if ($secret === '') {
            return false;
        }

        $token = (string) ($request->header('asaas-access-token')
            ?? $request->header('Asaas-Access-Token')
            ?? $request->header('X-Billing-Webhook-Secret')
            ?? '');

        return hash_equals($secret, $token);
    }

    private function isAuthorizedManual(Request $request): bool
    {
        $secret = (string) config('docflow.billing.webhook_secret');

        if ($secret === '') {
            return false;
        }

        return hash_equals($secret, (string) $request->header('X-Billing-Webhook-Secret'));
    }
}
