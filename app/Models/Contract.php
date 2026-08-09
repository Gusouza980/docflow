<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    public const BILLING_MONTH = 'month';

    public const BILLING_YEAR = 'year';

    public const BILLING_ONCE = 'once';

    protected $fillable = [
        'organization_id',
        'client_id',
        'code',
        'status',
        'amount_cents',
        'billing_interval',
        'starts_at',
        'ends_at',
        'auto_renew',
        'scope_included',
        'scope_excluded',
        'canceled_at',
        'cancel_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'billing_interval' => self::BILLING_MONTH,
        'auto_renew' => false,
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'auto_renew' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_ACTIVE => 'Ativo',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_CANCELED => 'Cancelado',
        ];
    }

    /**
     * @return list<string>
     */
    public static function billingIntervals(): array
    {
        return [
            self::BILLING_MONTH,
            self::BILLING_YEAR,
            self::BILLING_ONCE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function billingIntervalLabels(): array
    {
        return [
            self::BILLING_MONTH => 'Mensal',
            self::BILLING_YEAR => 'Anual',
            self::BILLING_ONCE => 'Único',
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

    public function clientServices(): BelongsToMany
    {
        return $this->belongsToMany(ClientService::class, 'contract_client_service')->withTimestamps();
    }

    /**
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public function scopeExpiringWithinDays(Builder $query, int $days = 30): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
