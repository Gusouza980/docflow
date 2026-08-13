<?php

namespace App\Models;

use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    /** @use HasFactory<ServiceTypeFactory> */
    use HasFactory;

    public const BILLING_MONTH = 'month';

    public const BILLING_YEAR = 'year';

    public const BILLING_ONCE = 'once';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'is_active',
        'default_amount_cents',
        'default_billing_interval',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_amount_cents' => 'integer',
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

    public function clientServices(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }
}
