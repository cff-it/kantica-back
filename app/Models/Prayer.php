<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Prayer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['title', 'content'];

    public function massParts(): HasMany
    {
        return $this->hasMany(MassPart::class);
    }
}
