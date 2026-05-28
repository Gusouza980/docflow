<?php

namespace Database\Factories;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequestItem>
 */
class DocumentRequestItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'document_request_id' => DocumentRequest::factory(),
            'title' => fake()->sentence(3),
            'instructions' => fake()->sentence(),
            'due_at' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'status' => DocumentRequestItem::STATUS_REQUESTED,
        ];
    }
}
