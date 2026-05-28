<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientTagRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ClientTagResource;
use App\Models\Client;
use App\Models\ClientTag;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientTagController extends Controller
{
    public function store(StoreClientTagRequest $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        $tag = ClientTag::create([
            ...$request->validated(),
            'organization_id' => $organizationContext->id(),
        ]);

        $auditLog->execute('client_tag.created', $request->user(), $organizationContext->organization(), $tag, request: $request);

        return (new ClientTagResource($tag))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function attach(Client $client, ClientTag $tag, Request $request, RecordAuditLog $auditLog): ClientResource
    {
        Gate::authorize('update', $client);
        Gate::authorize('view', $tag);

        abort_if($tag->organization_id !== $client->organization_id, Response::HTTP_NOT_FOUND);

        $client->tags()->syncWithoutDetaching([$tag->id]);

        $auditLog->execute('client.tag_attached', $request->user(), $client->organization, $client, ['tag_id' => $tag->id], $request);

        return new ClientResource($client->load(['tags', 'primaryResponsible.user', 'responsibles.user']));
    }

    public function detach(Client $client, ClientTag $tag, Request $request, RecordAuditLog $auditLog): ClientResource
    {
        Gate::authorize('update', $client);
        Gate::authorize('view', $tag);

        abort_if($tag->organization_id !== $client->organization_id, Response::HTTP_NOT_FOUND);

        $client->tags()->detach($tag->id);

        $auditLog->execute('client.tag_detached', $request->user(), $client->organization, $client, ['tag_id' => $tag->id], $request);

        return new ClientResource($client->load(['tags', 'primaryResponsible.user', 'responsibles.user']));
    }
}
