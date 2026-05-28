<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payable>
 */
class PayableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'description' => fake()->sentence(3),
            'vendor_name' => fake()->company(),
            'amount_cents' => 80000,
            'paid_amount_cents' => 0,
            'status' => Payable::STATUS_OPEN,
            'due_at' => now()->addDays(5)->toDateString(),
            'competence_date' => now()->startOfMonth()->toDateString(),
            'is_reimbursable' => false,
        ];
    }
}
