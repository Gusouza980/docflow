<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = Organization::query()
            ->whereHas('members', fn ($query) => $query
                ->whereBelongsTo($request->user())
                ->where('status', OrganizationMember::STATUS_ACTIVE))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return OrganizationResource::collection($organizations);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization, RecordAuditLog $auditLog): OrganizationResource
    {
        $before = $organization->only(['name', 'document', 'email', 'phone', 'timezone']);

        $organization->update($request->validated());

        $auditLog->execute('organization.updated', $request->user(), $organization, $organization, [
            'before' => $before,
            'after' => $organization->only(['name', 'document', 'email', 'phone', 'timezone']),
        ], $request);

        return new OrganizationResource($organization);
    }

    public function switch(Request $request, Organization $organization): OrganizationResource
    {
        Gate::authorize('view', $organization);

        setPermissionsTeamId($organization->id);
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        return new OrganizationResource($organization);
    }
}
