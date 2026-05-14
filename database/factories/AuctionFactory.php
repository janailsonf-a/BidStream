<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    public function definition(): array
    {
        $startingPrice = fake()->numberBetween(100, 1000);

        return [
            'created_by' => User::factory(),
            'winner_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starting_price' => $startingPrice,
            'current_price' => $startingPrice,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'draft',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);
    }
}
