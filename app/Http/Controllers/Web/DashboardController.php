<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Reports\ReportMetrics;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WebOrganizationContext $webOrganizationContext, ReportMetrics $reportMetrics): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para visualizar o painel.');
        }

        $baseQuery = Client::query()
            ->whereBelongsTo($membership->organization)
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function ($query) use ($membership): void {
                $query->where(function ($query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                });
            });

        $overview = $reportMetrics->overview($membership);

        return Inertia::render('Dashboard/Index', [
            'metrics' => [
                'active' => (clone $baseQuery)->where('status', Client::STATUS_ACTIVE)->count(),
                'inactive' => (clone $baseQuery)->where('status', Client::STATUS_INACTIVE)->count(),
                'negotiation' => (clone $baseQuery)->where('status', Client::STATUS_NEGOTIATION)->count(),
                'delinquent' => (clone $baseQuery)->where('status', Client::STATUS_DELINQUENT)->count(),
                'closed' => (clone $baseQuery)->where('status', Client::STATUS_CLOSED)->count(),
                'high_risk' => (clone $baseQuery)->where('risk_level', Client::RISK_HIGH)->count(),
                'without_primary_contact' => (clone $baseQuery)->whereDoesntHave('contacts', fn ($query) => $query->where('is_primary', true))->count(),
                'without_responsible' => (clone $baseQuery)->whereDoesntHave('responsibles')->count(),
                'open_tasks' => $overview['tasks']['open'],
                'overdue_tasks' => $overview['tasks']['overdue'],
                'completed_tasks' => $overview['tasks']['completed'],
                'pending_documents' => $overview['documents']['pending'],
                'overdue_documents' => $overview['documents']['overdue'],
                'due_soon_documents' => $overview['documents']['due_soon'],
                'open_tickets' => $overview['communication']['open_tickets'],
            ],
            'alerts' => $overview['alerts'],
            'structuralPendencies' => (clone $baseQuery)
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
                ]),
        ]);
    }
}
