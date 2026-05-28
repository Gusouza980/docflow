<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayablePayment>
 */
class PayablePaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'payable_id' => Payable::factory(),
            'paid_by_user_id' => User::factory(),
            'amount_cents' => 40000,
            'paid_at' => now()->toDateString(),
            'method' => 'transfer',
        ];
    }
}
