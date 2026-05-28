<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentCategoryFactory> */
    use HasFactory, SoftDeletes;

    public const SENSITIVITY_NORMAL = 'normal';

    public const SENSITIVITY_SENSITIVE = 'sensitive';

    public const SENSITIVITY_CONFIDENTIAL = 'confidential';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'validity_days',
        'sensitivity',
        'is_active',
    ];

    protected $attributes = [
        'sensitivity' => self::SENSITIVITY_NORMAL,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(DocumentRequestItem::class);
    }
}
