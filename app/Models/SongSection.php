<?php

namespace App\Models;

use App\Enums\SectionType;
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
 * @property SectionType|null $type
 * @property int|null $number
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SongSection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['song_id', 'lyrics', 'type', 'number', 'position'];

    protected function casts(): array
    {
        return [
            'type' => SectionType::class,
        ];
    }

    // L'UUID est dans la colonne `uuid` ; `id` reste l'entier auto-incrémenté (par défaut, HasUuids cible la clé primaire)
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
