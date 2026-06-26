<?php

namespace App\Models;

use App\Enums\DocumentSensitivity;
use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'validity_days',
        'sensitivity',
        'is_active',
    ];

    protected $attributes = [
        'sensitivity' => DocumentSensitivity::Normal,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sensitivity' => DocumentSensitivity::class,
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
