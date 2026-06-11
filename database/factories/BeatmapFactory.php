<?php

/**
 * BeatmapFactory — generates fake beatmap records for testing.
 * Produces realistic osu! beatmap data using Faker.
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BeatmapFactory extends Factory
{
    /**
     * Define the model's default state.
     * All fields use realistic ranges matching real osu! beatmaps.
     */
    public function definition(): array
    {
        return [
            // beatmap_id must be unique — use a large range to avoid collisions
            'beatmap_id' => $this->faker->unique()->numberBetween(100000, 9999999),
            'title' => $this->faker->sentence(rand(2, 5)),
            'artist' => $this->faker->name(),
            // Version names mimic real osu! difficulty naming
            'difficulty_name' => $this->faker->randomElement([
                'Easy', 'Normal', 'Hard', 'Insane', 'Expert', 'Extra',
                'Marathon', 'Stream Practice', 'Jump Practice',
            ]),
            'stars' => round($this->faker->randomFloat(2, 1.0, 9.5), 2),
            'length_seconds' => $this->faker->numberBetween(60, 360),
            'bpm' => round($this->faker->randomFloat(1, 100, 280), 1),
            'circle_count' => $this->faker->numberBetween(50, 2000),
            'ar' => round($this->faker->randomFloat(1, 5.0, 10.0), 1),
            'od' => round($this->faker->randomFloat(1, 5.0, 10.0), 1),
        ];
    }
}
