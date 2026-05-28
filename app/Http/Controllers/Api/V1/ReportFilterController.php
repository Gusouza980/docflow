<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportFilterRequest;
use App\Http\Resources\SavedReportFilterResource;
use App\Models\SavedReportFilter;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ReportFilterController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $filters = SavedReportFilter::query()
            ->whereBelongsTo($organizationContext->organization())
            ->where(function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id)->orWhere('is_shared', true);
            })
            ->when($request->string('report_type')->toString(), fn ($query, string $type) => $query->where('report_type', $type))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return SavedReportFilterResource::collection($filters);
    }

    public function store(StoreReportFilterRequest $request, OrganizationContext $organizationContext): SavedReportFilterResource
    {
        $filter = SavedReportFilter::create([
            ...$request->validated(),
            'organization_id' => $organizationContext->id(),
            'user_id' => $request->user()->id,
            'filters' => $request->validated('filters') ?? [],
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return new SavedReportFilterResource($filter);
    }

    public function update(StoreReportFilterRequest $request, SavedReportFilter $filter, OrganizationContext $organizationContext): SavedReportFilterResource
    {
        $this->authorizeFilter($filter, $organizationContext, $request);

        $filter->update([
            ...$request->validated(),
            'filters' => $request->validated('filters') ?? [],
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return new SavedReportFilterResource($filter);
    }

    public function destroy(SavedReportFilter $filter, Request $request, OrganizationContext $organizationContext): \Illuminate\Http\Response
    {
        $this->authorizeFilter($filter, $organizationContext, $request);
        $filter->delete();

        return response()->noContent();
    }

    private function authorizeFilter(SavedReportFilter $filter, OrganizationContext $organizationContext, Request $request): void
    {
        abort_if($filter->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);
        abort_if($filter->user_id !== $request->user()->id && ! $organizationContext->membership()?->isAdmin() && ! $organizationContext->membership()?->isManager(), Response::HTTP_FORBIDDEN);
    }
}
