<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentCategoryRequest;
use App\Http\Requests\Api\V1\UpdateDocumentCategoryRequest;
use App\Http\Resources\DocumentCategoryResource;
use App\Models\DocumentCategory;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DocumentCategoryController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $categories = DocumentCategory::query()
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return DocumentCategoryResource::collection($categories);
    }

    public function store(StoreDocumentCategoryRequest $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        $category = DocumentCategory::create([
            ...$request->validated(),
            'organization_id' => $organizationContext->id(),
        ]);

        $auditLog->execute('document_category.created', $request->user(), $organizationContext->organization(), $category, request: $request);

        return (new DocumentCategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $category, RecordAuditLog $auditLog): DocumentCategoryResource
    {
        $this->ensureActiveOrganization($category);

        $category->update($request->validated());
        $auditLog->execute('document_category.updated', $request->user(), $category->organization, $category, request: $request);

        return new DocumentCategoryResource($category);
    }

    public function destroy(DocumentCategory $category, Request $request, RecordAuditLog $auditLog): JsonResponse
    {
        $this->ensureActiveOrganization($category);
        Gate::authorize('delete', $category);

        if ($category->documents()->exists() || $category->requestItems()->exists()) {
            return response()->json([
                'message' => 'Document category is in use and cannot be deleted.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->delete();
        $auditLog->execute('document_category.deleted', $request->user(), $category->organization, $category, request: $request);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    private function ensureActiveOrganization(DocumentCategory $category): void
    {
        abort_if($category->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
    }
}
