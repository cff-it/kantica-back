<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mass_part_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prayer_id')->nullable()->constrained('prayers')->nullOnDelete();
            $table->text('antiphon')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_parts');
    }
};
