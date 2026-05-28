<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientPortalMessageRequest;
use App\Http\Requests\Web\StoreClientPortalTicketRequest;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\CommunicationConsent;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\GeneratedReport;
use App\Models\Receivable;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClientPortalController extends Controller
{
    public function show(string $token): Response
    {
        $access = $this->access($token);
        $access->update(['last_used_at' => now()]);

        return Inertia::render('ClientPortal/Show', [
            'token' => $token,
            'hasPortalCommunicationConsent' => $this->hasPortalConsent($access->client),
            'client' => [
                'id' => $access->client->id,
                'name' => $access->client->display_name,
                'contact' => ['name' => $access->name, 'email' => $access->email],
            ],
            'documentRequests' => $access->client->documentRequests()
                ->with(['items.category', 'items.document.latestVersion'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (DocumentRequest $request): array => $this->documentRequestSummary($request)),
            'receivables' => $access->client->receivables()
                ->whereIn('status', ['open', 'partial', 'paid'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Receivable $receivable): array => [
                    'id' => $receivable->id,
                    'description' => $receivable->description,
                    'status' => $receivable->status,
                    'amount_cents' => $receivable->amount_cents,
                    'balance_cents' => $receivable->balanceCents(),
                    'due_at' => $receivable->due_at?->toDateString(),
                ]),
            'tickets' => $access->client->tickets()
                ->with('messages')
                ->where('visible_to_client', true)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Ticket $ticket): array => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'messages_count' => $ticket->messages->where('visible_to_client', true)->count(),
                ]),
            'messages' => $access->client->messages()
                ->with('sentBy')
                ->where(function ($query) use ($access): void {
                    $query->where('direction', ClientMessage::DIRECTION_OUTBOUND)
                        ->orWhere('client_portal_access_id', $access->id);
                })
                ->orderBy('created_at')
                ->limit(100)
                ->get()
                ->map(fn (ClientMessage $message): array => $this->messageSummary($message, $access)),
            'announcements' => Announcement::query()
                ->where('organization_id', $access->organization_id)
                ->where(function ($query) use ($access): void {
                    $query->whereNull('client_id')->orWhere('client_id', $access->client_id);
                })
                ->where('status', Announcement::STATUS_PUBLISHED)
                ->latest('published_at')
                ->limit(10)
                ->get(['id', 'title', 'body', 'published_at']),
            'reports' => $access->client->generatedReports()
                ->where('status', GeneratedReport::STATUS_RELEASED)
                ->latest('released_at')
                ->limit(10)
                ->get()
                ->map(function (GeneratedReport $report): array {
                    $report->update(['last_viewed_at' => now()]);

                    return [
                        'id' => $report->id,
                        'title' => $report->title,
                        'released_at' => $report->released_at?->toISOString(),
                        'payload' => $report->payload,
                    ];
                }),
        ]);
    }

    public function storeConsent(string $token): RedirectResponse
    {
        $access = $this->access($token);

        CommunicationConsent::updateOrCreate([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'channel' => 'portal',
            'purpose' => 'general',
        ], [
            'recorded_by_user_id' => null,
            'status' => CommunicationConsent::STATUS_GRANTED,
            'source' => 'client_portal',
            'notes' => "Consentimento concedido por {$access->name} ({$access->email}) via portal.",
            'granted_at' => now(),
            'revoked_at' => null,
        ]);

        return redirect()->route('client-portal.show', ['token' => $token]);
    }

    public function storeMessage(StoreClientPortalMessageRequest $request, string $token): RedirectResponse
    {
        $access = $this->access($token);

        if (! $this->hasPortalConsent($access->client)) {
            return redirect()->route('client-portal.show', ['token' => $token])
                ->with('error', 'Autorize a comunicação pelo portal para enviar mensagens.');
        }

        ClientMessage::create([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'client_portal_access_id' => $access->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_INBOUND,
            'status' => ClientMessage::STATUS_RECEIVED,
            'body' => $request->validated('body'),
            'external_name' => $access->name,
            'external_email' => $access->email,
            'received_at' => now(),
        ]);

        return redirect()->route('client-portal.show', ['token' => $token]);
    }

    public function storeTicket(StoreClientPortalTicketRequest $request, string $token): RedirectResponse
    {
        $access = $this->access($token);

        $ticket = Ticket::create([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'visible_to_client' => true,
        ]);

        $ticket->messages()->create([
            'organization_id' => $access->organization_id,
            'client_portal_access_id' => $access->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => $request->validated('description'),
            'visible_to_client' => true,
        ]);

        return redirect()->route('client-portal.show', ['token' => $token])->with('status', 'Solicitação aberta.');
    }

    private function access(string $token): ClientPortalAccess
    {
        $access = ClientPortalAccess::findUsableByToken($token);
        abort_unless($access, HttpResponse::HTTP_NOT_FOUND);

        return $access;
    }

    private function hasPortalConsent(Client $client): bool
    {
        return CommunicationConsent::query()
            ->whereBelongsTo($client)
            ->where('channel', 'portal')
            ->whereIn('purpose', ['general'])
            ->where('status', CommunicationConsent::STATUS_GRANTED)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageSummary(ClientMessage $message, ClientPortalAccess $access): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'subject' => $message->subject,
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'sender_name' => $message->direction === ClientMessage::DIRECTION_OUTBOUND
                ? ($message->sentBy?->name ?? 'Escritório')
                : ($message->external_name ?? $access->name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRequestSummary(DocumentRequest $request): array
    {
        return [
            'id' => $request->id,
            'title' => $request->title,
            'status' => $request->status,
            'due_at' => $request->due_at?->toDateString(),
            'items_count' => $request->items->count(),
            'received_items_count' => $request->items->whereNotNull('received_at')->count(),
            'items' => $request->items->map(fn (DocumentRequestItem $item): array => $this->documentRequestItemSummary($item, $request))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRequestItemSummary(DocumentRequestItem $item, DocumentRequest $request): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'instructions' => $item->instructions,
            'status' => $item->status,
            'due_at' => $item->due_at?->toDateString() ?? $request->due_at?->toDateString(),
            'rejection_reason' => $item->rejection_reason,
            'category' => $item->category ? ['name' => $item->category->name] : null,
        ];
    }
}
