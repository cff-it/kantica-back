<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libretti', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 255);
            $table->string('color_one', 50)->nullable();
            $table->string('color_two', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('song_libretti', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('libretto_id')->constrained('libretti')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_libretti');
        Schema::dropIfExists('libretti');
    }
};
