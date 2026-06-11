<?php

/**
 * ScoreFactory — generates fake score records linked to users and beatmaps.
 * Uses realistic accuracy, rank, and miss count distributions.
 */

namespace Database\Factories;

use App\Models\Beatmap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     * Scores are always linked to a real user and beatmap.
     */
    public function definition(): array
    {
        // Pick a random rank weighted toward better ranks
        $rank = $this->faker->randomElement(['A', 'A', 'B', 'S', 'SS', 'C']);

        // Higher rank = higher accuracy
        $accuracy = match ($rank) {
            'SS'    => $this->faker->randomFloat(2, 99.0, 100.0),
            'S'     => $this->faker->randomFloat(2, 95.0, 99.0),
            'A'     => $this->faker->randomFloat(2, 90.0, 95.0),
            'B'     => $this->faker->randomFloat(2, 80.0, 90.0),
            'C'     => $this->faker->randomFloat(2, 70.0, 80.0),
            default => $this->faker->randomFloat(2, 50.0, 70.0),
        };

        // SS/S scores have 0 misses; others can have misses (Almost FC feature needs miss_count > 0)
        $missCount = match ($rank) {
            'SS', 'S' => 0,
            'A'       => $this->faker->numberBetween(1, 5),
            'B'       => $this->faker->numberBetween(3, 20),
            'C'       => $this->faker->numberBetween(10, 50),
            default   => $this->faker->numberBetween(20, 100),
        };

        return [
            'user_id' => User::factory(),
            'beatmap_id' => Beatmap::factory(),
            'score' => $this->faker->numberBetween(100000, 10000000),
            'accuracy' => $accuracy,
            'rank' => $rank,
            'passed' => true,
            'miss_count' => $missCount,
            'played_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
