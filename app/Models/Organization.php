<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'document',
        'email',
        'phone',
        'timezone',
        'status',
        'settings',
        'payment_instructions',
        'platform_notes',
        'plan_id',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'timezone' => 'America/Sao_Paulo',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planOverrides(): HasMany
    {
        return $this->hasMany(OrganizationPlanOverride::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function subscriptionOrFail(): Subscription
    {
        return $this->subscription()->firstOrFail();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', OrganizationMember::STATUS_ACTIVE);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function documentCategories(): HasMany
    {
        return $this->hasMany(DocumentCategory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function onboardingTemplates(): HasMany
    {
        return $this->hasMany(OnboardingTemplate::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isOperational(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
