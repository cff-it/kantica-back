<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KAN-31 : type de contact d'urgence de la fiche de recensement (Parents, Famille, Tuteur ou Tutrice, Ami(e) proche...)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_emergency_contacts', function (Blueprint $table) {
            $table->string('relationship', 100)->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('user_emergency_contacts', function (Blueprint $table) {
            $table->dropColumn('relationship');
        });
    }
};
