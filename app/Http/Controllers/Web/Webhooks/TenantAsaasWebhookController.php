<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTenantReceivableWebhook;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantAsaasWebhookController extends Controller
{
    public function store(Request $request, Organization $organization): JsonResponse
    {
        $gateway = $organization->paymentGateway;

        if ($gateway === null || ! $gateway->isReady()) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $token = (string) ($request->header('asaas-access-token')
            ?? $request->header('Asaas-Access-Token')
            ?? '');

        if ($token === '' || ! hash_equals($gateway->webhook_token, $token)) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();
        $eventId = (string) ($payload['id'] ?? $payload['event_id'] ?? '');

        if ($eventId === '') {
            return response()->json(['message' => 'event_id is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        ProcessTenantReceivableWebhook::dispatch($organization->id, $eventId, $payload);

        return response()->json(['received' => true]);
    }
}
