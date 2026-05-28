<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_TRIAGE = 'triage';

    public const STATUS_WAITING_CLIENT = 'waiting_client';

    public const STATUS_WAITING_THIRD_PARTY = 'waiting_third_party';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'organization_id',
        'client_id',
        'opened_by_user_id',
        'assigned_to_member_id',
        'source_message_id',
        'title',
        'description',
        'status',
        'priority',
        'visible_to_client',
        'due_at',
        'resolved_at',
        'closed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_NEW,
        'priority' => self::PRIORITY_NORMAL,
        'visible_to_client' => true,
    ];

    protected function casts(): array
    {
        return [
            'visible_to_client' => 'boolean',
            'due_at' => 'date',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'assigned_to_member_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(ClientMessage::class, 'source_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
