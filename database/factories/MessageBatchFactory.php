<?php

namespace Database\Factories;

use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageBatch>
 */
class MessageBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'message_template_id' => MessageTemplate::factory(),
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'filter' => MessageBatch::FILTER_OVERDUE,
            'skipped_count' => 0,
        ];
    }
}
