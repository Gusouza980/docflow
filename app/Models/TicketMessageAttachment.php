<?php

namespace App\Models;

use Database\Factories\TicketMessageAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessageAttachment extends Model
{
    /** @use HasFactory<TicketMessageAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'ticket_message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'visible_to_client',
    ];

    protected $attributes = [
        'disk' => 'local',
        'visible_to_client' => true,
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visible_to_client' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }
}
