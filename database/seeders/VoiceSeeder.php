<?php

namespace Database\Seeders;

use App\Actions\Deploy\SyncVoicesAction;
use Illuminate\Database\Seeder;

class VoiceSeeder extends Seeder
{
    public function run(SyncVoicesAction $syncVoices): void
    {
        $syncVoices();
    }
}
