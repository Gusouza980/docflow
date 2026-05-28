<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientIndividualProfile extends Model
{
    /** @use HasFactory<\Database\Factories\ClientIndividualProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'full_name',
        'rg',
        'birth_date',
        'marital_status',
        'profession',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
