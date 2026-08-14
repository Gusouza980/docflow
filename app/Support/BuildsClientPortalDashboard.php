<?php

namespace App\Support;

use App\Enums\CalendarEventType;
use App\Models\Announcement;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\ClientProfileUpdateRequest;
use App\Models\CommunicationConsent;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\GeneratedReport;
use App\Models\PortalClientAlert;
use App\Models\Receivable;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use App\Models\TicketPortalRead;

class BuildsClientPortalDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function layout(ClientPortalAccess $access): array
    {
        $client = $access->client;

        return [
            'client' => [
                'id' => $client->id,
                'name' => $client->display_name,
                'organization' => [
                    'id' => $access->organization->id,
                    'name' => $access->organization->name,
                ],
                'contact' => ['name' => $access->name, 'email' => $access->email],
            ],
            'portalNav' => $this->navigationCounts($access),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function homeSummary(ClientPortalAccess $access): array
    {
        $client = $access->client;
        $pendingDocumentItems = $this->pendingDocumentItemsCount($client->id);

        return [
            'pendingDocumentItems' => $pendingDocumentItems,
            'openTicketsCount' => $client->tickets()
                ->where('visible_to_client', true)
                ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
                ->count(),
            'ticketsAwaitingResponse' => $client->tickets()
                ->where('visible_to_client', true)
                ->where('status', Ticket::STATUS_WAITING_CLIENT)
                ->count(),
            'ticketsAwaitingRating' => $client->tickets()
                ->where('visible_to_client', true)
                ->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
                ->whereDoesntHave('rating')
                ->count(),
            'receivablesCount' => $client->receivables()->whereIn('status', ['open', 'partial'])->count(),
            'recentDocumentRequests' => $this->documentRequestsForAccess($access, 3),
            'recentTickets' => $this->ticketsForAccess($access, 3),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function navigationCounts(ClientPortalAccess $access): array
    {
        $client = $access->client;

        return [
            'pendingDocuments' => $this->pendingDocumentItemsCount($client->id),
            'openTickets' => $client->tickets()
                ->where('visible_to_client', true)
                ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
                ->count(),
            'ticketsAwaitingResponse' => $client->tickets()
                ->where('visible_to_client', true)
                ->where('status', Ticket::STATUS_WAITING_CLIENT)
                ->count(),
            'ticketsAwaitingRating' => $client->tickets()
                ->where('visible_to_client', true)
                ->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
                ->whereDoesntHave('rating')
                ->count(),
            'unreadMessages' => $this->unreadMessagesCount($access),
            'unreadTicketReplies' => $this->unreadTicketRepliesCount($access),
            'pendingMeetings' => $this->pendingMeetingsCount($access),
            'unreadAlerts' => $this->unreadAlertsCount($access),
        ];
    }

    public function unreadAlertsCount(ClientPortalAccess $access): int
    {
        return PortalClientAlert::query()
            ->where('client_portal_access_id', $access->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documentRequestsForAccess(ClientPortalAccess $access, ?int $limit = null): array
    {
        return $access->client->documentRequests()
            ->with(['items.category', 'items.document.latestVersion'])
            ->latest()
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn (DocumentRequest $request): array => $this->documentRequestSummary($request))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function documentRequestDetail(DocumentRequest $request): array
    {
        $request->loadMissing(['items.category', 'items.document.latestVersion']);

        return $this->documentRequestSummary($request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ticketsForAccess(ClientPortalAccess $access, ?int $limit = null): array
    {
        return $access->client->tickets()
            ->with('rating')
            ->withCount(['messages as visible_messages_count' => fn ($query) => $query->where('visible_to_client', true)])
            ->where('visible_to_client', true)
            ->latest()
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn (Ticket $ticket): array => $this->ticketListItem($ticket, $access))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function financePageForAccess(ClientPortalAccess $access): array
    {
        $receivables = $access->client->receivables()
            ->with('charge')
            ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL, Receivable::STATUS_PAID])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->get();

        $openReceivables = $receivables->filter(
            fn (Receivable $receivable): bool => in_array($receivable->status, [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL], true)
        );

        return [
            'summary' => [
                'open_balance_cents' => $openReceivables->sum(fn (Receivable $receivable): int => $receivable->balanceCents()),
                'overdue_count' => $openReceivables->filter(fn (Receivable $receivable): bool => $receivable->isOverdue())->count(),
                'open_count' => $openReceivables->count(),
            ],
            'payment_instructions' => $access->organization->payment_instructions,
            'receivables' => $receivables
                ->map(fn (Receivable $receivable): array => [
                    'id' => $receivable->id,
                    'description' => $receivable->description,
                    'status' => $receivable->status,
                    'amount_cents' => $receivable->amount_cents,
                    'paid_amount_cents' => $receivable->paid_amount_cents,
                    'balance_cents' => $receivable->balanceCents(),
                    'due_at' => DisplayFormat::date($receivable->due_at),
                    'due_at_raw' => $receivable->due_at?->toDateString(),
                    'is_overdue' => $receivable->isOverdue(),
                    'payment_reference' => $receivable->payment_reference,
                    'payment_url' => $receivable->payment_url,
                    'notes' => $receivable->notes,
                    'can_pay' => in_array($receivable->status, [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL], true)
                        && $receivable->charge !== null,
                    'charge' => $receivable->charge?->toPortalArray(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function receivablesForAccess(ClientPortalAccess $access): array
    {
        return $this->financePageForAccess($access)['receivables'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function announcementsForAccess(ClientPortalAccess $access): array
    {
        return Announcement::query()
            ->where('organization_id', $access->organization_id)
            ->where(function ($query) use ($access): void {
                $query->whereNull('client_id')->orWhere('client_id', $access->client_id);
            })
            ->where('status', Announcement::STATUS_PUBLISHED)
            ->latest('published_at')
            ->get(['id', 'title', 'body', 'published_at'])
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'published_at' => DisplayFormat::dateTime($announcement->published_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reportsForAccess(ClientPortalAccess $access): array
    {
        return $access->client->generatedReports()
            ->where('status', GeneratedReport::STATUS_RELEASED)
            ->latest('released_at')
            ->get()
            ->map(fn (GeneratedReport $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'released_at' => DisplayFormat::dateTime($report->released_at),
                'summary' => [
                    'tasks_completed' => $report->payload['tasks']['completed'] ?? 0,
                    'tickets_open' => $report->payload['tickets']['open'] ?? 0,
                ],
                'download_url' => route('client-portal.reports.download', $report),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function meetingsForAccess(ClientPortalAccess $access): array
    {
        return CalendarEvent::query()
            ->where('organization_id', $access->organization_id)
            ->where('client_id', $access->client_id)
            ->where('type', CalendarEventType::Meeting)
            ->where('requires_portal_confirmation', true)
            ->whereNotIn('status', [CalendarEvent::STATUS_CANCELLED, CalendarEvent::STATUS_DONE])
            ->where('starts_at', '>=', now()->subDay())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $event): array => $this->meetingItem($event, $access))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function profileForAccess(ClientPortalAccess $access): array
    {
        $primaryContact = $access->client->contacts()
            ->where('is_primary', true)
            ->first();

        $pendingRequest = ClientProfileUpdateRequest::query()
            ->where('client_portal_access_id', $access->id)
            ->where('status', ClientProfileUpdateRequest::STATUS_PENDING)
            ->latest()
            ->first();

        return [
            'name' => $access->name,
            'email' => $access->email,
            'phone' => $primaryContact?->phone,
            'whatsapp' => $primaryContact?->whatsapp,
            'pending_request' => $pendingRequest ? [
                'id' => $pendingRequest->id,
                'changes' => $pendingRequest->changes,
                'created_at' => DisplayFormat::dateTime($pendingRequest->created_at),
            ] : null,
        ];
    }

    public function markMessagesRead(ClientPortalAccess $access): void
    {
        $access->update(['messages_last_read_at' => now()]);
    }

    public function markTicketRead(Ticket $ticket, ClientPortalAccess $access): void
    {
        abort_unless($ticket->client_id === $access->client_id && $ticket->visible_to_client, 404);

        TicketPortalRead::query()->updateOrCreate([
            'ticket_id' => $ticket->id,
            'client_portal_access_id' => $access->id,
        ], [
            'organization_id' => $access->organization_id,
            'last_read_at' => now(),
        ]);
    }

    public function unreadMessagesCount(ClientPortalAccess $access): int
    {
        return $access->client->messages()
            ->where('direction', ClientMessage::DIRECTION_OUTBOUND)
            ->where('channel', 'portal')
            ->when(
                $access->messages_last_read_at,
                fn ($query) => $query->where('created_at', '>', $access->messages_last_read_at),
            )
            ->count();
    }

    public function unreadTicketRepliesCount(ClientPortalAccess $access): int
    {
        $readMap = TicketPortalRead::query()
            ->where('client_portal_access_id', $access->id)
            ->pluck('last_read_at', 'ticket_id');

        return $access->client->tickets()
            ->where('visible_to_client', true)
            ->get()
            ->filter(function (Ticket $ticket) use ($readMap): bool {
                $lastRead = $readMap->get($ticket->id);

                return $ticket->messages()
                    ->where('visible_to_client', true)
                    ->where('sender_type', TicketMessage::SENDER_INTERNAL)
                    ->when($lastRead, fn ($query) => $query->where('created_at', '>', $lastRead))
                    ->exists();
            })
            ->count();
    }

    public function ticketHasUnreadReplies(Ticket $ticket, ClientPortalAccess $access): bool
    {
        $lastRead = TicketPortalRead::query()
            ->where('ticket_id', $ticket->id)
            ->where('client_portal_access_id', $access->id)
            ->value('last_read_at');

        return $ticket->messages()
            ->where('visible_to_client', true)
            ->where('sender_type', TicketMessage::SENDER_INTERNAL)
            ->when($lastRead, fn ($query) => $query->where('created_at', '>', $lastRead))
            ->exists();
    }

    public function meetingCanBeConfirmed(CalendarEvent $event, ClientPortalAccess $access): bool
    {
        if ($event->client_id !== $access->client_id || ! $event->requires_portal_confirmation) {
            return false;
        }

        if (in_array($event->status, [CalendarEvent::STATUS_CANCELLED, CalendarEvent::STATUS_DONE], true)) {
            return false;
        }

        if ($event->confirmation_deadline_at && $event->confirmation_deadline_at->isPast()) {
            return false;
        }

        return $event->portal_confirmation_status === null
            || $event->portal_confirmation_status === CalendarEvent::PORTAL_CONFIRMATION_PENDING;
    }

    public function hasPortalConsent(Client $client): bool
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
    public function build(ClientPortalAccess $access): array
    {
        return [
            ...$this->layout($access),
            'hasPortalCommunicationConsent' => $this->hasPortalConsent($access->client),
            'documentRequests' => $this->documentRequestsForAccess($access, 10),
            'receivables' => $this->receivablesForAccess($access),
            'tickets' => $this->ticketsForAccess($access, 20),
            'openTicketsCount' => $this->navigationCounts($access)['openTickets'],
            'messages' => $this->messagesForAccess($access),
            'announcements' => $this->announcementsForAccess($access),
            'reports' => $this->reportsForAccess($access),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesForAccess(ClientPortalAccess $access, ?int $sinceId = null): array
    {
        return $access->client->messages()
            ->with('sentBy')
            ->where(function ($query) use ($access): void {
                $query->where(function ($query): void {
                    $query->where('direction', ClientMessage::DIRECTION_OUTBOUND)
                        ->where('channel', 'portal');
                })->orWhere('client_portal_access_id', $access->id);
            })
            ->when($sinceId, fn ($query) => $query->where('id', '>', $sinceId))
            ->orderBy('created_at')
            ->limit($sinceId ? 50 : 100)
            ->get()
            ->map(fn (ClientMessage $message): array => $this->messageSummary($message, $access))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesForClient(Client $client, ?int $sinceId = null): array
    {
        return $client->messages()
            ->with(['sentBy', 'portalAccess'])
            ->when($sinceId, fn ($query) => $query->where('id', '>', $sinceId))
            ->orderBy('created_at')
            ->limit($sinceId ? 50 : 100)
            ->get()
            ->map(fn (ClientMessage $message): array => [
                'id' => $message->id,
                'direction' => $message->direction,
                'channel' => $message->channel,
                'subject' => $message->subject,
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
                'sender_name' => $message->direction === ClientMessage::DIRECTION_OUTBOUND
                    ? ($message->sentBy?->name ?? 'Escritório')
                    : ($message->external_name ?? $message->portalAccess?->name ?? 'Cliente'),
                'can_open_ticket' => $message->direction === ClientMessage::DIRECTION_INBOUND
                    && ! $message->ticket_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function ticketDetailForAccess(Ticket $ticket, ClientPortalAccess $access): array
    {
        abort_unless($ticket->client_id === $access->client_id && $ticket->visible_to_client, 404);

        $ticket->loadMissing(['messages.user', 'messages.portalAccess', 'messages.attachments', 'rating']);

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'created_at' => DisplayFormat::dateTime($ticket->created_at),
            'updated_at' => DisplayFormat::dateTime($ticket->updated_at),
            'can_reply' => $this->ticketCanBeRepliedByClient($ticket),
            'needs_response' => $ticket->status === Ticket::STATUS_WAITING_CLIENT,
            'is_finalized' => $ticket->isFinalized(),
            'can_rate' => $this->ticketCanBeRatedByClient($ticket),
            'rating' => $this->ticketRatingItem($ticket),
            'messages' => $ticket->messages
                ->where('visible_to_client', true)
                ->sortBy('created_at')
                ->map(fn (TicketMessage $message): array => $this->ticketMessageItem($message, $access))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketListItem(Ticket $ticket, ?ClientPortalAccess $access = null): array
    {
        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'status' => $ticket->status,
            'created_at' => DisplayFormat::date($ticket->created_at),
            'updated_at' => DisplayFormat::dateTime($ticket->updated_at),
            'messages_count' => (int) ($ticket->visible_messages_count ?? 0),
            'can_reply' => $this->ticketCanBeRepliedByClient($ticket),
            'needs_response' => $ticket->status === Ticket::STATUS_WAITING_CLIENT,
            'is_closed' => in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true),
            'is_finalized' => $ticket->isFinalized(),
            'can_rate' => $this->ticketCanBeRatedByClient($ticket),
            'has_rating' => $ticket->relationLoaded('rating') ? $ticket->rating !== null : $ticket->rating()->exists(),
            'rating_score' => $ticket->rating?->rating,
            'has_unread' => $access ? $this->ticketHasUnreadReplies($ticket, $access) : false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ticketRatingItem(Ticket $ticket): ?array
    {
        if ($ticket->rating === null) {
            return null;
        }

        return [
            'score' => $ticket->rating->rating,
            'comment' => $ticket->rating->comment,
            'rated_at' => DisplayFormat::dateTime($ticket->rating->created_at),
        ];
    }

    public function ticketCanBeRatedByClient(Ticket $ticket): bool
    {
        return $ticket->isFinalized() && $ticket->rating === null;
    }

    public function ticketCanBeRepliedByClient(Ticket $ticket): bool
    {
        return ! in_array($ticket->status, [Ticket::STATUS_CLOSED], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ticketMessagesForAccess(Ticket $ticket, ClientPortalAccess $access, ?int $sinceId = null): array
    {
        abort_unless($ticket->client_id === $access->client_id && $ticket->visible_to_client, 404);

        return $ticket->messages()
            ->where('visible_to_client', true)
            ->when($sinceId, fn ($query) => $query->where('id', '>', $sinceId))
            ->with(['user', 'portalAccess', 'attachments'])
            ->orderBy('created_at')
            ->limit($sinceId ? 50 : 100)
            ->get()
            ->map(fn (TicketMessage $message): array => $this->ticketMessageItem($message, $access))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketMessageItem(TicketMessage $message, ClientPortalAccess $access): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'sender_name' => $message->sender_type === TicketMessage::SENDER_CLIENT
                ? ($message->portalAccess?->name ?? $access->name)
                : ($message->user?->name ?? 'Escritório'),
            'attachments' => $message->relationLoaded('attachments')
                ? $message->attachments
                    ->where('visible_to_client', true)
                    ->map(fn (TicketMessageAttachment $attachment): array => $this->attachmentItem($attachment))
                    ->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentItem(TicketMessageAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'download_url' => route('client-portal.tickets.attachments.download', $attachment),
        ];
    }

    private function pendingDocumentItemsCount(int $clientId): int
    {
        return DocumentRequestItem::query()
            ->whereHas('documentRequest', fn ($query) => $query->where('client_id', $clientId))
            ->whereIn('status', [DocumentRequestItem::STATUS_REQUESTED, DocumentRequestItem::STATUS_REJECTED])
            ->count();
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
            'can_open_ticket' => $message->direction === ClientMessage::DIRECTION_OUTBOUND
                && ! $message->ticket_id,
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
            'due_at' => DisplayFormat::date($request->due_at),
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
            'due_at' => DisplayFormat::date($item->due_at ?? $request->due_at),
            'rejection_reason' => $item->rejection_reason,
            'category' => $item->category ? ['name' => $item->category->name] : null,
            'can_upload' => in_array($item->status, [DocumentRequestItem::STATUS_REQUESTED, DocumentRequestItem::STATUS_REJECTED], true),
            'uploaded_file_name' => $item->document?->latestVersion?->original_name,
        ];
    }

    private function pendingMeetingsCount(ClientPortalAccess $access): int
    {
        return CalendarEvent::query()
            ->where('organization_id', $access->organization_id)
            ->where('client_id', $access->client_id)
            ->where('type', CalendarEventType::Meeting)
            ->where('requires_portal_confirmation', true)
            ->whereNotIn('status', [CalendarEvent::STATUS_CANCELLED, CalendarEvent::STATUS_DONE])
            ->where('starts_at', '>=', now()->subDay())
            ->where(function ($query): void {
                $query->whereNull('portal_confirmation_status')
                    ->orWhere('portal_confirmation_status', CalendarEvent::PORTAL_CONFIRMATION_PENDING);
            })
            ->when(
                true,
                fn ($query) => $query->where(function ($query): void {
                    $query->whereNull('confirmation_deadline_at')
                        ->orWhere('confirmation_deadline_at', '>', now());
                }),
            )
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function meetingItem(CalendarEvent $event, ClientPortalAccess $access): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => DisplayFormat::dateTime($event->starts_at),
            'ends_at' => DisplayFormat::dateTime($event->ends_at),
            'location' => $event->location,
            'status' => $event->status,
            'confirmation_status' => $event->portal_confirmation_status ?? CalendarEvent::PORTAL_CONFIRMATION_PENDING,
            'confirmation_notes' => $event->portal_confirmation_notes,
            'confirmation_deadline_at' => DisplayFormat::dateTime($event->confirmation_deadline_at),
            'can_confirm' => $this->meetingCanBeConfirmed($event, $access),
        ];
    }
}
