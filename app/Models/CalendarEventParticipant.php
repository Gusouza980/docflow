<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarEventParticipantFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'calendar_event_id',
        'organization_member_id',
        'external_name',
        'external_email',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'organization_member_id');
    }
}
