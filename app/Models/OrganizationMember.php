<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrganizationMember extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationMemberFactory> */
    use HasFactory;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_PROFESSIONAL = 'professional';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_READONLY = 'readonly';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'suspended_at',
    ];

    protected $attributes = [
        'role' => self::ROLE_READONLY,
        'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function responsibleClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_responsibles')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function accessibleClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_accesses')
            ->withTimestamps();
    }

    public function assignedTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to_member_id');
    }

    public function assignedDeadlines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Deadline::class, 'assigned_to_member_id');
    }
}
