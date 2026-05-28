<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContact extends Model
{
    /** @use HasFactory<\Database\Factories\ClientContactFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_GENERAL = 'general';

    public const TYPE_FINANCIAL = 'financial';

    public const TYPE_OPERATIONAL = 'operational';

    protected $fillable = [
        'organization_id',
        'client_id',
        'name',
        'role',
        'email',
        'phone',
        'whatsapp',
        'type',
        'is_primary',
        'notes',
    ];

    protected $attributes = [
        'type' => self::TYPE_GENERAL,
        'is_primary' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
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
}
