<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarEventFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_INTERNAL = 'internal';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_DEADLINE = 'deadline';
    public const TYPE_HEARING = 'hearing';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DONE = 'done';

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
    ];

    protected $attributes = [
        'type' => self::TYPE_INTERNAL,
        'status' => self::STATUS_SCHEDULED,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'notes_recorded_at' => 'datetime',
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

    public function participants(): HasMany
    {
        return $this->hasMany(CalendarEventParticipant::class);
    }
}
