<?php

/**
 * DatabaseSeeder — populates the database with initial data for development and testing.
 *
 * Creates:
 * - xan_ly (osu! ID 11885405) as permanent admin
 * - 3 top-100 osu! players as regular users (mrekk, Chicony, TTv_UFO)
 * - 20 sample beatmaps with scores for demo players
 */

namespace Database\Seeders;

use App\Models\Beatmap;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // xan_ly — permanent admin, logs in via osu! OAuth
        User::updateOrCreate(
            ['osu_id' => '11885405'],
            [
                'name'         => 'xan_ly',
                'osu_username' => 'xan_ly',
                'email'        => '11885405@osu.placeholder',
                'is_admin'     => true,
                'is_blocked'   => false,
            ]
        );

        // Top-100 osu! players as regular users (for admin panel demo)
        $topPlayers = [
            ['osu_id' => '7562902',  'osu_username' => 'mrekk'],
            ['osu_id' => '5199332',  'osu_username' => 'Chicony'],
            ['osu_id' => '14676719', 'osu_username' => 'TTv_UFO'],
        ];

        $players = [];
        foreach ($topPlayers as $data) {
            $players[] = User::updateOrCreate(
                ['osu_id' => $data['osu_id']],
                [
                    'name'         => $data['osu_username'],
                    'osu_username' => $data['osu_username'],
                    'email'        => $data['osu_id'] . '@osu.placeholder',
                    'is_admin'     => false,
                    'is_blocked'   => false,
                ]
            );
        }

        // 20 sample beatmaps
        $beatmaps = Beatmap::factory(20)->create();

        // Sample scores for each demo player
        foreach ($players as $player) {
            foreach ($beatmaps as $index => $beatmap) {
                if ($index < 5) {
                    Score::create([
                        'user_id'    => $player->id,
                        'beatmap_id' => $beatmap->id,
                        'score'      => fake()->numberBetween(3000000, 8000000),
                        'accuracy'   => fake()->randomFloat(2, 92.0, 99.0),
                        'rank'       => 'A',
                        'passed'     => true,
                        'miss_count' => fake()->numberBetween(1, 3),
                        'played_at'  => fake()->dateTimeBetween('-3 months', 'now'),
                    ]);
                } elseif ($index < 10) {
                    Score::create([
                        'user_id'    => $player->id,
                        'beatmap_id' => $beatmap->id,
                        'score'      => fake()->numberBetween(5000000, 10000000),
                        'accuracy'   => fake()->randomFloat(2, 98.0, 100.0),
                        'rank'       => fake()->randomElement(['S', 'SS']),
                        'passed'     => true,
                        'miss_count' => 0,
                        'played_at'  => fake()->dateTimeBetween('-3 months', 'now'),
                    ]);
                } else {
                    Score::create([
                        'user_id'    => $player->id,
                        'beatmap_id' => $beatmap->id,
                        'score'      => fake()->numberBetween(1000000, 5000000),
                        'accuracy'   => fake()->randomFloat(2, 75.0, 95.0),
                        'rank'       => fake()->randomElement(['B', 'C', 'A']),
                        'passed'     => fake()->boolean(80),
                        'miss_count' => fake()->numberBetween(2, 20),
                        'played_at'  => fake()->dateTimeBetween('-6 months', 'now'),
                    ]);
                }
            }
        }

        $this->command->info('Seeded: xan_ly (admin) + mrekk, Chicony, TTv_UFO + 20 beatmaps.');
    }
}
