<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 */
class ReceivableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => Client::factory(),
            'created_by_user_id' => User::factory(),
            'description' => fake()->sentence(3),
            'amount_cents' => 150000,
            'paid_amount_cents' => 0,
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->addDays(10)->toDateString(),
            'competence_date' => now()->startOfMonth()->toDateString(),
        ];
    }
}
