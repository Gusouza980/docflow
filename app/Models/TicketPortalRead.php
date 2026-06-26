<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPortalRead extends Model
{
    protected $fillable = [
        'organization_id',
        'ticket_id',
        'client_portal_access_id',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
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

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }
}
