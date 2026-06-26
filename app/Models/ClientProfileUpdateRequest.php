<?php

namespace App\Models;

use Database\Factories\ClientProfileUpdateRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfileUpdateRequest extends Model
{
    /** @use HasFactory<ClientProfileUpdateRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id',
        'client_id',
        'client_portal_access_id',
        'status',
        'changes',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'reviewed_at' => 'datetime',
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

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
