<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerRunLog extends Model
{
    protected $fillable = [
        'command',
        'ran_at',
        'duration_ms',
        'result',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
