<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REPLACED = 'replaced';

    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_CLIENT = 'client';

    public const VISIBILITY_RESTRICTED = 'restricted';

    public const VISIBILITY_CONFIDENTIAL = 'confidential';

    public const SENSITIVITY_NORMAL = 'normal';

    public const SENSITIVITY_SENSITIVE = 'sensitive';

    public const SENSITIVITY_CONFIDENTIAL = 'confidential';

    protected $fillable = [
        'organization_id',
        'client_id',
        'document_category_id',
        'created_by_user_id',
        'title',
        'description',
        'status',
        'visibility',
        'sensitivity',
        'expires_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_RECEIVED,
        'visibility' => self::VISIBILITY_INTERNAL,
        'sensitivity' => self::SENSITIVITY_NORMAL,
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version_number');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(DocumentRequestItem::class);
    }
}
