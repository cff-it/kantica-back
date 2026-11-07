<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $mass_part_id
 * @property int $song_id
 * @property string|null $specific_title
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MassSong extends Model
{
    use HasFactory;

    protected $fillable = ['mass_part_id', 'song_id', 'specific_title'];

    public function massPart(): BelongsTo
    {
        return $this->belongsTo(MassPart::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MassSongSection::class)->orderBy('position');
    }
}
