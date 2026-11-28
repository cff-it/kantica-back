<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KAN-38 : numéro de la section saisi par l'admin (Couplet 1, 2, 3). Il n'est pas calculé, car la numérotation est manuelle
// et facultative (un refrain unique n'a pas de numéro).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_sections', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('song_sections', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
