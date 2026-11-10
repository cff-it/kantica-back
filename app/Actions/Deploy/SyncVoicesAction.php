<?php

namespace App\Actions\Deploy;

use App\Enums\VoicePart;
use App\Repositories\VoiceRepository;
use Illuminate\Support\Facades\DB;

readonly class SyncVoicesAction
{
    // Nombre de pupitres par groupe de voix : S1, S2, A1, A2, ...
    private const int VOICES_PER_GROUP = 2;

    public function __construct(
        private VoiceRepository $voices,
    ) {}

    // Idempotent : crée les groupes et voix manquants, renomme les groupes si besoin ; retourne le nombre d'éléments créés ou mis à jour
    public function __invoke(): int
    {
        return DB::transaction(function (): int {
            $created = 0;

            foreach (VoicePart::cases() as $part) {
                $group = $this->voices->updateOrCreateGroup($part->value, $part->name);
                $created += (int) ($group->wasRecentlyCreated || $group->wasChanged());

                for ($i = 1; $i <= self::VOICES_PER_GROUP; $i++) {
                    $voice = $this->voices->firstOrCreateVoice($group, $part->value.$i);
                    $created += (int) $voice->wasRecentlyCreated;
                }
            }

            return $created;
        });
    }
}
