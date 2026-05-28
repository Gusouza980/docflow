<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentUploadRequest;
use App\Http\Requests\Api\V1\StoreDocumentVersionRequest;
use App\Http\Requests\Api\V1\UpdateDocumentMetadataRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentVersionResource;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $membership = $organizationContext->membership();

        $documents = Document::query()
            ->with(['client', 'category', 'latestVersion'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('document_category_id'), fn ($query, int $categoryId) => $query->where('document_category_id', $categoryId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('visibility')->toString(), fn ($query, string $visibility) => $query->where('visibility', $visibility))
            ->when($request->boolean('expired'), fn ($query) => $query->whereDate('expires_at', '<', now()->toDateString()))
            ->when($request->boolean('expiring_soon'), fn ($query) => $query->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when(! $membership?->isAdmin() && ! $membership?->isManager(), function ($query) use ($membership): void {
                $query->where('visibility', '!=', Document::VISIBILITY_CONFIDENTIAL)
                    ->where(function ($query) use ($membership): void {
                        $query->whereNull('client_id')
                            ->orWhereHas('client', function ($query) use ($membership): void {
                                $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                                    ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                                    ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                            });
                    });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentUploadRequest $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        $document = DB::transaction(function () use ($request, $organizationContext): Document {
            $data = $request->validated();
            $this->authorizeClientWrite($data['client_id'] ?? null);

            $document = Document::create([
                ...Arr::except($data, ['file', 'source']),
                'organization_id' => $organizationContext->id(),
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->createVersion($document, $request->file('file'), $request->user()->id, $data['source'] ?? DocumentVersion::SOURCE_INTERNAL);

            return $document;
        });

        $auditLog->execute('document.created', $request->user(), $document->organization, $document, request: $request);

        return (new DocumentResource($document->load($this->defaultRelations())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Document $document): DocumentResource
    {
        $this->authorizeDocument('view', $document);

        return new DocumentResource($document->load($this->defaultRelations()));
    }

    public function update(UpdateDocumentMetadataRequest $request, Document $document, RecordAuditLog $auditLog): DocumentResource
    {
        $this->authorizeDocument('update', $document);
        $data = $request->validated();
        $this->authorizeClientWrite($data['client_id'] ?? $document->client_id);

        $document->update($data);
        $auditLog->execute('document.updated', $request->user(), $document->organization, $document, request: $request);

        return new DocumentResource($document->load($this->defaultRelations()));
    }

    public function storeVersion(StoreDocumentVersionRequest $request, Document $document, RecordAuditLog $auditLog): DocumentVersionResource
    {
        $this->authorizeDocument('update', $document);
        $data = $request->validated();

        $version = DB::transaction(function () use ($request, $document, $data): DocumentVersion {
            $document->latestVersion?->update(['replaced_at' => now()]);

            return $this->createVersion($document, $request->file('file'), $request->user()->id, $data['source'] ?? DocumentVersion::SOURCE_INTERNAL);
        });

        $auditLog->execute('document.version_created', $request->user(), $document->organization, $document, [
            'version_id' => $version->id,
            'version_number' => $version->version_number,
        ], $request);

        return new DocumentVersionResource($version->load('uploadedBy'));
    }

    public function view(Document $document, Request $request, RecordAuditLog $auditLog)
    {
        return $this->fileResponse($document, $request, $auditLog, 'view');
    }

    public function download(Document $document, Request $request, RecordAuditLog $auditLog)
    {
        return $this->fileResponse($document, $request, $auditLog, 'download');
    }

    public function clientDocuments(Client $client, Request $request): AnonymousResourceCollection
    {
        abort_if($client->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);

        $documents = $client->documents()
            ->with(['category', 'latestVersion'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return DocumentResource::collection($documents);
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

    private function fileResponse(Document $document, Request $request, RecordAuditLog $auditLog, string $mode)
    {
        $this->authorizeDocument('view', $document);

        $version = $document->latestVersion()->firstOrFail();
        abort_unless(Storage::disk($version->disk)->exists($version->path), Response::HTTP_NOT_FOUND);

        $auditLog->execute("document.{$mode}ed", $request->user(), $document->organization, $document, [
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

    private function authorizeDocument(string $ability, Document $document): void
    {
        abort_if($document->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize($ability, $document);
    }

    private function authorizeClientWrite(?int $clientId): void
    {
        if (! $clientId) {
            return;
        }

        $client = Client::query()
            ->where('organization_id', app(OrganizationContext::class)->id())
            ->findOrFail($clientId);

        Gate::authorize('update', $client);
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return ['client', 'category', 'latestVersion.uploadedBy', 'versions.uploadedBy'];
    }
}
