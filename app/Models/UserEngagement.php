<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $adhesion_date
 * @property Carbon|null $engagement_date
 * @property string|null $engagement_place
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserEngagement extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'adhesion_date', 'engagement_date', 'engagement_place'];

    protected function casts(): array
    {
        return [
            'adhesion_date'   => 'date',
            'engagement_date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
