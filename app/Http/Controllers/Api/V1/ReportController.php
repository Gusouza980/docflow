<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Actions\Reports\ExportReportSpreadsheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExportReportRequest;
use App\Http\Requests\Api\V1\GenerateMonthlyClientReportRequest;
use App\Http\Resources\GeneratedReportResource;
use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\ReportDelivery;
use App\Reports\ReportMetrics;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function overview(Request $request, OrganizationContext $organizationContext, ReportMetrics $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->overview($organizationContext->membership(), $request->query())]);
    }

    public function productivity(Request $request, OrganizationContext $organizationContext, ReportMetrics $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->productivity($organizationContext->membership(), $request->query())]);
    }

    public function documents(Request $request, OrganizationContext $organizationContext, ReportMetrics $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->documents($organizationContext->membership(), $request->query())]);
    }

    public function finance(Request $request, OrganizationContext $organizationContext, ReportMetrics $metrics): JsonResponse
    {
        abort_unless($metrics->canAccessFinance($organizationContext->membership()), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $metrics->finance($organizationContext->membership(), $request->query())]);
    }

    public function monthly(Client $client, Request $request, OrganizationContext $organizationContext, ReportMetrics $metrics): JsonResponse
    {
        $this->authorizeClient($client, $organizationContext);

        return response()->json(['data' => $metrics->clientMonthly($client, $organizationContext->membership(), $request->query())]);
    }

    public function generateMonthly(GenerateMonthlyClientReportRequest $request, Client $client, OrganizationContext $organizationContext, ReportMetrics $metrics): GeneratedReportResource
    {
        $this->authorizeClient($client, $organizationContext);
        $payload = $metrics->clientMonthly($client, $organizationContext->membership(), $request->validated());

        $report = GeneratedReport::create([
            'organization_id' => $organizationContext->id(),
            'client_id' => $client->id,
            'generated_by_user_id' => $request->user()->id,
            'type' => GeneratedReport::TYPE_CLIENT_MONTHLY,
            'title' => $request->validated('title') ?: "Relatório mensal - {$client->display_name}",
            'status' => GeneratedReport::STATUS_REVIEWED,
            'filters' => $request->validated(),
            'payload' => $payload,
            'reviewed_at' => now(),
        ]);

        return new GeneratedReportResource($report->load('client'));
    }

    public function release(GeneratedReport $report, OrganizationContext $organizationContext): GeneratedReportResource
    {
        abort_if($report->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);

        $report->update([
            'status' => GeneratedReport::STATUS_RELEASED,
            'released_at' => now(),
        ]);

        ReportDelivery::create([
            'organization_id' => $organizationContext->id(),
            'generated_report_id' => $report->id,
            'channel' => 'portal',
            'status' => 'released',
            'delivered_at' => now(),
        ]);

        return new GeneratedReportResource($report->refresh()->load('client'));
    }

    public function export(
        ExportReportRequest $request,
        OrganizationContext $organizationContext,
        ExportReportSpreadsheet $exportReportSpreadsheet,
        RecordAuditLog $auditLog,
    ): StreamedResponse {
        $membership = $organizationContext->membership();
        $type = $request->validated('report_type');
        abort_unless($exportReportSpreadsheet->canExport($membership, $type), Response::HTTP_FORBIDDEN);

        $filters = $request->validated('filters') ?? [];
        $data = $exportReportSpreadsheet->data($membership, $type, $filters);

        $auditLog->execute('report.exported', $request->user(), $organizationContext->organization(), metadata: ['type' => $type], request: $request);

        return $exportReportSpreadsheet->streamDownload($type, $data, $membership, $filters);
    }

    private function authorizeClient(Client $client, OrganizationContext $organizationContext): void
    {
        abort_if($client->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);
    }
}
