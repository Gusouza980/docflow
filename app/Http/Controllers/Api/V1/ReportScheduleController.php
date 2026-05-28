<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportScheduleRequest;
use App\Http\Resources\ReportScheduleResource;
use App\Models\Client;
use App\Models\ReportSchedule;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ReportScheduleController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $schedules = ReportSchedule::query()
            ->with('client')
            ->whereBelongsTo($organizationContext->organization())
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ReportScheduleResource::collection($schedules);
    }

    public function store(StoreReportScheduleRequest $request, OrganizationContext $organizationContext): ReportScheduleResource
    {
        $data = $request->validated();

        if (isset($data['client_id'])) {
            Client::query()->where('organization_id', $organizationContext->id())->findOrFail($data['client_id']);
        }

        abort_if($organizationContext->membership()?->isAdmin() === false && $organizationContext->membership()?->isManager() === false, Response::HTTP_FORBIDDEN);

        $schedule = ReportSchedule::create([
            ...$data,
            'organization_id' => $organizationContext->id(),
            'created_by_user_id' => $request->user()->id,
            'filters' => $data['filters'] ?? [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new ReportScheduleResource($schedule->load('client'));
    }
}
