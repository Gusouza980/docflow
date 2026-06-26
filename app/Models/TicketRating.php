<?php

namespace App\Models;

use Database\Factories\TicketRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRating extends Model
{
    /** @use HasFactory<TicketRatingFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'ticket_id',
        'client_portal_access_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
