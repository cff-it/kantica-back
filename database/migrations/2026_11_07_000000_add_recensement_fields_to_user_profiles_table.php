<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// KAN-28 : voix déclarée (S, A, T, B) et email de contact de la fiche de recensement
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('voice', 1)->nullable()->after('user_id');
            $table->string('contact_mail', 255)->nullable()->after('phone');
            $table->unique('user_id');
        });

        // Les comptes existants ne doivent pas être bloqués par la vérification de l'email
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now(),]);
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropUnique(['user_id',]);
            $table->dropColumn(['voice', 'contact_mail',]);
        });
    }
};
