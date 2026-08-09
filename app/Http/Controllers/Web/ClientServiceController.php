<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contracts\UpsertClientService;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientServiceRequest;
use App\Models\Client;
use App\Models\ClientService;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClientServiceController extends Controller
{
    public function store(
        StoreClientServiceRequest $request,
        Client $client,
        WebOrganizationContext $webOrganizationContext,
        UpsertClientService $upsertClientService,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($client->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);
        Gate::authorize('create', ClientService::class);

        try {
            $service = $upsertClientService->execute($client, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $auditLog->execute(
            'web.client_service.created',
            $request->user(),
            $membership->organization,
            $service,
            request: $request,
        );

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'services'])
            ->with('status', 'Serviço vinculado ao cliente.');
    }

    public function update(
        StoreClientServiceRequest $request,
        Client $client,
        ClientService $service,
        WebOrganizationContext $webOrganizationContext,
        UpsertClientService $upsertClientService,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($client->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($service->client_id === $client->id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $service);

        try {
            $service = $upsertClientService->execute($client, $request->validated(), $service);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $auditLog->execute(
            'web.client_service.updated',
            $request->user(),
            $membership->organization,
            $service,
            request: $request,
        );

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'services'])
            ->with('status', 'Serviço do cliente atualizado.');
    }
}
