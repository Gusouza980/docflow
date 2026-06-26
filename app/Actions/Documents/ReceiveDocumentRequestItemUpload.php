<?php

namespace App\Actions\Documents;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiveDocumentRequestItemUpload
{
    /**
     * @param  array{title?: string|null, source?: string|null}  $data
     */
    public function execute(
        DocumentRequestItem $item,
        UploadedFile $file,
        ?int $uploadedByUserId = null,
        array $data = [],
    ): DocumentRequestItem {
        if ($item->documentRequest->status === DocumentRequest::STATUS_CANCELLED || $item->status === DocumentRequestItem::STATUS_CANCELLED) {
            abort(422, 'Item cancelado não pode receber arquivo.');
        }

        return DB::transaction(function () use ($item, $file, $uploadedByUserId, $data): DocumentRequestItem {
            $document = $item->document;

            if (! $document) {
                $document = Document::create([
                    'organization_id' => $item->organization_id,
                    'client_id' => $item->documentRequest->client_id,
                    'document_category_id' => $item->document_category_id,
                    'created_by_user_id' => $uploadedByUserId,
                    'title' => $data['title'] ?? $item->title,
                    'description' => $item->instructions,
                    'status' => Document::STATUS_RECEIVED,
                    'visibility' => DocumentVisibility::Internal,
                ]);
            }

            $document->latestVersion?->update(['replaced_at' => now()]);
            $this->createVersion(
                $document,
                $file,
                $uploadedByUserId,
                $data['source'] ?? DocumentVersion::SOURCE_PORTAL,
            );

            $item->update([
                'document_id' => $document->id,
                'status' => DocumentRequestItem::STATUS_RECEIVED,
                'received_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->completeParentRequestWhenReady($item->documentRequest);

            return $item->refresh()->load(['document.latestVersion', 'category']);
        });
    }

    private function createVersion(Document $document, UploadedFile $file, ?int $userId, string $source): DocumentVersion
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
