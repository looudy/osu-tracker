<?php

/**
 * Creates the tags and beatmap_tag tables.
 * Tags are global (shared across all users).
 * Names are stored without # symbol (e.g., "dt" not "#dt").
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. "dt", "jumps", "streams"
            $table->timestamps();
        });

        // Pivot table for beatmap<->tag many-to-many relationship
        Schema::create('beatmap_tag', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
            $table->primary(['tag_id', 'beatmap_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beatmap_tag');
        Schema::dropIfExists('tags');
    }
};
