<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KAN-31 : `user_sacraments` devient une ligne par sacrement (baptism, first_communion, confirmation, marriage)
return new class extends Migration
{
    public function up(): void
    {
        // Les lignes existantes sont des mariages : c'était le seul sacrement géré (conjoint, date, lieu)
        Schema::table('user_sacraments', function (Blueprint $table) {
            $table->string('type', 30)->default('marriage')->after('user_id');
        });

        // Le type est toujours précisé à l'écriture : la valeur par défaut ne sert qu'à migrer les lignes existantes
        Schema::table('user_sacraments', function (Blueprint $table) {
            $table->string('type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_sacraments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
