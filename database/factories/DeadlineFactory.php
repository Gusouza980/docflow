<?php

namespace Database\Factories;

use App\Models\Deadline;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deadline>
 */
class DeadlineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'assigned_to_member_id' => OrganizationMember::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'type' => 'general',
            'urgency' => Deadline::URGENCY_NORMAL,
            'status' => Deadline::STATUS_PENDING,
            'due_at' => now()->addWeek()->toDateString(),
            'requires_review' => false,
        ];
    }
}
