<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    /** @use HasFactory<\Database\Factories\TicketMessageFactory> */
    use HasFactory;

    public const SENDER_INTERNAL = 'internal';

    public const SENDER_CLIENT = 'client';

    protected $fillable = [
        'organization_id',
        'ticket_id',
        'user_id',
        'client_portal_access_id',
        'sender_type',
        'body',
        'visible_to_client',
    ];

    protected $attributes = [
        'visible_to_client' => true,
    ];

    protected function casts(): array
    {
        return [
            'visible_to_client' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }
}
