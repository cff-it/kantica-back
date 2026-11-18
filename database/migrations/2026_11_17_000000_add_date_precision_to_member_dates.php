<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fiche de recensement : les dates des sacrements, de l'adhésion et de l'engagement peuvent n'être connues qu'au mois
// ou à l'année (« 05/1998 », « 2008 »). La date garde le premier jour de la période, la précision (day, month, year)
// dit ce qui est connu. Les lignes existantes sont des dates complètes : `day` par défaut.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sacraments', function (Blueprint $table) {
            $table->string('date_precision', 5)->default('day')->after('date');
        });

        Schema::table('user_engagements', function (Blueprint $table) {
            $table->string('adhesion_date_precision', 5)->default('day')->after('adhesion_date');
            $table->string('engagement_date_precision', 5)->default('day')->after('engagement_date');
        });
    }

    public function down(): void
    {
        Schema::table('user_sacraments', function (Blueprint $table) {
            $table->dropColumn('date_precision');
        });

        Schema::table('user_engagements', function (Blueprint $table) {
            $table->dropColumn(['adhesion_date_precision', 'engagement_date_precision',]);
        });
    }
};
