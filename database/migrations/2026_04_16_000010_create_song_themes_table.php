<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_themes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 255);
            $table->timestamps();
        });

        Schema::create('song_song_theme', function (Blueprint $table) {
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_theme_id')->constrained()->cascadeOnDelete();
            $table->primary(['song_id', 'song_theme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_song_theme');
        Schema::dropIfExists('song_themes');
    }
};
