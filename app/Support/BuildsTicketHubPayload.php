<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketMessage;

class BuildsTicketHubPayload
{
    /**
     * @return array<string, mixed>
     */
    public function listItem(Ticket $ticket): array
    {
        $ticket->loadMissing(['assignedTo.user', 'openedBy']);

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'due_at' => DisplayFormat::date($ticket->due_at),
            'is_overdue' => $ticket->due_at !== null
                && $ticket->due_at->isPast()
                && ! in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true),
            'assigned_to' => $ticket->assignedTo?->user?->name,
            'messages_count' => $ticket->messages_count ?? $ticket->messages()->count(),
            'opened_by_portal' => $ticket->opened_by_user_id === null,
            'created_at' => DisplayFormat::dateTime($ticket->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Ticket $ticket, bool $canViewInternalMessages = true): array
    {
        $ticket->loadMissing([
            'assignedTo.user',
            'openedBy',
            'messages.user',
            'messages.portalAccess',
            'messages.attachments',
            'rating.portalAccess',
        ]);

        $messages = $ticket->messages->sortBy('created_at');

        if (! $canViewInternalMessages) {
            $messages = $messages->where('visible_to_client', true);
        }

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'visible_to_client' => $ticket->visible_to_client,
            'due_at' => $ticket->due_at?->toDateString(),
            'assigned_to_member_id' => $ticket->assigned_to_member_id,
            'assigned_to' => $ticket->assignedTo?->user?->name,
            'opened_by' => $ticket->openedBy?->name,
            'opened_by_portal' => $ticket->opened_by_user_id === null,
            'resolved_at' => DisplayFormat::dateTime($ticket->resolved_at),
            'closed_at' => DisplayFormat::dateTime($ticket->closed_at),
            'created_at' => DisplayFormat::dateTime($ticket->created_at),
            'rating' => $ticket->rating ? [
                'score' => $ticket->rating->rating,
                'comment' => $ticket->rating->comment,
                'rated_at' => DisplayFormat::dateTime($ticket->rating->created_at),
                'rated_by' => $ticket->rating->portalAccess?->name ?? 'Cliente',
            ] : null,
            'messages' => $messages->map(fn (TicketMessage $message): array => $this->messageItem($message, $ticket->client_id))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messageItem(TicketMessage $message, int $clientId): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'body' => $message->body,
            'visible_to_client' => $message->visible_to_client,
            'created_at' => $message->created_at?->toISOString(),
            'sender_name' => $message->sender_type === TicketMessage::SENDER_CLIENT
                ? ($message->portalAccess?->name ?? 'Cliente')
                : ($message->user?->name ?? 'Equipe'),
            'attachments' => $message->relationLoaded('attachments')
                ? $message->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'visible_to_client' => $attachment->visible_to_client,
                    'download_url' => route('clients.tickets.attachments.download', [
                        'client' => $clientId,
                        'attachment' => $attachment->id,
                    ]),
                ])->values()
                : [],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return [
            ['value' => Ticket::STATUS_NEW, 'label' => 'Novo'],
            ['value' => Ticket::STATUS_TRIAGE, 'label' => 'Em análise'],
            ['value' => Ticket::STATUS_IN_PROGRESS, 'label' => 'Em execução'],
            ['value' => Ticket::STATUS_WAITING_CLIENT, 'label' => 'Aguardando cliente'],
            ['value' => Ticket::STATUS_WAITING_THIRD_PARTY, 'label' => 'Aguardando terceiro'],
            ['value' => Ticket::STATUS_RESOLVED, 'label' => 'Resolvido'],
            ['value' => Ticket::STATUS_CLOSED, 'label' => 'Encerrado'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function priorityOptions(): array
    {
        return [
            ['value' => Ticket::PRIORITY_LOW, 'label' => 'Baixa'],
            ['value' => Ticket::PRIORITY_NORMAL, 'label' => 'Normal'],
            ['value' => Ticket::PRIORITY_HIGH, 'label' => 'Alta'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(): array
    {
        return [
            ['value' => 'open', 'label' => 'Abertos'],
            ['value' => 'waiting_client', 'label' => 'Aguardando cliente'],
            ['value' => 'mine', 'label' => 'Meus chamados'],
            ['value' => 'overdue', 'label' => 'Vencidos'],
            ['value' => 'closed', 'label' => 'Encerrados'],
            ['value' => 'all', 'label' => 'Todos'],
        ];
    }
}
