<?php

namespace App\Models;

use Database\Factories\OrganizationPaymentGatewayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPaymentGateway extends Model
{
    /** @use HasFactory<OrganizationPaymentGatewayFactory> */
    use HasFactory;

    public const PROVIDER_ASAAS = 'asaas';

    protected $fillable = [
        'organization_id',
        'provider',
        'api_key',
        'webhook_token',
        'is_enabled',
        'last_error',
        'connected_at',
    ];

    protected $hidden = [
        'api_key',
        'webhook_token',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'webhook_token' => 'encrypted',
            'is_enabled' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isReady(): bool
    {
        return $this->is_enabled && $this->api_key !== '' && $this->webhook_token !== '';
    }

    public function maskedApiKey(): string
    {
        $key = $this->api_key;

        if (strlen($key) < 4) {
            return '••••';
        }

        return '••••'.substr($key, -4);
    }
}
