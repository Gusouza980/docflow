<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Receivable;
use App\Models\ReceivableReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivableReminder>
 */
class ReceivableReminderFactory extends Factory
{
    protected $model = ReceivableReminder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'receivable_id' => Receivable::factory(),
            'sent_by_user_id' => User::factory(),
            'channel' => ReceivableReminder::CHANNEL_EMAIL,
            'notes' => fake()->sentence(),
            'sent_at' => now(),
        ];
    }
}
