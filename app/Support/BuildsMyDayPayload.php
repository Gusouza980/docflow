<?php

namespace App\Support;

use App\Models\Client;
use App\Models\DocumentRequestItem;
use App\Models\OrganizationMember;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

class BuildsMyDayPayload
{
    /**
     * @return array<string, mixed>
     */
    public function for(OrganizationMember $membership): array
    {
        $canAccessFinance = in_array($membership->role, [
            OrganizationMember::ROLE_ADMIN,
            OrganizationMember::ROLE_MANAGER,
            OrganizationMember::ROLE_FINANCE,
        ], true);

        $tasks = $this->tasks($membership);
        $documents = $this->documents($membership);
        $receivables = $canAccessFinance ? $this->receivables($membership) : [];
        $tickets = $this->tickets($membership);

        return [
            'sections' => [
                ['key' => 'tasks', 'label' => 'Tarefas', 'items' => $tasks],
                ['key' => 'documents', 'label' => 'Documentos', 'items' => $documents],
                ['key' => 'receivables', 'label' => 'Cobranças', 'items' => $receivables],
                ['key' => 'tickets', 'label' => 'Chamados', 'items' => $tickets],
            ],
            'counts' => [
                'tasks' => count($tasks),
                'documents' => count($documents),
                'receivables' => count($receivables),
                'tickets' => count($tickets),
                'total' => count($tasks) + count($documents) + count($receivables) + count($tickets),
            ],
            'can_access_finance' => $canAccessFinance,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tasks(OrganizationMember $membership): array
    {
        return Task::query()
            ->with('client')
            ->where('organization_id', $membership->organization_id)
            ->where('assigned_to_member_id', $membership->id)
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->where(fn (Builder $query) => $this->visibleClients($query, $membership, 'client_id'))
            ->orderByRaw('case when due_at is not null and due_at < ? then 0 else 1 end', [now()->toDateString()])
            ->orderBy('due_at')
            ->limit(20)
            ->get()
            ->map(fn (Task $task): array => $this->item(
                id: 'task-'.$task->id,
                type: 'task',
                title: $task->title,
                clientName: $task->client?->display_name,
                dueAt: $task->due_at,
                status: $task->status,
                href: route('tasks.show', $task, absolute: false),
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documents(OrganizationMember $membership): array
    {
        return DocumentRequestItem::query()
            ->with('documentRequest.client')
            ->where('organization_id', $membership->organization_id)
            ->whereIn('status', [
                DocumentRequestItem::STATUS_REQUESTED,
                DocumentRequestItem::STATUS_REJECTED,
            ])
            ->whereHas('documentRequest', fn (Builder $query) => $this->visibleClients($query, $membership, 'client_id'))
            ->orderByRaw('case when due_at is not null and due_at < ? then 0 else 1 end', [now()->toDateString()])
            ->orderBy('due_at')
            ->limit(20)
            ->get()
            ->map(fn (DocumentRequestItem $item): array => $this->item(
                id: 'document-'.$item->id,
                type: 'document',
                title: $item->title,
                clientName: $item->documentRequest?->client?->display_name,
                dueAt: $item->due_at,
                status: $item->status,
                href: route('document-requests.show', $item->document_request_id, absolute: false),
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receivables(OrganizationMember $membership): array
    {
        return Receivable::query()
            ->with('client')
            ->where('organization_id', $membership->organization_id)
            ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
            ->whereDate('due_at', '<', now()->toDateString())
            ->where(fn (Builder $query) => $this->visibleClients($query, $membership, 'client_id'))
            ->orderBy('due_at')
            ->limit(20)
            ->get()
            ->map(fn (Receivable $receivable): array => $this->item(
                id: 'receivable-'.$receivable->id,
                type: 'receivable',
                title: $receivable->description,
                clientName: $receivable->client?->display_name,
                dueAt: $receivable->due_at,
                status: $receivable->status,
                href: '/finance',
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tickets(OrganizationMember $membership): array
    {
        return Ticket::query()
            ->with('client')
            ->where('organization_id', $membership->organization_id)
            ->where('assigned_to_member_id', $membership->id)
            ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
            ->where(fn (Builder $query) => $this->visibleClients($query, $membership, 'client_id'))
            ->orderByRaw('case when due_at is not null and due_at < ? then 0 else 1 end', [now()->toDateString()])
            ->orderBy('due_at')
            ->limit(20)
            ->get()
            ->map(fn (Ticket $ticket): array => $this->item(
                id: 'ticket-'.$ticket->id,
                type: 'ticket',
                title: $ticket->title,
                clientName: $ticket->client?->display_name,
                dueAt: $ticket->due_at,
                status: $ticket->status,
                href: route('clients.show', ['client' => $ticket->client_id, 'tab' => 'tickets', 'ticket' => $ticket->id], absolute: false),
            ))
            ->all();
    }

    private function visibleClients(Builder $query, OrganizationMember $membership, string $column): Builder
    {
        if ($membership->isAdmin() || $membership->isManager()) {
            return $query;
        }

        $clientIds = Client::query()
            ->where('organization_id', $membership->organization_id)
            ->where(function ($clients) use ($membership): void {
                $clients->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                    ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                    ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
            })
            ->pluck('id');

        return $query->whereIn($column, $clientIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $id,
        string $type,
        string $title,
        ?string $clientName,
        mixed $dueAt,
        string $status,
        string $href,
    ): array {
        $dueDate = $dueAt?->toDateString();

        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'client_name' => $clientName,
            'due_at' => DisplayFormat::date($dueAt),
            'overdue' => $dueDate !== null && $dueDate < now()->toDateString(),
            'status' => $status,
            'href' => $href,
        ];
    }
}
