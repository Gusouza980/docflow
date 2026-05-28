<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientMessageRequest;
use App\Http\Resources\ClientMessageResource;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\CommunicationConsent;
use App\Models\MessageTemplate;
use App\Models\Ticket;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientCommunicationController extends Controller
{
    public function index(Client $client, Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $this->authorizeClient($client, $organizationContext);

        $messages = ClientMessage::query()
            ->with(['client', 'template'])
            ->whereBelongsTo($client)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ClientMessageResource::collection($messages);
    }

    public function store(StoreClientMessageRequest $request, OrganizationContext $organizationContext): ClientMessageResource|JsonResponse
    {
        $data = $request->validated();
        $client = Client::query()->where('organization_id', $organizationContext->id())->findOrFail($data['client_id']);
        Gate::authorize('update', $client);

        $template = isset($data['message_template_id'])
            ? MessageTemplate::query()->where('organization_id', $organizationContext->id())->findOrFail($data['message_template_id'])
            : null;

        if (($template?->requires_consent || $data['direction'] === ClientMessage::DIRECTION_OUTBOUND) && ! $this->hasConsent($client, $data['channel'], $template?->purpose ?? 'general')) {
            return response()->json(['message' => 'Consentimento ativo é obrigatório para este canal.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = DB::transaction(function () use ($request, $organizationContext, $client, $template, $data): ClientMessage {
            $body = $data['body'] ?? $template?->renderBody(['client_name' => $client->display_name]);
            $message = ClientMessage::create([
                'organization_id' => $organizationContext->id(),
                'client_id' => $client->id,
                'message_template_id' => $template?->id,
                'ticket_id' => $data['ticket_id'] ?? null,
                'sent_by_user_id' => $request->user()->id,
                'channel' => $data['channel'],
                'direction' => $data['direction'],
                'status' => $data['direction'] === ClientMessage::DIRECTION_OUTBOUND ? ClientMessage::STATUS_SENT : ClientMessage::STATUS_RECEIVED,
                'subject' => $data['subject'] ?? $template?->subject,
                'body' => $body,
                'external_name' => $data['external_name'] ?? null,
                'external_email' => $data['external_email'] ?? null,
                'sent_at' => $data['direction'] === ClientMessage::DIRECTION_OUTBOUND ? now() : null,
                'received_at' => $data['direction'] === ClientMessage::DIRECTION_INBOUND ? now() : null,
            ]);

            if ($request->boolean('create_ticket')) {
                $ticket = Ticket::create([
                    'organization_id' => $organizationContext->id(),
                    'client_id' => $client->id,
                    'opened_by_user_id' => $request->user()->id,
                    'source_message_id' => $message->id,
                    'title' => $message->subject ?: 'Mensagem do cliente',
                    'description' => $message->body,
                ]);

                $message->update(['ticket_id' => $ticket->id]);
            }

            return $message;
        });

        return new ClientMessageResource($message->load(['client', 'template']));
    }

    private function authorizeClient(Client $client, OrganizationContext $organizationContext): void
    {
        abort_if($client->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);
    }

    private function hasConsent(Client $client, string $channel, string $purpose): bool
    {
        return CommunicationConsent::query()
            ->whereBelongsTo($client)
            ->where('channel', $channel)
            ->whereIn('purpose', [$purpose, 'general'])
            ->where('status', CommunicationConsent::STATUS_GRANTED)
            ->exists();
    }
}
