<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientIndividualProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientIndividualProfile>
 */
class ClientIndividualProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'full_name' => fake()->name(),
            'rg' => fake()->numerify('#########'),
            'birth_date' => fake()->date(),
            'marital_status' => 'single',
            'profession' => fake()->jobTitle(),
        ];
    }
}
