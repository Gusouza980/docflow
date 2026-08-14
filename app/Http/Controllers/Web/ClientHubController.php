<?php

namespace App\Http\Controllers\Web;

use App\Actions\Communication\DeliverOutboundClientMessage;
use App\Actions\Notifications\NotifyPortalClient;
use App\Actions\Tickets\StoreTicketMessageAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientHubMessageRequest;
use App\Http\Requests\Web\StoreClientHubTicketMessageRequest;
use App\Http\Requests\Web\StoreClientHubTicketRequest;
use App\Http\Requests\Web\StorePortalAccessRequest;
use App\Http\Requests\Web\UpdateClientHubTicketRequest;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use App\Support\Billing\PlanLimitChecker;
use App\Support\BuildsClientPortalDashboard;
use App\Support\BuildsTicketHubPayload;
use App\Support\Communication\ClientMessageDestination;
use App\Support\Communication\WhatsAppLink;
use App\Support\WebOrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientHubController extends Controller
{
    public function __construct(
        private BuildsClientPortalDashboard $dashboard,
        private BuildsTicketHubPayload $ticketPayload,
        private StoreTicketMessageAttachment $storeTicketAttachment,
        private NotifyPortalClient $notifyPortalClient,
        private DeliverOutboundClientMessage $deliverOutboundClientMessage,
        private ClientMessageDestination $destination,
        private WhatsAppLink $whatsAppLink,
    ) {}

    public function messages(Client $client, Request $request, WebOrganizationContext $webOrganizationContext): JsonResponse
    {
        $this->authorizeClient($client, $request, $webOrganizationContext);

        $sinceId = $request->integer('since_id') ?: null;

        return response()->json([
            'messages' => $this->dashboard->messagesForClient($client, $sinceId),
        ]);
    }

    public function showTicket(
        Client $client,
        Ticket $ticket,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): JsonResponse {
        $this->authorizeTicket($client, $ticket, $request, $webOrganizationContext);

        $membership = $webOrganizationContext->membership($request);
        $canViewInternal = $membership?->role !== OrganizationMember::ROLE_READONLY;

        return response()->json([
            'ticket' => $this->ticketPayload->detail($ticket, $canViewInternal),
        ]);
    }

    public function storeTicket(
        Client $client,
        StoreClientHubTicketRequest $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        $data = $request->validated();

        $ticket = Ticket::create([
            ...$data,
            'organization_id' => $membership->organization_id,
            'client_id' => $client->id,
            'opened_by_user_id' => $request->user()->id,
            'visible_to_client' => $request->boolean('visible_to_client', true),
        ]);

        if (! empty($data['description'])) {
            $ticket->messages()->create([
                'organization_id' => $membership->organization_id,
                'user_id' => $request->user()->id,
                'sender_type' => TicketMessage::SENDER_INTERNAL,
                'body' => $data['description'],
                'visible_to_client' => $request->boolean('visible_to_client', true),
            ]);
        }

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'tickets', 'ticket' => $ticket->id])
            ->with('status', 'Chamado criado.');
    }

    public function updateTicket(
        Client $client,
        Ticket $ticket,
        UpdateClientHubTicketRequest $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        $this->authorizeTicket($client, $ticket, $request, $webOrganizationContext, requireWrite: true);

        $data = $request->validated();
        $statusDates = isset($data['status']) ? $this->statusDatesFor($data['status']) : [];

        $ticket->update([
            ...$data,
            ...$statusDates,
            ...($request->has('visible_to_client') ? [
                'visible_to_client' => $request->boolean('visible_to_client'),
            ] : []),
        ]);

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'tickets', 'ticket' => $ticket->id])
            ->with('status', 'Chamado atualizado.');
    }

    public function storeTicketMessage(
        Client $client,
        Ticket $ticket,
        StoreClientHubTicketMessageRequest $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        $this->authorizeTicket($client, $ticket, $request, $webOrganizationContext, requireWrite: true);

        $visibleToClient = $request->boolean('visible_to_client', true);

        $message = $ticket->messages()->create([
            'organization_id' => $membership->organization_id,
            'user_id' => $request->user()->id,
            'sender_type' => TicketMessage::SENDER_INTERNAL,
            'body' => $request->validated('body') ?? '',
            'visible_to_client' => $visibleToClient,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeTicketAttachment->execute(
                $message,
                $request->file('attachment'),
                $visibleToClient,
            );
        }

        if ($visibleToClient) {
            $ticket->loadMissing('client');
            $this->notifyPortalClient->execute(
                $ticket->client,
                'Nova resposta no chamado',
                'O escritório respondeu ao chamado "'.$ticket->title.'".',
                route('client-portal.tickets.index', ['ticket' => $ticket->id], absolute: true),
            );
        }

        if ($ticket->status === Ticket::STATUS_WAITING_CLIENT) {
            $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
        }

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'tickets', 'ticket' => $ticket->id])
            ->with('status', 'Resposta registrada.');
    }

    public function downloadTicketAttachment(
        Client $client,
        TicketMessageAttachment $attachment,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): StreamedResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $attachment->loadMissing('message.ticket');
        abort_unless($attachment->message->ticket->client_id === $client->id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function storeMessage(
        Client $client,
        StoreClientHubMessageRequest $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        $data = $request->validated();

        $template = isset($data['message_template_id'])
            ? MessageTemplate::whereBelongsTo($membership->organization)->findOrFail($data['message_template_id'])
            : null;

        $purpose = $template?->purpose ?? 'general';
        $reason = $this->destination->skipReason($client, $data['channel'], $purpose);

        if ($reason !== null) {
            return redirect()
                ->route('clients.show', ['client' => $client, 'tab' => 'communication'])
                ->with('error', $this->deliveryError($reason));
        }

        $variables = $this->destination->variablesFor($client);
        $message = $this->deliverOutboundClientMessage->queue(
            client: $client,
            channel: $data['channel'],
            body: $data['body'] ?? '',
            subject: $data['subject'] ?? $template?->subject,
            template: $template,
            sentBy: $request->user(),
            variables: $variables,
        );

        $status = match ($message->channel) {
            MessageTemplate::CHANNEL_WHATSAPP => 'Mensagem pronta. Abra o WhatsApp para enviar.',
            default => 'Mensagem enviada.',
        };

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'communication'])
            ->with('status', $status)
            ->with('whatsapp_url', $message->channel === MessageTemplate::CHANNEL_WHATSAPP
                ? $this->whatsAppLink->forClient($client, $message->body)
                : null);
    }

    public function openWhatsApp(
        Client $client,
        ClientMessage $message,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($message->client_id !== $client->id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        $this->deliverOutboundClientMessage->markWhatsAppOpened($message);

        return back()->with('status', 'WhatsApp marcado como enviado.');
    }

    public function storeTicketFromMessage(
        Client $client,
        ClientMessage $message,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($message->client_id !== $client->id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($message->direction === ClientMessage::DIRECTION_INBOUND, HttpResponse::HTTP_NOT_FOUND);
        abort_if($message->ticket_id !== null, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        $ticket = DB::transaction(function () use ($request, $membership, $client, $message): Ticket {
            $ticket = Ticket::create([
                'organization_id' => $membership->organization_id,
                'client_id' => $client->id,
                'opened_by_user_id' => $request->user()->id,
                'source_message_id' => $message->id,
                'title' => $message->subject ?: 'Mensagem do cliente',
                'description' => $message->body,
                'visible_to_client' => true,
            ]);

            $ticket->messages()->create([
                'organization_id' => $membership->organization_id,
                'user_id' => $request->user()->id,
                'sender_type' => TicketMessage::SENDER_INTERNAL,
                'body' => "Chamado criado a partir de mensagem do cliente:\n\n{$message->body}",
                'visible_to_client' => true,
            ]);

            $message->update(['ticket_id' => $ticket->id]);

            return $ticket;
        });

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'tickets', 'ticket' => $ticket->id])
            ->with('status', 'Chamado criado a partir da mensagem.');
    }

    public function storePortalAccess(
        Client $client,
        StorePortalAccessRequest $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        $planLimitChecker->assertFeature($membership->organization, 'portal');
        $planLimitChecker->assertWithinLimit($membership->organization, 'max_portal_accesses', 1);

        $token = ClientPortalAccess::makeToken();
        ClientPortalAccess::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
            'client_id' => $client->id,
            'token_hash' => $token['hash'],
        ]);

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('status', 'Convite do portal criado.')
            ->with('portal_url', route('client-portal.invite', ['token' => $token['plain']], absolute: true));
    }

    public function revokePortalAccess(
        Client $client,
        ClientPortalAccess $access,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($access->client_id !== $client->id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        $access->revoke();

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'portal'])
            ->with('status', 'Acesso revogado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function statusDatesFor(string $status): array
    {
        return match ($status) {
            Ticket::STATUS_RESOLVED => ['resolved_at' => now(), 'closed_at' => null],
            Ticket::STATUS_CLOSED => ['closed_at' => now()],
            default => ['resolved_at' => null, 'closed_at' => null],
        };
    }

    private function authorizeTicket(
        Client $client,
        Ticket $ticket,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        bool $requireWrite = false,
    ): void {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($ticket->client_id !== $client->id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);

        if ($requireWrite) {
            abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
            Gate::authorize('update', $client);
        }
    }

    private function authorizeClient(Client $client, Request $request, WebOrganizationContext $webOrganizationContext): void
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($client->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }

    private function deliveryError(string $reason): string
    {
        return match ($reason) {
            'Sem consentimento para este canal' => 'Consentimento ativo é obrigatório para este canal.',
            'Sem e-mail no contato' => 'Cadastre um e-mail no contato do cliente.',
            'Sem WhatsApp no contato' => 'Cadastre o WhatsApp no contato do cliente.',
            default => $reason,
        };
    }
}
