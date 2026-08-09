<?php

namespace App\Models;

use Database\Factories\InternalReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InternalReminder extends Model
{
    /** @use HasFactory<InternalReminderFactory> */
    use HasFactory;

    public const TYPE_TASK_ASSIGNED = 'task_assigned';

    public const TYPE_CALENDAR_EVENT = 'calendar_event';

    public const TYPE_MEETING_PORTAL_CONFIRMATION = 'meeting_portal_confirmation';

    public const TYPE_DOCUMENT_RECEIVED_PORTAL = 'document_received_portal';

    public const TYPE_TICKET_CLIENT_REPLY = 'ticket_client_reply';

    public const TYPE_TICKET_CLIENT_ATTACHMENT = 'ticket_client_attachment';

    public const TYPE_TICKET_LOW_RATING = 'ticket_low_rating';

    public const TYPE_PORTAL_MESSAGE_INBOUND = 'portal_message_inbound';

    public const TYPE_PORTAL_TICKET_OPENED = 'portal_ticket_opened';

    public const TYPE_PORTAL_PROFILE_UPDATE = 'portal_profile_update';

    public const TYPE_CLIENT_DELINQUENT = 'client_delinquent';

    public const TYPE_AUTOMATION = 'automation';

    protected $fillable = [
        'organization_id',
        'user_id',
        'remindable_type',
        'remindable_id',
        'type',
        'remind_at',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
