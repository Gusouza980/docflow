<?php

namespace Database\Factories;

use App\Enums\DocumentSensitivity;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => Document::STATUS_RECEIVED,
            'visibility' => DocumentVisibility::Internal,
            'sensitivity' => DocumentSensitivity::Normal,
            'expires_at' => fake()->optional()->dateTimeBetween('+1 month', '+1 year'),
        ];
    }
}
