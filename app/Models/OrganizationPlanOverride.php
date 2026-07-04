<?php

namespace App\Models;

use Database\Factories\OrganizationPlanOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlanOverride extends Model
{
    /** @use HasFactory<OrganizationPlanOverrideFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'limits',
        'features',
        'reason',
        'expires_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
