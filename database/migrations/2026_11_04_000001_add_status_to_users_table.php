<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// KAN-28 : statut de validation du compte et nom rendu facultatif à l'inscription
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable()->change();
            $table->string('status', 20)->default('pending')->index()->after('password');
        });

        // Les comptes existants ne doivent pas être bloqués par la validation
        DB::table('users')->update(['status' => 'active',]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status',]);
            $table->dropColumn('status');
            $table->string('last_name', 100)->nullable(false)->change();
        });
    }
};
