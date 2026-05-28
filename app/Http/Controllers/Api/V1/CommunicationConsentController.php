<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunicationConsentRequest;
use App\Http\Resources\CommunicationConsentResource;
use App\Models\Client;
use App\Models\CommunicationConsent;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CommunicationConsentController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $consents = CommunicationConsent::query()
            ->with('client')
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return CommunicationConsentResource::collection($consents);
    }

    public function store(StoreCommunicationConsentRequest $request, OrganizationContext $organizationContext): CommunicationConsentResource
    {
        $data = $request->validated();
        $client = Client::query()->where('organization_id', $organizationContext->id())->findOrFail($data['client_id']);
        Gate::authorize('update', $client);

        $consent = CommunicationConsent::updateOrCreate([
            'organization_id' => $organizationContext->id(),
            'client_id' => $client->id,
            'channel' => $data['channel'],
            'purpose' => $data['purpose'] ?? 'general',
        ], [
            'recorded_by_user_id' => $request->user()->id,
            'status' => CommunicationConsent::STATUS_GRANTED,
            'source' => $data['source'] ?? 'manual',
            'notes' => $data['notes'] ?? null,
            'granted_at' => now(),
            'revoked_at' => null,
        ]);

        return new CommunicationConsentResource($consent->load('client'));
    }

    public function revoke(CommunicationConsent $consent, OrganizationContext $organizationContext): CommunicationConsentResource
    {
        abort_if($consent->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('update', $consent->client);

        $consent->revoke();

        return new CommunicationConsentResource($consent->refresh()->load('client'));
    }
}
