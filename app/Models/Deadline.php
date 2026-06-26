<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Database\Factories\DeadlineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deadline extends Model
{
    /** @use HasFactory<DeadlineFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEW_REQUESTED = 'review_requested';

    public const STATUS_REVIEW_APPROVED = 'review_approved';

    public const STATUS_ADJUSTMENT_REQUESTED = 'adjustment_requested';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'client_id',
        'assigned_to_member_id',
        'created_by_user_id',
        'title',
        'description',
        'type',
        'urgency',
        'status',
        'due_at',
        'requires_review',
        'review_requested_at',
        'review_approved_at',
        'review_notes',
        'completed_at',
        'completion_notes',
    ];

    protected $attributes = [
        'type' => 'general',
        'urgency' => TaskPriority::Normal,
        'status' => self::STATUS_PENDING,
        'requires_review' => false,
    ];

    protected function casts(): array
    {
        return [
            'urgency' => TaskPriority::class,
            'due_at' => 'date',
            'requires_review' => 'boolean',
            'review_requested_at' => 'datetime',
            'review_approved_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'assigned_to_member_id');
    }
}
