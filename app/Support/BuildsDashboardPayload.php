<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Contract;
use App\Models\OrganizationMember;
use App\Reports\ReportMetrics;
use Illuminate\Http\Request;

class BuildsDashboardPayload
{
    public function __construct(
        private ReportMetrics $reportMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request, OrganizationMember $membership): array
    {
        return $this->build($membership, $this->filtersFromRequest($request));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(OrganizationMember $membership, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $overview = $this->reportMetrics->overview($membership, $filters);
        $canAccessFinance = $this->reportMetrics->canAccessFinance($membership);
        $canAccessCrm = $this->reportMetrics->canAccessCrm($membership);
        $canAccessAutomations = $this->reportMetrics->canAccessAutomations($membership);
        $clientQuery = $this->reportMetrics->clientQuery($membership);
        $value = $this->reportMetrics->valueSummary($membership, $filters);
        $contractsRevenue = $this->reportMetrics->contractsRevenueSummary($membership);
        $commercial = $this->reportMetrics->commercialSummary($membership, $filters);
        $docflowRoi = $this->reportMetrics->docflowRoiSummary($membership, $filters);

        $metrics = [
            'active_clients' => (clone $clientQuery)->where('status', Client::STATUS_ACTIVE)->count(),
            'high_risk_clients' => (clone $clientQuery)->where('risk_level', Client::RISK_HIGH)->count(),
            'delinquent_clients' => (clone $clientQuery)->where('status', Client::STATUS_DELINQUENT)->count(),
            'without_primary_contact' => (clone $clientQuery)->whereDoesntHave('contacts', fn ($query) => $query->where('is_primary', true))->count(),
            'open_tasks' => $overview['tasks']['open'],
            'overdue_tasks' => $overview['tasks']['overdue'],
            'completed_tasks' => $overview['tasks']['completed'],
            'pending_documents' => $overview['documents']['pending'],
            'overdue_documents' => $overview['documents']['overdue'],
            'due_soon_documents' => $overview['documents']['due_soon'],
            'open_tickets' => $overview['communication']['open_tickets'],
            'expiring_contracts' => $membership->role === OrganizationMember::ROLE_READONLY
                ? 0
                : Contract::query()
                    ->whereBelongsTo($membership->organization)
                    ->whereIn('client_id', $this->reportMetrics->clientQuery($membership)->select('id'))
                    ->expiringWithinDays(30)
                    ->count(),
        ];

        if ($canAccessFinance) {
            $metrics['open_receivables_cents'] = $value['open_receivables_cents'];
            $metrics['overdue_receivables_cents'] = $value['overdue_receivables_cents'];
            $metrics['received_cents'] = $value['received_cents'];
            $metrics['net_period_cents'] = $value['net_period_cents'];
            $metrics['paid_payables_cents'] = $value['paid_payables_cents'];
        }

        return [
            'can_access_finance' => $canAccessFinance,
            'can_access_crm' => $canAccessCrm,
            'can_access_automations' => $canAccessAutomations,
            'period' => $overview['period'],
            'filters' => $filters,
            'value' => $value,
            'contracts_revenue' => $contractsRevenue,
            'commercial' => $commercial,
            'docflow_roi' => $docflowRoi,
            'metrics' => $metrics,
            'alerts' => $overview['alerts'],
            'structuralPendencies' => (clone $clientQuery)
                ->with('primaryResponsible.user')
                ->whereDoesntHave('contacts', fn ($query) => $query->where('is_primary', true))
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'display_name' => $client->display_name,
                    'status' => $client->status,
                    'responsible' => $client->primaryResponsible?->user?->name,
                    'href' => route('clients.show', $client, absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        return $this->normalizeFilters([
            'period' => $request->string('period')->toString() ?: 'month',
            'start_date' => $request->string('start_date')->toString(),
            'end_date' => $request->string('end_date')->toString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $period = $filters['period'] ?? 'month';

        if ($period === 'week') {
            return [
                'period' => 'week',
                'start_date' => now()->subDays(6)->toDateString(),
                'end_date' => now()->toDateString(),
            ];
        }

        if ($period === 'custom' && ! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            return [
                'period' => 'custom',
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
            ];
        }

        return [
            'period' => 'month',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->toDateString(),
        ];
    }
}
