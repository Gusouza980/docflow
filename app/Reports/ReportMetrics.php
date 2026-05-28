<?php

namespace App\Reports;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\DocumentRequestItem;
use App\Models\OrganizationMember;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportMetrics
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(OrganizationMember $membership, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);
        $clientQuery = $this->clientQuery($membership);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'clients' => [
                'active' => (clone $clientQuery)->where('status', Client::STATUS_ACTIVE)->count(),
                'high_risk' => (clone $clientQuery)->where('risk_level', Client::RISK_HIGH)->count(),
                'delinquent' => (clone $clientQuery)->where('status', Client::STATUS_DELINQUENT)->count(),
                'without_primary_contact' => (clone $clientQuery)->whereDoesntHave('contacts', fn (Builder $query) => $query->where('is_primary', true))->count(),
            ],
            'tasks' => $this->taskSummary($membership, $start, $end),
            'documents' => $this->documentSummary($membership),
            'communication' => [
                'messages' => ClientMessage::query()->whereBelongsTo($membership->organization)->whereBetween('created_at', [$start, $end])->count(),
                'open_tickets' => Ticket::query()->whereBelongsTo($membership->organization)->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count(),
            ],
            'alerts' => $this->alerts($membership),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function productivity(OrganizationMember $membership, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);

        $members = OrganizationMember::query()
            ->with('user')
            ->whereBelongsTo($membership->organization)
            ->get()
            ->map(function (OrganizationMember $member) use ($start, $end): array {
                $taskQuery = Task::query()->where('assigned_to_member_id', $member->id);

                return [
                    'member_id' => $member->id,
                    'name' => $member->user?->name,
                    'open_tasks' => (clone $taskQuery)->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED])->count(),
                    'completed_tasks' => (clone $taskQuery)->where('status', Task::STATUS_COMPLETED)->whereBetween('completed_at', [$start, $end])->count(),
                    'overdue_tasks' => (clone $taskQuery)->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])->count(),
                    'open_tickets' => Ticket::query()->where('assigned_to_member_id', $member->id)->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count(),
                ];
            })
            ->values();

        return ['period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()], 'members' => $members];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function documents(OrganizationMember $membership, array $filters = []): array
    {
        $query = DocumentRequestItem::query()
            ->with(['documentRequest.client', 'category'])
            ->whereBelongsTo($membership->organization)
            ->when($filters['client_id'] ?? null, fn (Builder $query, int|string $clientId) => $query->whereHas('documentRequest', fn (Builder $query) => $query->where('client_id', $clientId)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        return [
            'summary' => [
                'pending' => (clone $query)->whereIn('status', [DocumentRequestItem::STATUS_REQUESTED, DocumentRequestItem::STATUS_REJECTED])->count(),
                'overdue' => (clone $query)->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])->count(),
                'due_soon' => (clone $query)->whereBetween('due_at', [now()->toDateString(), now()->addDays(7)->toDateString()])->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])->count(),
            ],
            'items' => (clone $query)
                ->orderBy('due_at')
                ->limit(50)
                ->get()
                ->map(fn (DocumentRequestItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'status' => $item->status,
                    'due_at' => $item->due_at?->toDateString(),
                    'client' => $item->documentRequest?->client?->display_name,
                    'category' => $item->category?->name,
                    'request' => $item->documentRequest?->title,
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function finance(OrganizationMember $membership, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);

        $receivables = Receivable::query()->whereBelongsTo($membership->organization);
        $payables = Payable::query()->whereBelongsTo($membership->organization);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'summary' => [
                'open_receivables_cents' => (int) (clone $receivables)->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'overdue_receivables_cents' => (int) (clone $receivables)->whereDate('due_at', '<', now()->toDateString())->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'received_cents' => (int) Payment::query()->where('organization_id', $membership->organization_id)->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])->sum('amount_cents'),
                'open_payables_cents' => (int) (clone $payables)->whereIn('status', [Payable::STATUS_OPEN, Payable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'paid_payables_cents' => (int) (clone $payables)->where('status', Payable::STATUS_PAID)->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])->sum('paid_amount_cents'),
            ],
            'delinquent_clients' => (clone $receivables)
                ->with('client')
                ->select('client_id', DB::raw('sum(amount_cents - paid_amount_cents) as balance_cents'))
                ->whereDate('due_at', '<', now()->toDateString())
                ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
                ->groupBy('client_id')
                ->orderByDesc('balance_cents')
                ->limit(10)
                ->get()
                ->map(fn (Receivable $receivable): array => [
                    'client' => $receivable->client?->display_name,
                    'balance_cents' => (int) $receivable->balance_cents,
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function clientMonthly(Client $client, OrganizationMember $membership, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'client' => ['id' => $client->id, 'name' => $client->display_name],
            'tasks' => [
                'completed' => $client->tasks()->where('status', Task::STATUS_COMPLETED)->whereBetween('completed_at', [$start, $end])->count(),
                'open' => $client->tasks()->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED])->count(),
            ],
            'documents' => [
                'requests' => $client->documentRequests()->whereBetween('created_at', [$start, $end])->count(),
                'pending_items' => DocumentRequestItem::query()->whereBelongsTo($membership->organization)->whereHas('documentRequest', fn (Builder $query) => $query->whereBelongsTo($client))->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])->count(),
            ],
            'tickets' => [
                'opened' => $client->tickets()->whereBetween('created_at', [$start, $end])->count(),
                'open' => $client->tickets()->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count(),
            ],
            'finance' => [
                'open_receivables_cents' => (int) $client->receivables()->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'received_cents' => (int) Payment::query()->where('organization_id', $membership->organization_id)->whereHas('receivable', fn (Builder $query) => $query->whereBelongsTo($client))->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])->sum('amount_cents'),
            ],
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    public function period(array $filters): array
    {
        $start = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $end = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        return [$start, $end];
    }

    public function canAccessFinance(OrganizationMember $membership): bool
    {
        return in_array($membership->role, [OrganizationMember::ROLE_ADMIN, OrganizationMember::ROLE_MANAGER, OrganizationMember::ROLE_FINANCE], true);
    }

    public function clientQuery(OrganizationMember $membership): Builder
    {
        return Client::query()
            ->whereBelongsTo($membership->organization)
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function (Builder $query) use ($membership): void {
                $query->where(function (Builder $query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn (Builder $query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn (Builder $query) => $query->whereKey($membership->id));
                });
            });
    }

    /**
     * @return array<string, int>
     */
    private function taskSummary(OrganizationMember $membership, Carbon $start, Carbon $end): array
    {
        $query = Task::query()->whereBelongsTo($membership->organization);

        return [
            'open' => (clone $query)->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED])->count(),
            'overdue' => (clone $query)->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])->count(),
            'completed' => (clone $query)->where('status', Task::STATUS_COMPLETED)->whereBetween('completed_at', [$start, $end])->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function documentSummary(OrganizationMember $membership): array
    {
        $query = DocumentRequestItem::query()->whereBelongsTo($membership->organization);

        return [
            'pending' => (clone $query)->whereIn('status', [DocumentRequestItem::STATUS_REQUESTED, DocumentRequestItem::STATUS_REJECTED])->count(),
            'overdue' => (clone $query)->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])->count(),
            'due_soon' => (clone $query)->whereBetween('due_at', [now()->toDateString(), now()->addDays(7)->toDateString()])->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])->count(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function alerts(OrganizationMember $membership): array
    {
        $alerts = [];

        $overdueTasks = Task::query()->whereBelongsTo($membership->organization)->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])->count();
        if ($overdueTasks > 0) {
            $alerts[] = ['type' => 'tasks', 'label' => "{$overdueTasks} tarefas atrasadas", 'href' => '/tasks'];
        }

        $overdueDocuments = DocumentRequestItem::query()->whereBelongsTo($membership->organization)->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])->count();
        if ($overdueDocuments > 0) {
            $alerts[] = ['type' => 'documents', 'label' => "{$overdueDocuments} documentos vencidos", 'href' => '/document-requests'];
        }

        return $alerts;
    }
}
