<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $abbreviation
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VoiceGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'abbreviation',];

    public function voices(): HasMany
    {
        return $this->hasMany(Voice::class, 'vocal_group_id');
    }
}
