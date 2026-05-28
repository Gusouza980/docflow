<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_COMPANY = 'company';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_NEGOTIATION = 'negotiation';

    public const STATUS_DELINQUENT = 'delinquent';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const ACCESS_ALL_MEMBERS = 'all_members';

    public const ACCESS_RESTRICTED = 'restricted';

    protected $fillable = [
        'organization_id',
        'primary_responsible_member_id',
        'type',
        'display_name',
        'document_number',
        'status',
        'priority',
        'risk_level',
        'potential_revenue_cents',
        'origin',
        'access_policy',
        'internal_notes',
        'entered_at',
        'closed_at',
        'closure_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'priority' => self::PRIORITY_NORMAL,
        'risk_level' => self::RISK_LOW,
        'access_policy' => self::ACCESS_ALL_MEMBERS,
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'date',
            'closed_at' => 'datetime',
            'potential_revenue_cents' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function primaryResponsible(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'primary_responsible_member_id');
    }

    public function individualProfile(): HasOne
    {
        return $this->hasOne(ClientIndividualProfile::class);
    }

    public function companyProfile(): HasOne
    {
        return $this->hasOne(ClientCompanyProfile::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClientTag::class, 'client_tag')->withTimestamps();
    }

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationMember::class, 'client_responsibles')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function accessMembers(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationMember::class, 'client_accesses')
            ->withTimestamps();
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

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClientMessage::class);
    }

    public function communicationConsents(): HasMany
    {
        return $this->hasMany(CommunicationConsent::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function portalAccesses(): HasMany
    {
        return $this->hasMany(ClientPortalAccess::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }
}
