<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SchedulerRunLog;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuditController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para consultar a auditoria.');
        }

        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);

        $action = $request->string('action')->toString();

        $logs = AuditLog::query()
            ->with('user')
            ->where('organization_id', $membership->organization_id)
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $actions = AuditLog::query()
            ->where('organization_id', $membership->organization_id)
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        return Inertia::render('Audit/Index', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'metadata' => $log->metadata ?? [],
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toISOString(),
                ]),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ],
            'filters' => ['action' => $action],
            'actionOptions' => $actions->map(fn (string $value): array => ['value' => $value, 'label' => $value])->all(),
            'observability' => [
                'failed_jobs_count' => DB::table('failed_jobs')->count(),
                'scheduler_runs' => SchedulerRunLog::query()
                    ->latest('ran_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (SchedulerRunLog $run): array => [
                        'command' => $run->command,
                        'ran_at' => $run->ran_at?->toISOString(),
                        'duration_ms' => $run->duration_ms,
                        'result' => $run->result,
                        'meta' => $run->meta ?? [],
                    ]),
            ],
        ]);
    }
}
