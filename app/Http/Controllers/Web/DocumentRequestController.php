<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CancelDocumentRequestRecordRequest;
use App\Http\Requests\Web\StoreDocumentRequestRecordRequest;
use App\Models\Client;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentRequestController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar solicitações.');
        }

        $documentRequests = $this->documentRequestQuery($request, $membership)
            ->with(['client', 'items.category', 'items.document.latestVersion'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('DocumentRequests/Index', [
            'documentRequests' => [
                'data' => $documentRequests->getCollection()->map(fn (DocumentRequest $documentRequest): array => $this->documentRequestSummary($documentRequest)),
                'meta' => [
                    'current_page' => $documentRequests->currentPage(),
                    'last_page' => $documentRequests->lastPage(),
                    'per_page' => $documentRequests->perPage(),
                    'total' => $documentRequests->total(),
                ],
            ],
            'filters' => [
                'client_id' => $request->string('client_id')->toString(),
                'status' => $request->string('status')->toString(),
                'overdue' => $request->boolean('overdue'),
            ],
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', DocumentRequest::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(StoreDocumentRequestRecordRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', DocumentRequest::class);

        $documentRequest = DB::transaction(function () use ($request, $membership): DocumentRequest {
            $data = $request->validated();
            $client = Client::query()
                ->whereBelongsTo($membership->organization)
                ->findOrFail($data['client_id']);

            Gate::authorize('update', $client);

            $documentRequest = DocumentRequest::create([
                ...Arr::except($data, ['items']),
                'organization_id' => $membership->organization_id,
                'requested_by_user_id' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $documentRequest->items()->create([
                    ...$item,
                    'organization_id' => $membership->organization_id,
                    'due_at' => $item['due_at'] ?? $data['due_at'] ?? null,
                ]);
            }

            return $documentRequest;
        });

        $auditLog->execute('web.document_request.created', $request->user(), $membership->organization, $documentRequest, request: $request);

        return redirect()->route('document-requests.show', $documentRequest)->with('status', 'Solicitação criada.');
    }

    public function show(DocumentRequest $documentRequest, Request $request, WebOrganizationContext $webOrganizationContext): Response
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDocumentRequest('view', $documentRequest, $membership);

        $documentRequest->load(['client', 'items.category', 'items.document.latestVersion']);

        return Inertia::render('DocumentRequests/Show', [
            'documentRequest' => $this->documentRequestDetail($documentRequest),
            'can' => [
                'update' => $request->user()->can('update', $documentRequest),
            ],
        ]);
    }

    public function cancel(CancelDocumentRequestRecordRequest $request, DocumentRequest $documentRequest, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDocumentRequest('update', $documentRequest, $membership);

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

        $auditLog->execute('web.document_request.cancelled', $request->user(), $documentRequest->organization, $documentRequest, request: $request);

        return redirect()->route('document-requests.show', $documentRequest)->with('status', 'Solicitação cancelada.');
    }

    private function documentRequestQuery(Request $request, OrganizationMember $membership): Builder
    {
        return DocumentRequest::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->whereDate('due_at', '<', now()->toDateString())->where('status', DocumentRequest::STATUS_PENDING))
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function (Builder $query) use ($membership): void {
                $query->whereHas('client', function (Builder $query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn (Builder $query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn (Builder $query) => $query->whereKey($membership->id));
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRequestSummary(DocumentRequest $documentRequest): array
    {
        return [
            'id' => $documentRequest->id,
            'title' => $documentRequest->title,
            'instructions' => $documentRequest->instructions,
            'status' => $documentRequest->status,
            'due_at' => $documentRequest->due_at?->toDateString(),
            'client' => ['id' => $documentRequest->client->id, 'name' => $documentRequest->client->display_name],
            'items_count' => $documentRequest->items->count(),
            'approved_items_count' => $documentRequest->items->where('status', DocumentRequestItem::STATUS_APPROVED)->count(),
            'received_items_count' => $documentRequest->items->where('status', DocumentRequestItem::STATUS_RECEIVED)->count(),
            'href' => route('document-requests.show', $documentRequest, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRequestDetail(DocumentRequest $documentRequest): array
    {
        return [
            ...$this->documentRequestSummary($documentRequest),
            'cancellation_reason' => $documentRequest->cancellation_reason,
            'items' => $documentRequest->items->map(fn (DocumentRequestItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'instructions' => $item->instructions,
                'status' => $item->status,
                'due_at' => $item->due_at?->toDateString(),
                'received_at' => $item->received_at?->toISOString(),
                'rejection_reason' => $item->rejection_reason,
                'category' => $item->category ? ['id' => $item->category->id, 'name' => $item->category->name] : null,
                'document' => $item->document ? [
                    'id' => $item->document->id,
                    'title' => $item->document->title,
                    'href' => route('documents.show', $item->document, absolute: false),
                    'download_href' => route('documents.download', $item->document, absolute: false),
                ] : null,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(OrganizationMember $membership): array
    {
        return [
            'clients' => Client::query()
                ->whereBelongsTo($membership->organization)
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])
                ->values(),
            'categories' => DocumentCategory::query()
                ->whereBelongsTo($membership->organization)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (DocumentCategory $category): array => ['value' => $category->id, 'label' => $category->name])
                ->values(),
        ];
    }

    private function authorizeDocumentRequest(string $ability, DocumentRequest $documentRequest, OrganizationMember $membership): void
    {
        abort_if($documentRequest->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize($ability, $documentRequest);
    }
}
