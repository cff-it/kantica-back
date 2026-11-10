<?php

namespace App\Repositories;

use App\Models\Mass;
use App\Models\MassPart;
use Illuminate\Database\Eloquent\Collection;

class MassRepository
{
    public function all(): Collection
    {
        return Mass::orderByDesc('date')->get();
    }

    public function findById(int $id): Mass
    {
        return Mass::with(['parts.type', 'parts.massSongs.song', 'roleAssignments.user', 'roleAssignments.massRole'])
            ->findOrFail($id);
    }

    public function create(array $data): Mass
    {
        return Mass::create($data);
    }

    public function findPartById(int $partId): MassPart
    {
        return MassPart::findOrFail($partId);
    }
}
