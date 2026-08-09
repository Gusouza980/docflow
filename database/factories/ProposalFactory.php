<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'title' => 'Proposta '.fake()->words(2, true),
            'amount_cents' => fake()->numberBetween(10000, 200000),
            'status' => Proposal::STATUS_DRAFT,
            'notes' => fake()->sentence(),
        ];
    }
}
