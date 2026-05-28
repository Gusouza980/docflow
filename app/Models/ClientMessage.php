<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMessage extends Model
{
    /** @use HasFactory<\Database\Factories\ClientMessageFactory> */
    use HasFactory;

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_SENT = 'sent';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'organization_id',
        'client_id',
        'message_template_id',
        'ticket_id',
        'sent_by_user_id',
        'client_portal_access_id',
        'channel',
        'direction',
        'status',
        'subject',
        'body',
        'external_name',
        'external_email',
        'sent_at',
        'received_at',
        'read_at',
    ];

    protected $attributes = [
        'channel' => MessageTemplate::CHANNEL_EMAIL,
        'status' => self::STATUS_REGISTERED,
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }
}
