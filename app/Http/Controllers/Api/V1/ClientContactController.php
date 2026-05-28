<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientContactRequest;
use App\Http\Requests\Api\V1\UpdateClientContactRequest;
use App\Http\Resources\ClientContactResource;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientContactController extends Controller
{
    public function store(StoreClientContactRequest $request, Client $client, RecordAuditLog $auditLog): JsonResponse
    {
        $data = $request->validated();

        if ($data['is_primary'] ?? false) {
            $client->contacts()
                ->where('type', $data['type'] ?? ClientContact::TYPE_GENERAL)
                ->update(['is_primary' => false]);
        }

        $contact = $client->contacts()->create([
            ...$data,
            'organization_id' => $client->organization_id,
            'type' => $data['type'] ?? ClientContact::TYPE_GENERAL,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        $auditLog->execute('client_contact.created', $request->user(), $client->organization, $contact, ['client_id' => $client->id], $request);

        return (new ClientContactResource($contact))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateClientContactRequest $request, ClientContact $contact, RecordAuditLog $auditLog): ClientContactResource
    {
        $data = $request->validated();

        if ($data['is_primary'] ?? false) {
            $contact->client->contacts()
                ->where('type', $data['type'] ?? $contact->type)
                ->whereKeyNot($contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($data);

        $auditLog->execute('client_contact.updated', $request->user(), $contact->organization, $contact, ['client_id' => $contact->client_id], $request);

        return new ClientContactResource($contact);
    }

    public function destroy(ClientContact $contact, Request $request, RecordAuditLog $auditLog): Response
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        $auditLog->execute('client_contact.deleted', $request->user(), $contact->organization, $contact, ['client_id' => $contact->client_id], $request);

        return response()->noContent();
    }
}
