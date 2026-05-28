<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => Client::factory(),
            'requested_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'instructions' => fake()->sentence(),
            'due_at' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'status' => DocumentRequest::STATUS_PENDING,
        ];
    }
}
