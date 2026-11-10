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
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LinkType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name'];

    public function songLinks(): HasMany
    {
        return $this->hasMany(SongLink::class, 'link_id');
    }
}
