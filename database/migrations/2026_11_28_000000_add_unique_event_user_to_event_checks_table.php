<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Un membre ne peut être présent qu'une fois par événement (KAN-12, US-11)
    public function up(): void
    {
        // Les doublons éventuels (entrées multiples d'avant la contrainte) sont réduits à la première présence
        $keptIds = DB::table('event_checks')
            ->selectRaw('MIN(id)')
            ->groupBy('event_id', 'user_id');

        DB::table('event_checks')->whereNotIn('id', $keptIds)->delete();

        Schema::table('event_checks', function (Blueprint $table) {
            $table->unique(['event_id', 'user_id',]);
        });
    }

    public function down(): void
    {
        Schema::table('event_checks', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id',]);
        });
    }
};
