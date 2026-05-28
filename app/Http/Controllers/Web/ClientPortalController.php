<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientPortalMessageRequest;
use App\Http\Requests\Web\StoreClientPortalTicketRequest;
use App\Models\Announcement;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\DocumentRequest;
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
                ->map(fn (DocumentRequest $request): array => [
                    'id' => $request->id,
                    'title' => $request->title,
                    'status' => $request->status,
                    'due_at' => $request->due_at?->toDateString(),
                    'items_count' => $request->items->count(),
                    'received_items_count' => $request->items->whereNotNull('received_at')->count(),
                ]),
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
                ->where(function ($query) use ($access): void {
                    $query->where('direction', ClientMessage::DIRECTION_OUTBOUND)
                        ->orWhere('client_portal_access_id', $access->id);
                })
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (ClientMessage $message): array => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'subject' => $message->subject,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toISOString(),
                ]),
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

    public function storeMessage(StoreClientPortalMessageRequest $request, string $token): RedirectResponse
    {
        $access = $this->access($token);

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

        return redirect()->route('client-portal.show', ['token' => $token])->with('status', 'Mensagem enviada.');
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
}
