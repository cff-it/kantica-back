<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $color_one
 * @property string|null $color_two
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Libretto extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'color_one', 'color_two'];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_libretti')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
