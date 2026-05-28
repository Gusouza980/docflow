<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    /** @use HasFactory<\Database\Factories\FinancialCategoryFactory> */
    use HasFactory;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_BOTH = 'both';

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'color',
        'is_active',
    ];

    protected $attributes = [
        'type' => self::TYPE_BOTH,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }
}
