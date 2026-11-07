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
 * @property int $libretto_id
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SongLibretto extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['song_id', 'libretto_id', 'position'];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function libretto(): BelongsTo
    {
        return $this->belongsTo(Libretto::class);
    }
}
