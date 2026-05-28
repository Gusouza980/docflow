<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Reports\ReportMetrics;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(OrganizationContext $organizationContext, ReportMetrics $reportMetrics): JsonResponse
    {
        $membership = $organizationContext->membership();
        $baseQuery = Client::query()
            ->whereBelongsTo($organizationContext->organization())
            ->when(! $membership?->isAdmin() && ! $membership?->isManager(), function ($query) use ($membership): void {
                $query->where(function ($query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                });
            });

        $overview = $reportMetrics->overview($membership);

        return response()->json([
            'data' => [
                'clients' => [
                    'active' => (clone $baseQuery)->where('status', Client::STATUS_ACTIVE)->count(),
                    'inactive' => (clone $baseQuery)->where('status', Client::STATUS_INACTIVE)->count(),
                    'negotiation' => (clone $baseQuery)->where('status', Client::STATUS_NEGOTIATION)->count(),
                    'delinquent' => (clone $baseQuery)->where('status', Client::STATUS_DELINQUENT)->count(),
                    'closed' => (clone $baseQuery)->where('status', Client::STATUS_CLOSED)->count(),
                    'high_risk' => (clone $baseQuery)->where('risk_level', Client::RISK_HIGH)->count(),
                    'without_primary_contact' => (clone $baseQuery)->whereDoesntHave('contacts', fn ($query) => $query->where('is_primary', true))->count(),
                    'without_responsible' => (clone $baseQuery)->whereDoesntHave('responsibles')->count(),
                ],
                'tasks' => $overview['tasks'],
                'documents' => $overview['documents'],
                'communication' => $overview['communication'],
                'alerts' => $overview['alerts'],
            ],
        ]);
    }
}
