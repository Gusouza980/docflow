<?php

namespace App\Models;

use Database\Factories\ClientPortalAccessFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientPortalAccess extends Model implements AuthenticatableContract
{
    /** @use HasFactory<ClientPortalAccessFactory> */
    use Authenticatable, HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'organization_id',
        'client_id',
        'created_by_user_id',
        'name',
        'email',
        'password',
        'password_set_at',
        'onboarding_completed_at',
        'token_hash',
        'status',
        'expires_at',
        'last_used_at',
        'messages_last_read_at',
        'revoked_at',
    ];

    protected $hidden = [
        'password',
        'token_hash',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'expires_at' => 'datetime',
            'password_set_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_used_at' => 'datetime',
            'messages_last_read_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function profileUpdateRequests(): HasMany
    {
        return $this->hasMany(ClientProfileUpdateRequest::class);
    }

    public function ticketReads(): HasMany
    {
        return $this->hasMany(TicketPortalRead::class);
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

    public static function findForPortalLogin(string $email, string $password): ?self
    {
        $accesses = self::query()
            ->with(['organization', 'client'])
            ->where('email', $email)
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('password')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($accesses as $access) {
            if (Hash::check($password, $access->password)) {
                return $access;
            }
        }

        return null;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->password_set_at !== null;
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

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function revoke(): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }

    public function completeOnboarding(string $password): void
    {
        $this->update([
            'password' => $password,
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
    }
}
