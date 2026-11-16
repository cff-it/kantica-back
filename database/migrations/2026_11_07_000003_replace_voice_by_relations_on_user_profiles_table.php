<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// KAN-28 : la voix de la fiche devient une relation vers `voice_groups` (S, A, T, B) et `voices` (S1, S2, ...)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('voice_group_id')->nullable()->after('user_id')
                ->constrained('voice_groups')->restrictOnDelete();
            $table->foreignId('voice_id')->nullable()->after('voice_group_id')
                ->constrained('voices')->nullOnDelete();
        });

        // Reprise de l'ancienne colonne `voice` (abréviation du groupe) avant de rendre la colonne obligatoire
        DB::table('voice_groups')->get(['id', 'abbreviation',])->each(
            fn (object $group) => DB::table('user_profiles')
                ->where('voice', $group->abbreviation)
                ->update(['voice_group_id' => $group->id,]),
        );

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('voice');
            $table->foreignId('voice_group_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('voice', 1)->nullable()->after('user_id');
        });

        DB::table('voice_groups')->get(['id', 'abbreviation',])->each(
            fn (object $group) => DB::table('user_profiles')
                ->where('voice_group_id', $group->id)
                ->update(['voice' => $group->abbreviation,]),
        );

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voice_id');
            $table->dropConstrainedForeignId('voice_group_id');
        });
    }
};
