<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KAN-31 : pseudo Facebook ou Messenger de la fiche de recensement (information seulement, pas un identifiant de connexion)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('social_pseudo', 255)->nullable()->after('picture_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('social_pseudo');
        });
    }
};
