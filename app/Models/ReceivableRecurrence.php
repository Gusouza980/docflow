<?php

namespace App\Models;

use Database\Factories\ReceivableRecurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivableRecurrence extends Model
{
    /** @use HasFactory<ReceivableRecurrenceFactory> */
    use HasFactory;

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_YEARLY = 'yearly';

    protected $fillable = [
        'organization_id',
        'client_id',
        'contract_id',
        'financial_category_id',
        'created_by_user_id',
        'description',
        'amount_cents',
        'frequency',
        'billing_day',
        'start_date',
        'end_date',
        'next_due_date',
        'is_active',
        'notes',
    ];

    protected $attributes = [
        'frequency' => self::FREQUENCY_MONTHLY,
        'billing_day' => 10,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'billing_day' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function isDueForGeneration(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->end_date && $this->next_due_date->isAfter($this->end_date)) {
            return false;
        }

        return $this->next_due_date->lte(now()->toDateString());
    }
}
