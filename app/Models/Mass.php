<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property Carbon $date
 * @property string|null $liturgical_name
 * @property string|null $prayer_cycle
 * @property string|null $notes
 * @property bool $is_public
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Mass extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'liturgical_name', 'prayer_cycle', 'notes', 'is_public'];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'is_public' => 'boolean',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MassPart::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(MassRoleAssignment::class);
    }
}
