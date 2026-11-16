<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MassRole extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function assignments(): HasMany
    {
        return $this->hasMany(MassRoleAssignment::class);
    }
}
