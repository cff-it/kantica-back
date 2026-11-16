<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Abréviation du groupe de voix (S, A, T, B) ; `name` porte le nom complet (Soprano, Alto, ...)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_groups', function (Blueprint $table) {
            $table->string('abbreviation', 5)->nullable()->after('name');
        });

        // Les groupes existants stockaient l'abréviation dans `name` : app:deploy remet ensuite le nom complet
        DB::table('voice_groups')->update(['abbreviation' => DB::raw('name'),]);

        Schema::table('voice_groups', function (Blueprint $table) {
            $table->unique('abbreviation');
        });
    }

    public function down(): void
    {
        DB::table('voice_groups')->whereNotNull('abbreviation')->update(['name' => DB::raw('abbreviation'),]);

        Schema::table('voice_groups', function (Blueprint $table) {
            $table->dropUnique(['abbreviation',]);
            $table->dropColumn('abbreviation');
        });
    }
};
