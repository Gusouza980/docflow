<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientTagRequest;
use App\Models\Client;
use App\Models\ClientTag;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientTagController extends Controller
{
    public function store(
        StoreClientTagRequest $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, Response::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, Response::HTTP_FORBIDDEN);

        $tag = ClientTag::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
        ]);

        $auditLog->execute('web.client_tag.created', $request->user(), $membership->organization, $tag, request: $request);

        return redirect()->back()->with('status', 'Etiqueta criada.');
    }

    public function attach(Client $client, ClientTag $tag, Request $request, RecordAuditLog $auditLog): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view', $tag);

        abort_if($tag->organization_id !== $client->organization_id, Response::HTTP_NOT_FOUND);

        $client->tags()->syncWithoutDetaching([$tag->id]);

        $auditLog->execute('web.client.tag_attached', $request->user(), $client->organization, $client, ['tag_id' => $tag->id], $request);

        return redirect()->route('clients.show', $client)->with('status', 'Etiqueta aplicada.');
    }

    public function detach(Client $client, ClientTag $tag, Request $request, RecordAuditLog $auditLog): RedirectResponse
    {
        Gate::authorize('update', $client);
        Gate::authorize('view', $tag);

        abort_if($tag->organization_id !== $client->organization_id, Response::HTTP_NOT_FOUND);

        $client->tags()->detach($tag->id);

        $auditLog->execute('web.client.tag_detached', $request->user(), $client->organization, $client, ['tag_id' => $tag->id], $request);

        return redirect()->route('clients.show', $client)->with('status', 'Etiqueta removida.');
    }
}
