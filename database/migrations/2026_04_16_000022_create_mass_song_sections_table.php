<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_song_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mass_song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_song_sections');
    }
};
