<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientCompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCompanyProfile>
 */
class ClientCompanyProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->company(),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->companySuffix(),
            'state_registration' => fake()->numerify('#########'),
            'municipal_registration' => fake()->numerify('#########'),
            'tax_regime' => 'simples_nacional',
            'main_cnae' => fake()->numerify('####-#/##'),
        ];
    }
}
