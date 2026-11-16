<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mass_song_id
 * @property int $song_section_id
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MassSongSection extends Model
{
    use HasFactory;

    protected $fillable = ['mass_song_id', 'song_section_id', 'position'];

    public function massSong(): BelongsTo
    {
        return $this->belongsTo(MassSong::class);
    }

    public function songSection(): BelongsTo
    {
        return $this->belongsTo(SongSection::class);
    }
}
