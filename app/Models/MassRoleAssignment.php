<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $mass_id
 * @property int $mass_role_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MassRoleAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'mass_id', 'mass_role_id', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mass(): BelongsTo
    {
        return $this->belongsTo(Mass::class);
    }

    public function massRole(): BelongsTo
    {
        return $this->belongsTo(MassRole::class);
    }
}
