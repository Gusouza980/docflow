<?php

namespace App\Models;

use App\Enums\DocumentSensitivity;
use App\Enums\DocumentVisibility;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REPLACED = 'replaced';

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
        'visibility' => DocumentVisibility::Internal,
        'sensitivity' => DocumentSensitivity::Normal,
    ];

    protected function casts(): array
    {
        return [
            'visibility' => DocumentVisibility::class,
            'sensitivity' => DocumentSensitivity::class,
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
