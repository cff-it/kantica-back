<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255); // Animation, 1ère lecture, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_roles');
    }
};
