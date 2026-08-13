<?php

namespace App\Reports;

use App\Models\AutomationLog;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Contract;
use App\Models\DocumentRequestItem;
use App\Models\Lead;
use App\Models\OrganizationMember;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\Ticket;
use App\Support\Billing\PlanLimitChecker;
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
        if (($filters['period'] ?? null) === 'week') {
            return [now()->subDays(6)->startOfDay(), now()->endOfDay()];
        }

        $start = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $end = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        return [$start, $end];
    }

    public function canAccessFinance(OrganizationMember $membership): bool
    {
        return in_array($membership->role, [OrganizationMember::ROLE_ADMIN, OrganizationMember::ROLE_MANAGER, OrganizationMember::ROLE_FINANCE], true);
    }

    public function canAccessCrm(OrganizationMember $membership): bool
    {
        if (! $membership->canViewCrm() || ! Plan::query()->exists()) {
            return false;
        }

        return app(PlanLimitChecker::class)->hasFeature($membership->organization, 'crm');
    }

    public function canAccessAutomations(OrganizationMember $membership): bool
    {
        if ((! $membership->isAdmin() && ! $membership->isManager()) || ! Plan::query()->exists()) {
            return false;
        }

        return app(PlanLimitChecker::class)->hasFeature($membership->organization, 'automations');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function valueSummary(OrganizationMember $membership, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);
        $previousFilters = $this->previousPeriodFilters($filters);
        [$previousStart, $previousEnd] = $this->period($previousFilters);

        if (! $this->canAccessFinance($membership)) {
            $tasks = $this->taskSummary($membership, $start, $end);
            $previousTasks = $this->taskSummary($membership, $previousStart, $previousEnd);
            $approvedDocuments = $this->approvedDocumentsInPeriod($membership, $start, $end);
            $previousApprovedDocuments = $this->approvedDocumentsInPeriod($membership, $previousStart, $previousEnd);
            $activeClients = (clone $this->clientQuery($membership))->where('status', Client::STATUS_ACTIVE)->count();

            return [
                'mode' => 'operational',
                'completed_tasks' => $tasks['completed'],
                'completed_tasks_delta' => $tasks['completed'] - $previousTasks['completed'],
                'approved_documents' => $approvedDocuments,
                'approved_documents_delta' => $approvedDocuments - $previousApprovedDocuments,
                'active_clients' => $activeClients,
                'previous_period' => [
                    'start' => $previousStart->toDateString(),
                    'end' => $previousEnd->toDateString(),
                ],
            ];
        }

        $finance = $this->finance($membership, $filters);
        $previousFinance = $this->finance($membership, $previousFilters);
        $received = (int) $finance['summary']['received_cents'];
        $previousReceived = (int) $previousFinance['summary']['received_cents'];
        $paidPayables = (int) $finance['summary']['paid_payables_cents'];

        return [
            'mode' => 'finance',
            'received_cents' => $received,
            'received_delta_cents' => $received - $previousReceived,
            'received_delta_percent' => $this->deltaPercent($received, $previousReceived),
            'open_receivables_cents' => (int) $finance['summary']['open_receivables_cents'],
            'overdue_receivables_cents' => (int) $finance['summary']['overdue_receivables_cents'],
            'paid_payables_cents' => $paidPayables,
            'net_period_cents' => $received - $paidPayables,
            'previous_period' => [
                'start' => $previousStart->toDateString(),
                'end' => $previousEnd->toDateString(),
                'received_cents' => $previousReceived,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contractsRevenueSummary(OrganizationMember $membership): ?array
    {
        if ($membership->role === OrganizationMember::ROLE_READONLY) {
            return null;
        }

        $contracts = Contract::query()
            ->whereBelongsTo($membership->organization)
            ->whereIn('client_id', $this->clientQuery($membership)->select('id'));

        $activeContracts = (clone $contracts)
            ->where('status', Contract::STATUS_ACTIVE)
            ->get(['amount_cents', 'billing_interval']);

        $mrrCents = (int) $activeContracts->sum(function (Contract $contract): int {
            return match ($contract->billing_interval) {
                Contract::BILLING_MONTH => (int) $contract->amount_cents,
                Contract::BILLING_YEAR => (int) round(((int) $contract->amount_cents) / 12),
                default => 0,
            };
        });

        $expiringQuery = (clone $contracts)->expiringWithinDays(30);

        return [
            'mrr_cents' => $mrrCents,
            'active_contracts' => $activeContracts->count(),
            'expiring_count' => (clone $expiringQuery)->count(),
            'at_risk_cents' => (int) (clone $expiringQuery)->sum('amount_cents'),
            'href' => route('contracts.index', ['expiring_soon' => 1], absolute: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    public function commercialSummary(OrganizationMember $membership, array $filters = []): ?array
    {
        if (! $this->canAccessCrm($membership)) {
            return null;
        }

        [$start, $end] = $this->period($filters);

        $openStages = [
            Lead::STAGE_NEW,
            Lead::STAGE_FIRST_CONTACT,
            Lead::STAGE_DIAGNOSIS,
            Lead::STAGE_PROPOSAL,
            Lead::STAGE_NEGOTIATION,
        ];

        $leads = Lead::query()->whereBelongsTo($membership->organization);

        $pipelineCents = (int) (clone $leads)
            ->whereIn('stage', $openStages)
            ->sum('estimated_value_cents');

        $wonLeadsCents = (int) (clone $leads)
            ->where('stage', Lead::STAGE_WON)
            ->whereNotNull('converted_at')
            ->whereBetween('converted_at', [$start, $end])
            ->sum('estimated_value_cents');

        $acceptedProposalsCents = (int) Proposal::query()
            ->whereHas('lead', function (Builder $query) use ($membership, $start, $end): void {
                $query->whereBelongsTo($membership->organization)
                    ->where(function (Builder $query) use ($start, $end): void {
                        $query->where('stage', '!=', Lead::STAGE_WON)
                            ->orWhereNull('converted_at')
                            ->orWhereNotBetween('converted_at', [$start, $end]);
                    });
            })
            ->where('status', Proposal::STATUS_ACCEPTED)
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereBetween('decided_at', [$start, $end])
                    ->orWhere(function (Builder $query) use ($start, $end): void {
                        $query->whereNull('decided_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->sum('amount_cents');

        return [
            'pipeline_cents' => $pipelineCents,
            'open_leads' => (clone $leads)->whereIn('stage', $openStages)->count(),
            'won_leads_cents' => $wonLeadsCents,
            'accepted_proposals_cents' => $acceptedProposalsCents,
            'gained_cents' => $wonLeadsCents + $acceptedProposalsCents,
            'href' => route('leads.index', absolute: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    public function docflowRoiSummary(OrganizationMember $membership, array $filters = []): ?array
    {
        if (! $this->canAccessAutomations($membership)) {
            return null;
        }

        [$start, $end] = $this->period($filters);
        $previousFilters = $this->previousPeriodFilters($filters);
        [$previousStart, $previousEnd] = $this->period($previousFilters);

        $current = $this->automationRoiTotals($membership, $start, $end);
        $previous = $this->automationRoiTotals($membership, $previousStart, $previousEnd);

        return [
            'runs' => $current['runs'],
            'estimated_minutes_saved' => $current['minutes'],
            'estimated_hours_saved' => round($current['minutes'] / 60, 1),
            'runs_delta' => $current['runs'] - $previous['runs'],
            'estimated_minutes_saved_delta' => $current['minutes'] - $previous['minutes'],
            'is_estimate' => true,
            'href' => route('automations.index', absolute: false),
            'previous_period' => [
                'start' => $previousStart->toDateString(),
                'end' => $previousEnd->toDateString(),
                'runs' => $previous['runs'],
                'estimated_minutes_saved' => $previous['minutes'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function previousPeriodFilters(array $filters): array
    {
        if (($filters['period'] ?? null) === 'month') {
            $reference = now();
            $previousMonth = $reference->copy()->subMonthNoOverflow();
            $previousDay = min($reference->day, $previousMonth->daysInMonth);

            return [
                'period' => 'custom',
                'start_date' => $previousMonth->copy()->startOfMonth()->toDateString(),
                'end_date' => $previousMonth->copy()->day($previousDay)->toDateString(),
            ];
        }

        [$start, $end] = $this->period($filters);
        $daySpan = max(1, (int) $start->diffInDays($end) + 1);
        $previousEnd = $start->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($daySpan - 1)->startOfDay();

        return [
            'period' => 'custom',
            'start_date' => $previousStart->toDateString(),
            'end_date' => $previousEnd->toDateString(),
        ];
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

    private function approvedDocumentsInPeriod(OrganizationMember $membership, Carbon $start, Carbon $end): int
    {
        return DocumentRequestItem::query()
            ->whereBelongsTo($membership->organization)
            ->where('status', DocumentRequestItem::STATUS_APPROVED)
            ->whereBetween('approved_at', [$start, $end])
            ->count();
    }

    private function deltaPercent(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array{runs: int, minutes: int}
     */
    private function automationRoiTotals(OrganizationMember $membership, Carbon $start, Carbon $end): array
    {
        $query = AutomationLog::query()
            ->whereBelongsTo($membership->organization)
            ->where('status', AutomationLog::STATUS_SUCCEEDED)
            ->whereBetween('ran_at', [$start, $end]);

        return [
            'runs' => (clone $query)->count(),
            'minutes' => (int) (clone $query)->sum('estimated_minutes_saved'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alerts(OrganizationMember $membership): array
    {
        $alerts = [];
        $clientQuery = $this->clientQuery($membership);
        $documentQuery = DocumentRequestItem::query()->whereBelongsTo($membership->organization);

        $overdueTasks = Task::query()
            ->whereBelongsTo($membership->organization)
            ->whereDate('due_at', '<', now()->toDateString())
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->count();

        if ($overdueTasks > 0) {
            $alerts[] = $this->alert(
                type: 'tasks_overdue',
                severity: 'danger',
                label: "{$overdueTasks} tarefa(s) atrasada(s)",
                count: $overdueTasks,
                href: route('tasks.index', ['flag' => 'overdue'], absolute: false),
            );
        }

        $overdueDocuments = (clone $documentQuery)
            ->whereDate('due_at', '<', now()->toDateString())
            ->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])
            ->count();

        if ($overdueDocuments > 0) {
            $alerts[] = $this->alert(
                type: 'documents_overdue',
                severity: 'danger',
                label: "{$overdueDocuments} documento(s) vencido(s)",
                count: $overdueDocuments,
                href: route('document-requests.index', ['overdue' => 1], absolute: false),
            );
        }

        $dueSoonDocuments = (clone $documentQuery)
            ->whereBetween('due_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])
            ->count();

        if ($dueSoonDocuments > 0) {
            $alerts[] = $this->alert(
                type: 'documents_due_soon',
                severity: 'warning',
                label: "{$dueSoonDocuments} documento(s) vence(m) em 7 dias",
                count: $dueSoonDocuments,
                href: route('document-requests.index', absolute: false),
            );
        }

        $delinquentClients = (clone $clientQuery)->where('status', Client::STATUS_DELINQUENT)->count();

        if ($delinquentClients > 0) {
            $alerts[] = $this->alert(
                type: 'clients_delinquent',
                severity: 'danger',
                label: "{$delinquentClients} cliente(s) inadimplente(s)",
                count: $delinquentClients,
                href: route('clients.index', ['status' => Client::STATUS_DELINQUENT], absolute: false),
            );
        }

        $highRiskClients = (clone $clientQuery)->where('risk_level', Client::RISK_HIGH)->count();

        if ($highRiskClients > 0) {
            $alerts[] = $this->alert(
                type: 'clients_high_risk',
                severity: 'warning',
                label: "{$highRiskClients} cliente(s) com alto risco",
                count: $highRiskClients,
                href: route('clients.index', ['risk_level' => Client::RISK_HIGH], absolute: false),
            );
        }

        if ($this->canAccessFinance($membership)) {
            $receivableQuery = Receivable::query()->whereBelongsTo($membership->organization);

            $overdueReceivables = (clone $receivableQuery)
                ->whereDate('due_at', '<', now()->toDateString())
                ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
                ->count();

            if ($overdueReceivables > 0) {
                $alerts[] = $this->alert(
                    type: 'receivables_overdue',
                    severity: 'danger',
                    label: "{$overdueReceivables} cobrança(s) vencida(s)",
                    count: $overdueReceivables,
                    href: route('finance.index', ['status' => Receivable::STATUS_OPEN], absolute: false),
                );
            }

            $dueSoonReceivables = (clone $receivableQuery)
                ->whereBetween('due_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
                ->count();

            if ($dueSoonReceivables > 0) {
                $alerts[] = $this->alert(
                    type: 'receivables_due_soon',
                    severity: 'warning',
                    label: "{$dueSoonReceivables} cobrança(s) vence(m) em 7 dias",
                    count: $dueSoonReceivables,
                    href: route('finance.index', ['status' => Receivable::STATUS_OPEN], absolute: false),
                );
            }
        }

        if ($membership->role !== OrganizationMember::ROLE_READONLY) {
            $expiringContracts = Contract::query()
                ->whereBelongsTo($membership->organization)
                ->whereIn('client_id', $this->clientQuery($membership)->select('id'))
                ->expiringWithinDays(30)
                ->count();

            if ($expiringContracts > 0) {
                $alerts[] = $this->alert(
                    type: 'contracts_expiring_soon',
                    severity: 'warning',
                    label: "{$expiringContracts} contrato(s) vence(m) em 30 dias",
                    count: $expiringContracts,
                    href: route('contracts.index', ['expiring_soon' => 1], absolute: false),
                );
            }
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>|null  $filter
     * @return array<string, mixed>
     */
    private function alert(string $type, string $severity, string $label, int $count, string $href, ?array $filter = null): array
    {
        return array_filter([
            'type' => $type,
            'severity' => $severity,
            'label' => $label,
            'count' => $count,
            'href' => $href,
            'filter' => $filter,
        ], fn (mixed $value): bool => $value !== null);
    }
}
