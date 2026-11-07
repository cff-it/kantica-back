<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $song_id
 * @property string|null $lyrics
 * @property string|null $type
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SongSection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['song_id', 'lyrics', 'type', 'position'];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
