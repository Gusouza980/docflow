<?php

namespace App\Models;

use Database\Factories\ReceivableChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableCharge extends Model
{
    /** @use HasFactory<ReceivableChargeFactory> */
    use HasFactory;

    public const PROVIDER_ASAAS = 'asaas';

    public const TYPE_PIX = 'PIX';

    public const TYPE_BOLETO = 'BOLETO';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'organization_id',
        'receivable_id',
        'client_id',
        'provider',
        'provider_payment_id',
        'billing_type',
        'status',
        'invoice_url',
        'pix_payload',
        'pix_encoded_image',
        'bank_slip_url',
        'identification_field',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return array{
     *     id: int,
     *     billing_type: string,
     *     status: string,
     *     invoice_url: ?string,
     *     pix_payload: ?string,
     *     pix_encoded_image: ?string,
     *     bank_slip_url: ?string,
     *     identification_field: ?string
     * }
     */
    public function toPortalArray(): array
    {
        return [
            'id' => $this->id,
            'billing_type' => $this->billing_type,
            'status' => $this->status,
            'invoice_url' => $this->invoice_url,
            'pix_payload' => $this->pix_payload,
            'pix_encoded_image' => $this->pix_encoded_image,
            'bank_slip_url' => $this->bank_slip_url,
            'identification_field' => $this->identification_field,
        ];
    }
}
