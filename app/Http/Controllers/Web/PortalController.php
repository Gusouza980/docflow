<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StorePortalAccessRequest;
use App\Http\Requests\Web\StorePortalMessageRequest;
use App\Http\Requests\Web\StorePortalTicketRequest;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\CommunicationConsent;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PortalController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar o portal.');
        }

        $clients = Client::query()
            ->whereBelongsTo($membership->organization)
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return Inertia::render('Portal/Index', [
            'metrics' => [
                'active_accesses' => ClientPortalAccess::whereBelongsTo($membership->organization)->where('status', ClientPortalAccess::STATUS_ACTIVE)->count(),
                'messages' => ClientMessage::whereBelongsTo($membership->organization)->count(),
                'open_tickets' => Ticket::whereBelongsTo($membership->organization)->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count(),
                'consents' => CommunicationConsent::whereBelongsTo($membership->organization)->where('status', CommunicationConsent::STATUS_GRANTED)->count(),
            ],
            'accesses' => ClientPortalAccess::query()
                ->with('client')
                ->whereBelongsTo($membership->organization)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ClientPortalAccess $access): array => $this->accessSummary($access)),
            'messages' => ClientMessage::query()
                ->with('client')
                ->whereBelongsTo($membership->organization)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ClientMessage $message): array => $this->messageSummary($message)),
            'tickets' => Ticket::query()
                ->with(['client', 'assignedTo.user'])
                ->whereBelongsTo($membership->organization)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Ticket $ticket): array => $this->ticketSummary($ticket)),
            'consents' => CommunicationConsent::query()
                ->with('client')
                ->whereBelongsTo($membership->organization)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (CommunicationConsent $consent): array => [
                    'id' => $consent->id,
                    'client' => ['id' => $consent->client->id, 'name' => $consent->client->display_name],
                    'channel' => $consent->channel,
                    'purpose' => $consent->purpose,
                    'status' => $consent->status,
                ]),
            'options' => [
                'clients' => $clients->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])->values(),
                'templates' => MessageTemplate::query()
                    ->whereBelongsTo($membership->organization)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (MessageTemplate $template): array => ['value' => $template->id, 'label' => $template->name, 'channel' => $template->channel, 'body' => $template->body])->values(),
                'members' => OrganizationMember::query()
                    ->with('user')
                    ->whereBelongsTo($membership->organization)
                    ->where('status', OrganizationMember::STATUS_ACTIVE)
                    ->get()
                    ->map(fn (OrganizationMember $member): array => ['value' => $member->id, 'label' => $member->user->name])->values(),
            ],
            'can' => [
                'manage' => $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function storeAccess(StorePortalAccessRequest $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        $client = $this->client($request->validated('client_id'), $membership);
        Gate::authorize('update', $client);

        $token = ClientPortalAccess::makeToken();
        $access = ClientPortalAccess::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
            'client_id' => $client->id,
            'token_hash' => $token['hash'],
        ]);

        return redirect()->route('portal.index')->with('status', 'Acesso do portal criado.')->with('portal_url', route('client-portal.show', ['token' => $token['plain']], absolute: false));
    }

    public function revokeAccess(ClientPortalAccess $access, Request $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($access->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $access->client);

        $access->revoke();

        return redirect()->route('portal.index')->with('status', 'Acesso revogado.');
    }

    public function storeMessage(StorePortalMessageRequest $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        $data = $request->validated();
        $client = $this->client($data['client_id'], $membership);
        Gate::authorize('update', $client);

        $template = isset($data['message_template_id'])
            ? MessageTemplate::whereBelongsTo($membership->organization)->findOrFail($data['message_template_id'])
            : null;

        if (! $this->hasConsent($client, $data['channel'], $template?->purpose ?? 'general')) {
            return redirect()->route('portal.index')->with('error', 'Consentimento ativo é obrigatório para este canal.');
        }

        DB::transaction(function () use ($request, $membership, $data, $client, $template): void {
            $message = ClientMessage::create([
                'organization_id' => $membership->organization_id,
                'client_id' => $client->id,
                'message_template_id' => $template?->id,
                'sent_by_user_id' => $request->user()->id,
                'channel' => $data['channel'],
                'direction' => ClientMessage::DIRECTION_OUTBOUND,
                'status' => ClientMessage::STATUS_SENT,
                'subject' => $data['subject'] ?? $template?->subject,
                'body' => $data['body'] ?? $template?->renderBody(['client_name' => $client->display_name]),
                'sent_at' => now(),
            ]);

            if ($request->boolean('create_ticket')) {
                $ticket = Ticket::create([
                    'organization_id' => $membership->organization_id,
                    'client_id' => $client->id,
                    'opened_by_user_id' => $request->user()->id,
                    'source_message_id' => $message->id,
                    'title' => $message->subject ?: 'Mensagem enviada',
                    'description' => $message->body,
                ]);

                $message->update(['ticket_id' => $ticket->id]);
            }
        });

        return redirect()->route('portal.index')->with('status', 'Mensagem registrada.');
    }

    public function storeTicket(StorePortalTicketRequest $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        $data = $request->validated();
        $client = $this->client($data['client_id'], $membership);
        Gate::authorize('update', $client);

        Ticket::create([
            ...$data,
            'organization_id' => $membership->organization_id,
            'client_id' => $client->id,
            'opened_by_user_id' => $request->user()->id,
            'visible_to_client' => $request->boolean('visible_to_client', true),
        ]);

        return redirect()->route('portal.index')->with('status', 'Chamado criado.');
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }

    private function client(int|string $clientId, OrganizationMember $membership): Client
    {
        return Client::query()->whereBelongsTo($membership->organization)->findOrFail($clientId);
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

    private function accessSummary(ClientPortalAccess $access): array
    {
        return [
            'id' => $access->id,
            'client' => ['id' => $access->client->id, 'name' => $access->client->display_name],
            'name' => $access->name,
            'email' => $access->email,
            'status' => $access->status,
            'expires_at' => $access->expires_at?->toDateString(),
            'last_used_at' => $access->last_used_at?->toISOString(),
        ];
    }

    private function messageSummary(ClientMessage $message): array
    {
        return [
            'id' => $message->id,
            'client' => ['id' => $message->client->id, 'name' => $message->client->display_name],
            'channel' => $message->channel,
            'direction' => $message->direction,
            'status' => $message->status,
            'subject' => $message->subject,
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function ticketSummary(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'client' => ['id' => $ticket->client->id, 'name' => $ticket->client->display_name],
            'title' => $ticket->title,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to' => $ticket->assignedTo?->user?->name,
            'visible_to_client' => $ticket->visible_to_client,
            'due_at' => $ticket->due_at?->toDateString(),
        ];
    }
}
