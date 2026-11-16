<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 255); // YouTube, Spotify, PDF, etc.
            $table->timestamps();
        });

        Schema::create('song_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained('link_types')->cascadeOnDelete();
            $table->text('url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_links');
        Schema::dropIfExists('link_types');
    }
};
