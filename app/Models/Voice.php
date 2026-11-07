<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $vocal_group_id
 * @property string $name
 * @property int|null $vocal_range
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Voice extends Model
{
    use HasFactory;

    protected $fillable = ['vocal_group_id', 'name', 'vocal_range'];

    public function voiceGroup(): BelongsTo
    {
        return $this->belongsTo(VoiceGroup::class, 'vocal_group_id');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(UserProfile::class);
    }
}
