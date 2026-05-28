<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientPortalAccess extends Model
{
    /** @use HasFactory<\Database\Factories\ClientPortalAccessFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'organization_id',
        'client_id',
        'created_by_user_id',
        'name',
        'email',
        'token_hash',
        'status',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return array{plain: string, hash: string}
     */
    public static function makeToken(): array
    {
        $plain = Str::random(48);

        return ['plain' => $plain, 'hash' => hash('sha256', $plain)];
    }

    public static function findUsableByToken(string $token): ?self
    {
        return self::query()
            ->with(['organization', 'client'])
            ->where('token_hash', hash('sha256', $token))
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revoke(): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
