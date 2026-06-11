<?php

/**
 * DatabaseSeeder — populates the database with initial data for development and testing.
 *
 * Creates:
 * - 1 admin user (admin@osu.test / password)
 * - 1 demo osu! player (player@osu.test / password) with sample scores
 * - 20 sample beatmaps
 * - Sample scores with varying miss counts (needed for Almost FC feature)
 */

namespace Database\Seeders;

use App\Models\Beatmap;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Order matters: users and beatmaps before scores (foreign keys).
     */
    public function run(): void
    {
        // Create admin user — can access /admin/users and manage all content
        $admin = User::updateOrCreate(
            ['email' => 'admin@osu.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_blocked' => false,
            ]
        );

        // Create demo player with fake osu! credentials for testing dashboard
        $player = User::updateOrCreate(
            ['email' => 'player@osu.test'],
            [
                'name' => 'Demo Player',
                'password' => Hash::make('password'),
                'osu_username' => 'DemoPlayer',
                'is_admin' => false,
                'is_blocked' => false,
            ]
        );

        // Create 20 beatmaps to populate the beatmaps list
        $beatmaps = Beatmap::factory(20)->create();

        // Create scores for the demo player across all beatmaps
        // Mix of clean plays (0 misses) and near-FC plays (1-5 misses) for Almost FC demo
        foreach ($beatmaps as $index => $beatmap) {
            // First 5 beatmaps: almost-FC scores (miss_count 1-3) — these appear in Almost FC table
            if ($index < 5) {
                Score::create([
                    'user_id' => $player->id,
                    'beatmap_id' => $beatmap->id,
                    'score' => fake()->numberBetween(3000000, 8000000),
                    'accuracy' => fake()->randomFloat(2, 92.0, 99.0),
                    'rank' => 'A',
                    'passed' => true,
                    'miss_count' => fake()->numberBetween(1, 3),
                    'played_at' => fake()->dateTimeBetween('-3 months', 'now'),
                ]);
            }
            // Next 5 beatmaps: clean FC scores (0 misses)
            elseif ($index < 10) {
                Score::create([
                    'user_id' => $player->id,
                    'beatmap_id' => $beatmap->id,
                    'score' => fake()->numberBetween(5000000, 10000000),
                    'accuracy' => fake()->randomFloat(2, 98.0, 100.0),
                    'rank' => fake()->randomElement(['S', 'SS']),
                    'passed' => true,
                    'miss_count' => 0,
                    'played_at' => fake()->dateTimeBetween('-3 months', 'now'),
                ]);
            }
            // Remaining: mixed scores (some with more misses, some failed)
            else {
                Score::create([
                    'user_id' => $player->id,
                    'beatmap_id' => $beatmap->id,
                    'score' => fake()->numberBetween(1000000, 5000000),
                    'accuracy' => fake()->randomFloat(2, 75.0, 95.0),
                    'rank' => fake()->randomElement(['B', 'C', 'A']),
                    'passed' => fake()->boolean(80),
                    'miss_count' => fake()->numberBetween(2, 20),
                    'played_at' => fake()->dateTimeBetween('-6 months', 'now'),
                ]);
            }
        }

        $this->command->info('Seeded: admin@osu.test and player@osu.test with 20 beatmaps and scores.');
    }
}
