<?php

namespace App\Policies;

use App\Models\Song;
use App\Models\User;

class SongPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Song $song): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function update(User $user, Song $song): bool
    {
        return $user->hasRole(['admin', 'responsable']);
    }

    public function delete(User $user, Song $song): bool
    {
        return $user->hasRole('admin');
    }
}
