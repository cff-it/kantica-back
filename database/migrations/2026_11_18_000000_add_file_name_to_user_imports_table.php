<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KAN-62 : nom d'origine du fichier envoyé, affiché dans l'historique des imports. Nul pour les imports déjà enregistrés.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_imports', function (Blueprint $table) {
            $table->string('file_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_imports', function (Blueprint $table) {
            $table->dropColumn('file_name');
        });
    }
};
