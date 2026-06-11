<?php

/**
 * Creates the beatmaps table.
 * Beatmaps are osu! difficulty levels — they are auto-created when scores are imported.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beatmaps', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('beatmap_id')->unique(); // osu! API beatmap ID
            $table->string('title');
            $table->string('artist');
            $table->string('difficulty_name'); // "version" field from osu! API
            $table->float('stars'); // difficulty_rating from API
            $table->integer('length_seconds');
            $table->float('bpm')->nullable();
            $table->integer('circle_count')->nullable();
            $table->float('ar')->nullable(); // approach rate
            $table->float('od')->nullable(); // overall difficulty
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beatmaps');
    }
};
