<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RejectDocumentRequestItemRequest;
use App\Http\Requests\Web\UploadDocumentRequestItemFileRequest;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentVersion;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentRequestItemController extends Controller
{
    public function upload(UploadDocumentRequestItemFileRequest $request, DocumentRequestItem $item, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeItem($item, $membership->organization_id);

        if ($item->documentRequest->status === DocumentRequest::STATUS_CANCELLED || $item->status === DocumentRequestItem::STATUS_CANCELLED) {
            return redirect()->route('document-requests.show', $item->documentRequest)->with('error', 'Item cancelado não pode receber arquivo.');
        }

        DB::transaction(function () use ($request, $item): void {
            $data = $request->validated();
            $document = $item->document;

            if (! $document) {
                $document = Document::create([
                    'organization_id' => $item->organization_id,
                    'client_id' => $item->documentRequest->client_id,
                    'document_category_id' => $item->document_category_id,
                    'created_by_user_id' => $request->user()->id,
                    'title' => $data['title'] ?? $item->title,
                    'description' => $item->instructions,
                    'status' => Document::STATUS_RECEIVED,
                    'visibility' => Document::VISIBILITY_INTERNAL,
                ]);
            }

            $document->latestVersion?->update(['replaced_at' => now()]);
            $this->createVersion($document, $request->file('file'), $request->user()->id, $data['source'] ?? DocumentVersion::SOURCE_PORTAL);

            $item->update([
                'document_id' => $document->id,
                'status' => DocumentRequestItem::STATUS_RECEIVED,
                'received_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        });

        $auditLog->execute('web.document_request_item.uploaded', $request->user(), $item->organization, $item, request: $request);

        return redirect()->route('document-requests.show', $item->documentRequest)->with('status', 'Arquivo recebido.');
    }

    public function approve(DocumentRequestItem $item, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $request = request();
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeItem($item, $membership->organization_id);
        abort_unless($item->document_id, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Item sem documento recebido.');

        DB::transaction(function () use ($item): void {
            $item->update([
                'status' => DocumentRequestItem::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $item->document?->update([
                'status' => Document::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->completeParentRequestWhenReady($item->documentRequest);
        });

        $auditLog->execute('web.document_request_item.approved', $request->user(), $item->organization, $item, request: $request);

        return redirect()->route('document-requests.show', $item->documentRequest)->with('status', 'Item aprovado.');
    }

    public function reject(RejectDocumentRequestItemRequest $request, DocumentRequestItem $item, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeItem($item, $membership->organization_id);

        $data = $request->validated();

        DB::transaction(function () use ($item, $data): void {
            $item->update([
                'status' => DocumentRequestItem::STATUS_REJECTED,
                'approved_at' => null,
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);

            $item->document?->update([
                'status' => Document::STATUS_REJECTED,
                'approved_at' => null,
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);
        });

        $auditLog->execute('web.document_request_item.rejected', $request->user(), $item->organization, $item, request: $request);

        return redirect()->route('document-requests.show', $item->documentRequest)->with('status', 'Item recusado.');
    }

    private function authorizeItem(DocumentRequestItem $item, int $organizationId): void
    {
        abort_if($item->organization_id !== $organizationId, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $item->documentRequest);
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

    private function completeParentRequestWhenReady(DocumentRequest $documentRequest): void
    {
        $hasOpenItems = $documentRequest->items()
            ->whereNotIn('status', [DocumentRequestItem::STATUS_APPROVED, DocumentRequestItem::STATUS_CANCELLED])
            ->exists();

        if (! $hasOpenItems) {
            $documentRequest->update([
                'status' => DocumentRequest::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }
}
