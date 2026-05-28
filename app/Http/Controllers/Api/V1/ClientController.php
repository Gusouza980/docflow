<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Requests\Api\V1\StoreClientResponsibleRequest;
use App\Http\Requests\Api\V1\UpdateClientAccessRequest;
use App\Http\Requests\Api\V1\UpdateClientRequest;
use App\Http\Requests\Api\V1\UpdateClientStatusRequest;
use App\Http\Resources\ClientResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $membership = $organizationContext->membership();

        $clients = Client::query()
            ->with(['primaryResponsible.user', 'tags'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('display_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('type')->toString(), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->integer('responsible_member_id'), fn ($query, int $memberId) => $query->whereHas('responsibles', fn ($query) => $query->whereKey($memberId)))
            ->when(! $membership?->isAdmin() && ! $membership?->isManager(), function ($query) use ($membership): void {
                $query->where(function ($query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        $client = DB::transaction(function () use ($request, $organizationContext): Client {
            $data = $request->validated();
            $responsibleIds = array_values(array_unique($data['responsible_member_ids']));
            $primaryResponsibleId = $responsibleIds[0];

            $client = Client::create([
                ...Arr::except($data, ['responsible_member_ids', 'individual_profile', 'company_profile']),
                'organization_id' => $organizationContext->id(),
                'primary_responsible_member_id' => $primaryResponsibleId,
            ]);

            $this->syncResponsibles($client, $responsibleIds, $primaryResponsibleId);

            if ($client->type === Client::TYPE_INDIVIDUAL) {
                $client->individualProfile()->create($data['individual_profile']);
            }

            if ($client->type === Client::TYPE_COMPANY) {
                $client->companyProfile()->create($data['company_profile']);
            }

            return $client;
        });

        $auditLog->execute('client.created', $request->user(), $organizationContext->organization(), $client, request: $request);

        return (new ClientResource($client->load($this->defaultRelations())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return new ClientResource($client->load($this->defaultRelations()));
    }

    public function update(UpdateClientRequest $request, Client $client, RecordAuditLog $auditLog): ClientResource
    {
        $before = $client->toArray();

        DB::transaction(function () use ($request, $client): void {
            $data = $request->validated();

            $client->update(Arr::except($data, ['responsible_member_ids', 'individual_profile', 'company_profile']));

            if (isset($data['responsible_member_ids'])) {
                $responsibleIds = array_values(array_unique($data['responsible_member_ids']));
                $this->syncResponsibles($client, $responsibleIds, $responsibleIds[0]);
                $client->update(['primary_responsible_member_id' => $responsibleIds[0]]);
            }

            if (isset($data['individual_profile']) && $client->type === Client::TYPE_INDIVIDUAL) {
                $client->individualProfile()->updateOrCreate(['client_id' => $client->id], $data['individual_profile']);
            }

            if (isset($data['company_profile']) && $client->type === Client::TYPE_COMPANY) {
                $client->companyProfile()->updateOrCreate(['client_id' => $client->id], $data['company_profile']);
            }
        });

        $auditLog->execute('client.updated', $request->user(), $client->organization, $client, [
            'before' => Arr::only($before, ['display_name', 'document_number', 'status', 'priority', 'risk_level']),
            'after' => $client->fresh()->only(['display_name', 'document_number', 'status', 'priority', 'risk_level']),
        ], $request);

        return new ClientResource($client->refresh()->load($this->defaultRelations()));
    }

    public function updateStatus(UpdateClientStatusRequest $request, Client $client, RecordAuditLog $auditLog): ClientResource
    {
        $data = $request->validated();
        $before = $client->only(['status', 'closure_reason', 'closed_at']);

        $client->update([
            'status' => $data['status'],
            'closure_reason' => $data['closure_reason'] ?? null,
            'closed_at' => $data['status'] === Client::STATUS_CLOSED ? now() : null,
        ]);

        $auditLog->execute('client.status_updated', $request->user(), $client->organization, $client, [
            'before' => $before,
            'after' => $client->only(['status', 'closure_reason', 'closed_at']),
        ], $request);

        return new ClientResource($client->load($this->defaultRelations()));
    }

    public function timeline(Client $client): JsonResponse
    {
        Gate::authorize('view', $client);

        $events = AuditLog::query()
            ->whereMorphedTo('auditable', $client)
            ->latest()
            ->paginate(request()->integer('per_page', 15));

        return response()->json([
            'data' => $events->map(fn (AuditLog $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'metadata' => $event->metadata,
                'created_at' => $event->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function addResponsible(StoreClientResponsibleRequest $request, Client $client, RecordAuditLog $auditLog): ClientResource
    {
        $data = $request->validated();
        $client->responsibles()->syncWithoutDetaching([
            $data['member_id'] => ['is_primary' => (bool) ($data['is_primary'] ?? false)],
        ]);

        if ($data['is_primary'] ?? false) {
            if ($client->primary_responsible_member_id && $client->primary_responsible_member_id !== $data['member_id']) {
                $client->responsibles()->updateExistingPivot($client->primary_responsible_member_id, ['is_primary' => false]);
            }

            $client->responsibles()->updateExistingPivot($data['member_id'], ['is_primary' => true]);
            $client->update(['primary_responsible_member_id' => $data['member_id']]);
        }

        $auditLog->execute('client.responsible_added', $request->user(), $client->organization, $client, ['member_id' => $data['member_id']], $request);

        return new ClientResource($client->load($this->defaultRelations()));
    }

    public function removeResponsible(Client $client, OrganizationMember $member, Request $request, RecordAuditLog $auditLog): ClientResource|JsonResponse
    {
        Gate::authorize('update', $client);

        abort_if($member->organization_id !== $client->organization_id, Response::HTTP_NOT_FOUND);

        if ($client->responsibles()->count() <= 1) {
            return response()->json([
                'message' => 'An active client must have at least one responsible member.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $client->responsibles()->detach($member->id);

        if ($client->primary_responsible_member_id === $member->id) {
            $newPrimary = $client->responsibles()->firstOrFail();
            $client->update(['primary_responsible_member_id' => $newPrimary->id]);
            $client->responsibles()->updateExistingPivot($newPrimary->id, ['is_primary' => true]);
        }

        $auditLog->execute('client.responsible_removed', $request->user(), $client->organization, $client, ['member_id' => $member->id], $request);

        return new ClientResource($client->load($this->defaultRelations()));
    }

    public function updateAccess(UpdateClientAccessRequest $request, Client $client, RecordAuditLog $auditLog): ClientResource
    {
        $data = $request->validated();

        $client->update(['access_policy' => $data['access_policy']]);
        $client->accessMembers()->sync($data['member_ids'] ?? []);

        $auditLog->execute('client.access_updated', $request->user(), $client->organization, $client, [
            'access_policy' => $client->access_policy,
            'member_ids' => $data['member_ids'] ?? [],
        ], $request);

        return new ClientResource($client->load($this->defaultRelations()));
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return [
            'primaryResponsible.user',
            'individualProfile',
            'companyProfile',
            'contacts',
            'tags',
            'responsibles.user',
        ];
    }

    /**
     * @param  array<int, int>  $responsibleIds
     */
    private function syncResponsibles(Client $client, array $responsibleIds, int $primaryResponsibleId): void
    {
        $client->responsibles()->sync(collect($responsibleIds)
            ->mapWithKeys(fn (int $id) => [$id => ['is_primary' => $id === $primaryResponsibleId]])
            ->all());
    }
}
