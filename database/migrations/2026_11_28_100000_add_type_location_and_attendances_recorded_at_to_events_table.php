<?php

use App\Enums\EventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// KAN-12 : choix et création des événements. `type` (défaut « autre » pour les lignes existantes), `location` et
// `attendances_recorded_at` : c'est ce champ, et non le nombre de présences, qui fait passer un événement en « Déjà
// saisis » (une saisie à 0 présent reste une saisie).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('type', 20)->default(EventType::Other->value)->after('name');
            $table->string('location', 255)->nullable()->after('date_end');
            $table->dateTime('attendances_recorded_at')->nullable()->after('payment_amount');
            $table->index(['type', 'date_start',]);
        });

        // Les présences déjà enregistrées (saisie ou pointage) comptent comme une saisie : sans cela, un événement
        // avec des présences réapparaîtrait dans « À saisir ». Date de la saisie : la dernière présence écrite.
        $recordedAt = DB::table('event_checks')
            ->select('event_id', DB::raw('MAX(created_at) as recorded_at'))
            ->groupBy('event_id')
            ->get();

        foreach ($recordedAt as $row) {
            DB::table('events')
                ->where('id', $row->event_id)
                ->update(['attendances_recorded_at' => $row->recorded_at ?? now(),]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['type', 'date_start',]);
            $table->dropColumn(['type', 'location', 'attendances_recorded_at',]);
        });
    }
};
