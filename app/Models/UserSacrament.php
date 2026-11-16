<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $date
 * @property string|null $place
 * @property string|null $spouse_name
 * @property bool $spouse_is_member
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserSacrament extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date', 'place', 'spouse_name', 'spouse_is_member'];

    protected function casts(): array
    {
        return [
            'date'             => 'date',
            'spouse_is_member' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
