<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_PAUSED = 'paused';

    public const BILLING_PROVIDER_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'billing_provider',
        'provider_customer_id',
        'provider_subscription_id',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'past_due_at',
        'canceled_at',
        'cancel_at_period_end',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'past_due_at' => 'datetime',
            'canceled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }

    public function isAccessible(): bool
    {
        return match ($this->status) {
            self::STATUS_TRIALING => $this->trial_ends_at !== null && $this->trial_ends_at->isFuture(),
            self::STATUS_ACTIVE => ! ($this->cancel_at_period_end
                && $this->current_period_end !== null
                && $this->current_period_end->isPast()),
            self::STATUS_PAST_DUE => $this->onGracePeriod(),
            default => false,
        };
    }

    public function onGracePeriod(): bool
    {
        if ($this->status !== self::STATUS_PAST_DUE || $this->past_due_at === null) {
            return false;
        }

        $graceEndsAt = $this->past_due_at->copy()->addDays(
            (int) config('docflow.subscription.grace_days', 7)
        );

        return $graceEndsAt->isFuture();
    }

    public function daysUntilTrialEnds(): ?int
    {
        if ($this->trial_ends_at === null || ! $this->trial_ends_at->isFuture()) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false));
    }
}
