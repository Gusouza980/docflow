<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientContactController extends Controller
{
    public function store(StoreClientContactRequest $request, Client $client, RecordAuditLog $auditLog): RedirectResponse
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

        $auditLog->execute('web.client_contact.created', $request->user(), $client->organization, $contact, ['client_id' => $client->id], $request);

        return redirect()->route('clients.show', $client)->with('status', 'Contato adicionado.');
    }

    public function destroy(ClientContact $contact, Request $request, RecordAuditLog $auditLog): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $client = $contact->client;
        $contact->delete();

        $auditLog->execute('web.client_contact.deleted', $request->user(), $contact->organization, $contact, ['client_id' => $client->id], $request);

        return redirect()->route('clients.show', $client)->with('status', 'Contato removido.');
    }
}
