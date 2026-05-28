<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\GenerateMonthlyClientReportRequest;
use App\Http\Requests\Web\StoreReportFilterRequest;
use App\Http\Requests\Web\StoreReportScheduleRequest;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\OrganizationMember;
use App\Models\ReportDelivery;
use App\Models\ReportSchedule;
use App\Models\SavedReportFilter;
use App\Reports\ReportMetrics;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext, ReportMetrics $metrics): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para acessar relatórios.');
        }

        $filters = $request->only(['start_date', 'end_date', 'client_id', 'status']);
        $clients = $metrics->clientQuery($membership)->orderBy('display_name')->get(['id', 'display_name']);

        return Inertia::render('Reports/Index', [
            'overview' => $metrics->overview($membership, $filters),
            'productivity' => $metrics->productivity($membership, $filters),
            'documents' => $metrics->documents($membership, $filters),
            'finance' => $metrics->canAccessFinance($membership) ? $metrics->finance($membership, $filters) : null,
            'savedFilters' => SavedReportFilter::query()
                ->whereBelongsTo($membership->organization)
                ->where(fn ($query) => $query->where('user_id', $request->user()->id)->orWhere('is_shared', true))
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (SavedReportFilter $filter): array => $this->filterSummary($filter)),
            'generatedReports' => GeneratedReport::query()
                ->with('client')
                ->whereBelongsTo($membership->organization)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (GeneratedReport $report): array => $this->generatedReportSummary($report)),
            'schedules' => ReportSchedule::query()
                ->with('client')
                ->whereBelongsTo($membership->organization)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ReportSchedule $schedule): array => $this->scheduleSummary($schedule)),
            'filters' => $filters,
            'options' => [
                'clients' => $clients->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])->values(),
            ],
            'can' => [
                'finance' => $metrics->canAccessFinance($membership),
                'schedule' => $membership->isAdmin() || $membership->isManager(),
            ],
        ]);
    }

    public function storeFilter(StoreReportFilterRequest $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);

        SavedReportFilter::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'user_id' => $request->user()->id,
            'filters' => $request->validated('filters') ?? [],
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return redirect()->route('reports.index')->with('status', 'Filtro salvo.');
    }

    public function storeSchedule(StoreReportScheduleRequest $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);

        ReportSchedule::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
            'filters' => $request->validated('filters') ?? [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('reports.index')->with('status', 'Agendamento cadastrado.');
    }

    public function generateMonthly(GenerateMonthlyClientReportRequest $request, WebOrganizationContext $webOrganizationContext, ReportMetrics $metrics): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        $client = Client::query()->whereBelongsTo($membership->organization)->findOrFail($request->validated('client_id'));
        Gate::authorize('view', $client);

        $payload = $metrics->clientMonthly($client, $membership, $request->validated());

        GeneratedReport::create([
            'organization_id' => $membership->organization_id,
            'client_id' => $client->id,
            'generated_by_user_id' => $request->user()->id,
            'type' => GeneratedReport::TYPE_CLIENT_MONTHLY,
            'title' => $request->validated('title') ?: "Relatório mensal - {$client->display_name}",
            'status' => GeneratedReport::STATUS_REVIEWED,
            'filters' => $request->validated(),
            'payload' => $payload,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('reports.index')->with('status', 'Relatório mensal gerado para revisão.');
    }

    public function release(GeneratedReport $report, Request $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($report->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $report->update([
            'status' => GeneratedReport::STATUS_RELEASED,
            'released_at' => now(),
        ]);

        ReportDelivery::create([
            'organization_id' => $membership->organization_id,
            'generated_report_id' => $report->id,
            'channel' => 'portal',
            'status' => 'released',
            'delivered_at' => now(),
        ]);

        return redirect()->route('reports.index')->with('status', 'Relatório liberado ao portal.');
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);

        return $membership;
    }

    private function filterSummary(SavedReportFilter $filter): array
    {
        return [
            'id' => $filter->id,
            'name' => $filter->name,
            'report_type' => $filter->report_type,
            'filters' => $filter->filters ?? [],
            'is_shared' => $filter->is_shared,
        ];
    }

    private function generatedReportSummary(GeneratedReport $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'type' => $report->type,
            'status' => $report->status,
            'client' => $report->client ? ['id' => $report->client->id, 'name' => $report->client->display_name] : null,
            'released_at' => $report->released_at?->toISOString(),
            'created_at' => $report->created_at?->toISOString(),
        ];
    }

    private function scheduleSummary(ReportSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'report_type' => $schedule->report_type,
            'frequency' => $schedule->frequency,
            'is_active' => $schedule->is_active,
            'next_run_at' => $schedule->next_run_at?->toDateString(),
            'client' => $schedule->client ? ['id' => $schedule->client->id, 'name' => $schedule->client->display_name] : null,
        ];
    }
}
