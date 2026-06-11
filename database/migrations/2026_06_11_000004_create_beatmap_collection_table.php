<?php

/**
 * Creates the beatmap_collection pivot table.
 * Many-to-many: a beatmap can be in many collections; a collection can have many beatmaps.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beatmap_collection', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
            $table->primary(['collection_id', 'beatmap_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beatmap_collection');
    }
};
