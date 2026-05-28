<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'receivable_id' => Receivable::factory(),
            'received_by_user_id' => User::factory(),
            'amount_cents' => 50000,
            'paid_at' => now()->toDateString(),
            'method' => 'transfer',
        ];
    }
}
