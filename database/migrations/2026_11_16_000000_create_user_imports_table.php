<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KAN-31 : suivi d'un import de membres lancé en tâche de fond (statut, progression, rapport final)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            // Fichier envoyé : disque privé, supprimé une fois l'import terminé
            $table->string('file_path')->nullable();
            // Étapes du traitement : un compte à créer par ligne prête, plus une photo à télécharger pour chaque ligne qui en a une
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->json('report')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_imports');
    }
};
