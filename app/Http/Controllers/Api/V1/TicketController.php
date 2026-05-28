<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketMessageRequest;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $tickets = Ticket::query()
            ->with(['client', 'assignedTo.user'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request, OrganizationContext $organizationContext): TicketResource
    {
        $data = $request->validated();
        $client = Client::query()->where('organization_id', $organizationContext->id())->findOrFail($data['client_id']);
        Gate::authorize('update', $client);

        $ticket = Ticket::create([
            ...$data,
            'organization_id' => $organizationContext->id(),
            'opened_by_user_id' => $request->user()->id,
            'visible_to_client' => $request->boolean('visible_to_client', true),
        ]);

        return new TicketResource($ticket->load(['client', 'assignedTo.user']));
    }

    public function show(Ticket $ticket, OrganizationContext $organizationContext): TicketResource
    {
        $this->authorizeTicket($ticket, $organizationContext);

        return new TicketResource($ticket->load(['client', 'assignedTo.user', 'messages']));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, OrganizationContext $organizationContext): TicketResource
    {
        $this->authorizeTicket($ticket, $organizationContext);
        $data = $request->validated();
        $statusDates = match ($data['status']) {
            Ticket::STATUS_RESOLVED => ['resolved_at' => now(), 'closed_at' => null],
            Ticket::STATUS_CLOSED => ['closed_at' => now()],
            default => ['resolved_at' => null, 'closed_at' => null],
        };

        $ticket->update([
            ...$data,
            'visible_to_client' => $request->boolean('visible_to_client', true),
            ...$statusDates,
        ]);

        return new TicketResource($ticket->refresh()->load(['client', 'assignedTo.user', 'messages']));
    }

    public function storeMessage(StoreTicketMessageRequest $request, Ticket $ticket, OrganizationContext $organizationContext): TicketResource
    {
        $this->authorizeTicket($ticket, $organizationContext);

        $ticket->messages()->create([
            'organization_id' => $organizationContext->id(),
            'user_id' => $request->user()->id,
            'sender_type' => TicketMessage::SENDER_INTERNAL,
            'body' => $request->validated('body'),
            'visible_to_client' => $request->boolean('visible_to_client', true),
        ]);

        if ($ticket->status === Ticket::STATUS_WAITING_CLIENT) {
            $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        }

        return new TicketResource($ticket->refresh()->load(['client', 'assignedTo.user', 'messages']));
    }

    private function authorizeTicket(Ticket $ticket, OrganizationContext $organizationContext): void
    {
        abort_if($ticket->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $ticket->client);
        abort_if($organizationContext->membership()?->role === OrganizationMember::ROLE_READONLY, Response::HTTP_FORBIDDEN);
    }
}
