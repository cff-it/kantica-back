<?php

namespace App\Repositories;

use App\Models\Voice;
use App\Models\VoiceGroup;
use Illuminate\Database\Eloquent\Collection;

class VoiceRepository
{
    // Groupes dans l'ordre de création (S, A, T, B), y compris ceux sans aucun membre
    public function allGroups(): Collection
    {
        return VoiceGroup::orderBy('id')->get();
    }

    // Le groupe est identifié par son abréviation ; le nom est mis à jour s'il a changé
    public function updateOrCreateGroup(string $abbreviation, string $name): VoiceGroup
    {
        return VoiceGroup::updateOrCreate(
            ['abbreviation' => $abbreviation,],
            ['name' => $name,],
        );
    }

    public function findGroupByAbbreviation(string $abbreviation): VoiceGroup
    {
        return VoiceGroup::where('abbreviation', $abbreviation)->firstOrFail();
    }

    public function findVoiceInGroup(VoiceGroup $group, string $name): Voice
    {
        return Voice::where('vocal_group_id', $group->id)->where('name', $name)->firstOrFail();
    }

    public function firstOrCreateVoice(VoiceGroup $group, string $name): Voice
    {
        return Voice::firstOrCreate([
            'vocal_group_id' => $group->id,
            'name'           => $name,
        ]);
    }
}
