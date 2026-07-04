<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ReceivableRecurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivableRecurrence>
 */
class ReceivableRecurrenceFactory extends Factory
{
    protected $model = ReceivableRecurrence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->startOfMonth();

        return [
            'organization_id' => Organization::factory(),
            'client_id' => Client::factory(),
            'created_by_user_id' => User::factory(),
            'description' => 'Mensalidade recorrente',
            'amount_cents' => 150000,
            'frequency' => ReceivableRecurrence::FREQUENCY_MONTHLY,
            'billing_day' => 10,
            'start_date' => $startDate->toDateString(),
            'next_due_date' => $startDate->copy()->day(10)->toDateString(),
            'is_active' => true,
        ];
    }
}
