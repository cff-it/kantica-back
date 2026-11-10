<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property int $order
 * @property bool $default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Firstname extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'first_name', 'order', 'default'];

    protected function casts(): array
    {
        return ['default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
