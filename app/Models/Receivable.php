<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receivable extends Model
{
    /** @use HasFactory<\Database\Factories\ReceivableFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'client_id',
        'financial_category_id',
        'created_by_user_id',
        'description',
        'amount_cents',
        'paid_amount_cents',
        'status',
        'due_at',
        'competence_date',
        'paid_at',
        'notes',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'paid_amount_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_amount_cents' => 'integer',
            'due_at' => 'date',
            'competence_date' => 'date',
            'paid_at' => 'date',
            'cancelled_at' => 'datetime',
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
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balanceCents(): int
    {
        return max(0, $this->amount_cents - $this->paid_amount_cents);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_PARTIAL], true)
            && $this->due_at?->isPast();
    }
}
