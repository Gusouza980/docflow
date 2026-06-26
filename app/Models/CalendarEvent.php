<?php

namespace App\Models;

use App\Enums\CalendarEventType;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DONE = 'done';

    public const PORTAL_CONFIRMATION_PENDING = 'pending';

    public const PORTAL_CONFIRMATION_CONFIRMED = 'confirmed';

    public const PORTAL_CONFIRMATION_DECLINED = 'declined';

    public const PORTAL_CONFIRMATION_RESCHEDULE_REQUESTED = 'reschedule_requested';

    protected $fillable = [
        'organization_id',
        'client_id',
        'created_by_user_id',
        'title',
        'description',
        'type',
        'status',
        'starts_at',
        'ends_at',
        'location',
        'notes',
        'notes_recorded_at',
        'requires_portal_confirmation',
        'portal_confirmation_status',
        'portal_confirmation_notes',
        'portal_confirmed_at',
        'portal_confirmed_by_access_id',
        'confirmation_deadline_at',
    ];

    protected $attributes = [
        'type' => CalendarEventType::Internal,
        'status' => self::STATUS_SCHEDULED,
    ];

    protected function casts(): array
    {
        return [
            'type' => CalendarEventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'notes_recorded_at' => 'datetime',
            'requires_portal_confirmation' => 'boolean',
            'portal_confirmed_at' => 'datetime',
            'confirmation_deadline_at' => 'datetime',
        ];
    }

    public function portalConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'portal_confirmed_by_access_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CalendarEventParticipant::class);
    }
}
