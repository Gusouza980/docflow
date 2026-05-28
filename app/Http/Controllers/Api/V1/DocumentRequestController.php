<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelDocumentRequestRecordRequest;
use App\Http\Requests\Api\V1\StoreDocumentRequestRecordRequest;
use App\Http\Resources\DocumentRequestResource;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DocumentRequestController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $membership = $organizationContext->membership();

        $documentRequests = DocumentRequest::query()
            ->with(['client', 'items.category', 'items.document.latestVersion'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->boolean('overdue'), fn ($query) => $query->whereDate('due_at', '<', now()->toDateString())->where('status', DocumentRequest::STATUS_PENDING))
            ->when(! $membership?->isAdmin() && ! $membership?->isManager(), function ($query) use ($membership): void {
                $query->whereHas('client', function ($query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return DocumentRequestResource::collection($documentRequests);
    }

    public function store(StoreDocumentRequestRecordRequest $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        $documentRequest = DB::transaction(function () use ($request, $organizationContext): DocumentRequest {
            $data = $request->validated();
            $client = Client::query()
                ->where('organization_id', $organizationContext->id())
                ->findOrFail($data['client_id']);

            Gate::authorize('update', $client);

            $documentRequest = DocumentRequest::create([
                ...Arr::except($data, ['items']),
                'organization_id' => $organizationContext->id(),
                'requested_by_user_id' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $documentRequest->items()->create([
                    ...$item,
                    'organization_id' => $organizationContext->id(),
                    'due_at' => $item['due_at'] ?? $data['due_at'] ?? null,
                ]);
            }

            return $documentRequest;
        });

        $auditLog->execute('document_request.created', $request->user(), $documentRequest->organization, $documentRequest, request: $request);

        return (new DocumentRequestResource($documentRequest->load($this->defaultRelations())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DocumentRequest $documentRequest): DocumentRequestResource
    {
        $this->authorizeDocumentRequest('view', $documentRequest);

        return new DocumentRequestResource($documentRequest->load($this->defaultRelations()));
    }

    public function cancel(CancelDocumentRequestRecordRequest $request, DocumentRequest $documentRequest, RecordAuditLog $auditLog): DocumentRequestResource
    {
        $this->authorizeDocumentRequest('update', $documentRequest);

        DB::transaction(function () use ($request, $documentRequest): void {
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->validated('cancellation_reason'),
            ]);

            $documentRequest->items()
                ->whereNot('status', DocumentRequestItem::STATUS_APPROVED)
                ->update(['status' => DocumentRequestItem::STATUS_CANCELLED]);
        });

        $auditLog->execute('document_request.cancelled', $request->user(), $documentRequest->organization, $documentRequest, request: $request);

        return new DocumentRequestResource($documentRequest->refresh()->load($this->defaultRelations()));
    }

    private function authorizeDocumentRequest(string $ability, DocumentRequest $documentRequest): void
    {
        abort_if($documentRequest->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize($ability, $documentRequest);
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return ['client', 'items.category', 'items.document.latestVersion'];
    }
}
