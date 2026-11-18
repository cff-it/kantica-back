<?php

namespace App\Models;

use App\Enums\DatePrecision;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $adhesion_date premier jour de la période connue (voir `adhesion_date_precision`)
 * @property DatePrecision $adhesion_date_precision
 * @property Carbon|null $engagement_date premier jour de la période connue (voir `engagement_date_precision`)
 * @property DatePrecision $engagement_date_precision
 * @property string|null $engagement_place
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserEngagement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'adhesion_date',
        'adhesion_date_precision',
        'engagement_date',
        'engagement_date_precision',
        'engagement_place',
    ];

    protected function casts(): array
    {
        return [
            'adhesion_date'             => 'date',
            'adhesion_date_precision'   => DatePrecision::class,
            'engagement_date'           => 'datetime',
            'engagement_date_precision' => DatePrecision::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
