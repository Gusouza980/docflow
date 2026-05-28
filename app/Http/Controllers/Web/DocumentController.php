<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDocumentUploadRequest;
use App\Http\Requests\Web\StoreDocumentVersionRequest;
use App\Http\Requests\Web\UpdateDocumentMetadataRequest;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar documentos.');
        }

        $documents = $this->documentQuery($request, $membership)
            ->with(['client', 'category', 'latestVersion'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = DocumentCategory::query()
            ->whereBelongsTo($membership->organization)
            ->withCount(['documents', 'requestItems'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Documents/Index', [
            'documents' => [
                'data' => $documents->getCollection()->map(fn (Document $document): array => $this->documentSummary($document)),
                'meta' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                ],
            ],
            'categories' => $categories->map(fn (DocumentCategory $category): array => $this->categorySummary($category))->values(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'client_id' => $request->string('client_id')->toString(),
                'document_category_id' => $request->string('document_category_id')->toString(),
                'status' => $request->string('status')->toString(),
                'visibility' => $request->string('visibility')->toString(),
                'date_filter' => $request->string('date_filter')->toString(),
            ],
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', Document::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
                'manage_categories' => $request->user()->can('create', DocumentCategory::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(StoreDocumentUploadRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', Document::class);

        $document = DB::transaction(function () use ($request, $membership): Document {
            $data = $request->validated();
            $this->authorizeClientWrite($data['client_id'] ?? null, $membership);

            $document = Document::create([
                ...Arr::except($data, ['file', 'source']),
                'organization_id' => $membership->organization_id,
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->createVersion($document, $request->file('file'), $request->user()->id, $data['source'] ?? DocumentVersion::SOURCE_INTERNAL);

            return $document;
        });

        $auditLog->execute('web.document.created', $request->user(), $membership->organization, $document, request: $request);

        return redirect()->route('documents.show', $document)->with('status', 'Documento enviado.');
    }

    public function show(Document $document, Request $request, WebOrganizationContext $webOrganizationContext): Response
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDocument('view', $document, $membership);

        $document->load(['client', 'category', 'latestVersion.uploadedBy', 'versions.uploadedBy']);

        return Inertia::render('Documents/Show', [
            'document' => $this->documentDetail($document),
            'options' => $this->options($membership),
            'can' => [
                'update' => $request->user()->can('update', $document),
            ],
        ]);
    }

    public function update(UpdateDocumentMetadataRequest $request, Document $document, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDocument('update', $document, $membership);

        $data = $request->validated();
        $this->authorizeClientWrite($data['client_id'] ?? null, $membership);
        $document->update($data);

        $auditLog->execute('web.document.updated', $request->user(), $document->organization, $document, request: $request);

        return redirect()->route('documents.show', $document)->with('status', 'Documento atualizado.');
    }

    public function storeVersion(StoreDocumentVersionRequest $request, Document $document, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDocument('update', $document, $membership);

        $version = DB::transaction(function () use ($request, $document): DocumentVersion {
            $document->latestVersion?->update(['replaced_at' => now()]);

            return $this->createVersion($document, $request->file('file'), $request->user()->id, $request->validated('source') ?? DocumentVersion::SOURCE_INTERNAL);
        });

        $auditLog->execute('web.document.version_created', $request->user(), $document->organization, $document, [
            'version_id' => $version->id,
            'version_number' => $version->version_number,
        ], $request);

        return redirect()->route('documents.show', $document)->with('status', 'Nova versão adicionada.');
    }

    public function view(Document $document, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog)
    {
        return $this->fileResponse($document, $request, $webOrganizationContext, $auditLog, 'view');
    }

    public function download(Document $document, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog)
    {
        return $this->fileResponse($document, $request, $webOrganizationContext, $auditLog, 'download');
    }

    private function documentQuery(Request $request, OrganizationMember $membership): Builder
    {
        return Document::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('document_category_id'), fn (Builder $query, int $categoryId) => $query->where('document_category_id', $categoryId))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('visibility')->toString(), fn (Builder $query, string $visibility) => $query->where('visibility', $visibility))
            ->when($request->string('date_filter')->toString() === 'expired', fn (Builder $query) => $query->whereDate('expires_at', '<', now()->toDateString()))
            ->when($request->string('date_filter')->toString() === 'expiring_soon', fn (Builder $query) => $query->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function (Builder $query) use ($membership): void {
                $query->where('visibility', '!=', Document::VISIBILITY_CONFIDENTIAL)
                    ->where(function (Builder $query) use ($membership): void {
                        $query->whereNull('client_id')
                            ->orWhereHas('client', function (Builder $query) use ($membership): void {
                                $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                                    ->orWhereHas('responsibles', fn (Builder $query) => $query->whereKey($membership->id))
                                    ->orWhereHas('accessMembers', fn (Builder $query) => $query->whereKey($membership->id));
                            });
                    });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSummary(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'status' => $document->status,
            'visibility' => $document->visibility,
            'sensitivity' => $document->sensitivity,
            'expires_at' => $document->expires_at?->toDateString(),
            'client' => $document->client ? ['id' => $document->client->id, 'name' => $document->client->display_name] : null,
            'category' => $document->category ? ['id' => $document->category->id, 'name' => $document->category->name] : null,
            'latest_version' => $document->latestVersion ? [
                'id' => $document->latestVersion->id,
                'version_number' => $document->latestVersion->version_number,
                'original_name' => $document->latestVersion->original_name,
                'size' => $document->latestVersion->size,
            ] : null,
            'href' => route('documents.show', $document, absolute: false),
            'view_href' => route('documents.view', $document, absolute: false),
            'download_href' => route('documents.download', $document, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentDetail(Document $document): array
    {
        return [
            ...$this->documentSummary($document),
            'rejection_reason' => $document->rejection_reason,
            'versions' => $document->versions
                ->sortByDesc('version_number')
                ->map(fn (DocumentVersion $version): array => [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'source' => $version->source,
                    'original_name' => $version->original_name,
                    'mime_type' => $version->mime_type,
                    'size' => $version->size,
                    'uploaded_by' => $version->uploadedBy?->name,
                    'created_at' => $version->created_at?->toISOString(),
                    'replaced_at' => $version->replaced_at?->toISOString(),
                ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categorySummary(DocumentCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'validity_days' => $category->validity_days,
            'sensitivity' => $category->sensitivity,
            'is_active' => $category->is_active,
            'documents_count' => $category->documents_count ?? 0,
            'request_items_count' => $category->request_items_count ?? 0,
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

    private function createVersion(Document $document, UploadedFile $file, int $userId, string $source): DocumentVersion
    {
        $versionNumber = ((int) $document->versions()->max('version_number')) + 1;
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $storedName = Str::uuid()->toString().($extension ? ".{$extension}" : '');
        $path = "organizations/{$document->organization_id}/documents/{$document->id}/{$storedName}";

        Storage::disk('local')->put($path, $file->getContent());

        return $document->versions()->create([
            'organization_id' => $document->organization_id,
            'uploaded_by_user_id' => $userId,
            'version_number' => $versionNumber,
            'source' => $source,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'hash' => hash_file('sha256', $file->getRealPath()),
        ]);
    }

    private function fileResponse(Document $document, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog, string $mode)
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDocument('view', $document, $membership);

        $version = $document->latestVersion()->firstOrFail();
        abort_unless(Storage::disk($version->disk)->exists($version->path), HttpResponse::HTTP_NOT_FOUND);

        $auditLog->execute("web.document.{$mode}ed", $request->user(), $document->organization, $document, [
            'version_id' => $version->id,
        ], $request);

        if ($mode === 'download') {
            return Storage::disk($version->disk)->download($version->path, $version->original_name);
        }

        return Storage::disk($version->disk)->response($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline; filename="'.$version->original_name.'"',
        ]);
    }

    private function authorizeDocument(string $ability, Document $document, OrganizationMember $membership): void
    {
        abort_if($document->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize($ability, $document);
    }

    private function authorizeClientWrite(?int $clientId, OrganizationMember $membership): void
    {
        if (! $clientId) {
            return;
        }

        $client = Client::query()
            ->whereBelongsTo($membership->organization)
            ->findOrFail($clientId);

        Gate::authorize('update', $client);
    }
}
