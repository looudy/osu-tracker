<?php

/**
 * Creates the scores table.
 * Each row represents one play attempt by a user on a beatmap.
 * miss_count is CRITICAL for the "Almost FC" feature.
 * The unique constraint prevents importing duplicate plays.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
            $table->bigInteger('score');
            $table->float('accuracy'); // stored as 0-100 percentage
            $table->string('rank'); // SS, S, A, B, C, D
            $table->boolean('passed');
            $table->integer('miss_count'); // CRITICAL: used for Almost FC feature
            $table->dateTime('played_at'); // when the score was set in osu!
            $table->timestamps();
            // Prevent importing the exact same play twice
            $table->unique(['user_id', 'beatmap_id', 'played_at', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
