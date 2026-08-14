<?php

namespace App\Models;

use Database\Factories\PlatformAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlatformAuditLog extends Model
{
    /** @use HasFactory<PlatformAuditLogFactory> */
    use HasFactory;

    public const ACTION_ORGANIZATION_SUSPENDED = 'platform.organization.suspended';

    public const ACTION_ORGANIZATION_REACTIVATED = 'platform.organization.reactivated';

    public const ACTION_ORGANIZATION_NOTES_UPDATED = 'platform.organization.notes_updated';

    public const ACTION_ORGANIZATION_VIEWED = 'platform.organization.viewed';

    public const ACTION_TENANT_PROVISIONED = 'platform.tenant.provisioned';

    protected $fillable = [
        'platform_admin_user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'platform_admin_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
