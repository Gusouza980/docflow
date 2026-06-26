<?php

namespace App\Http\Controllers\Web;

use App\Actions\Documents\ReceiveDocumentRequestItemUpload;
use App\Actions\Notifications\NotifyPortalClient;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RejectDocumentRequestItemRequest;
use App\Http\Requests\Web\UploadDocumentRequestItemFileRequest;
use App\Models\Document;
use App\Models\DocumentRequestItem;
use App\Models\DocumentVersion;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentRequestItemController extends Controller
{
    public function __construct(
        private ReceiveDocumentRequestItemUpload $receiveDocumentUpload,
        private NotifyPortalClient $notifyPortalClient,
    ) {}

    public function upload(UploadDocumentRequestItemFileRequest $request, DocumentRequestItem $item, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeItem($item, $membership->organization_id);

        $data = $request->validated();
        $this->receiveDocumentUpload->execute(
            $item,
            $request->file('file'),
            $request->user()->id,
            [
                'title' => $data['title'] ?? null,
                'source' => $data['source'] ?? DocumentVersion::SOURCE_INTERNAL,
            ],
        );

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

        $item->loadMissing('documentRequest.client');

        if ($item->documentRequest?->client) {
            $this->notifyPortalClient->execute(
                $item->documentRequest->client,
                'Documento recusado',
                'O item "'.$item->title.'" foi recusado. Motivo: '.$data['rejection_reason'],
                route('client-portal.documents.show', $item->documentRequest, absolute: true),
            );
        }

        return redirect()->route('document-requests.show', $item->documentRequest)->with('status', 'Item recusado.');
    }

    private function authorizeItem(DocumentRequestItem $item, int $organizationId): void
    {
        abort_if($item->organization_id !== $organizationId, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $item->documentRequest);
    }
}
