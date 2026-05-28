<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDocumentCategoryRequest;
use App\Http\Requests\Web\UpdateDocumentCategoryRequest;
use App\Models\DocumentCategory;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentCategoryController extends Controller
{
    public function store(StoreDocumentCategoryRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', DocumentCategory::class);

        $category = DocumentCategory::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
        ]);

        $auditLog->execute('web.document_category.created', $request->user(), $membership->organization, $category, request: $request);

        return redirect()->route('documents.index')->with('status', 'Categoria cadastrada.');
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $category, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($category->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $category);

        $category->update($request->validated());
        $auditLog->execute('web.document_category.updated', $request->user(), $membership->organization, $category, request: $request);

        return redirect()->route('documents.index')->with('status', 'Categoria atualizada.');
    }

    public function destroy(DocumentCategory $category, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($category->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('delete', $category);

        if ($category->documents()->exists() || $category->requestItems()->exists()) {
            return redirect()->route('documents.index')->with('error', 'Categoria em uso não pode ser removida.');
        }

        $category->delete();
        $auditLog->execute('web.document_category.deleted', $request->user(), $membership->organization, $category, request: $request);

        return redirect()->route('documents.index')->with('status', 'Categoria removida.');
    }
}
