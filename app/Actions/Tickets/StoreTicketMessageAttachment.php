<?php

namespace App\Actions\Tickets;

use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreTicketMessageAttachment
{
    public function execute(
        TicketMessage $message,
        UploadedFile $file,
        bool $visibleToClient = true,
    ): TicketMessageAttachment {
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $storedName = Str::uuid()->toString().($extension ? ".{$extension}" : '');
        $path = "organizations/{$message->organization_id}/tickets/{$message->ticket_id}/{$storedName}";

        Storage::disk('local')->put($path, $file->getContent());

        return $message->attachments()->create([
            'organization_id' => $message->organization_id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'visible_to_client' => $visibleToClient,
        ]);
    }
}
