<?php

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\SacramentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property SacramentType $type
 * @property Carbon|null $date premier jour de la période connue (voir `date_precision`)
 * @property DatePrecision $date_precision
 * @property string|null $place
 * @property string|null $spouse_name
 * @property bool $spouse_is_member
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserSacrament extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'date', 'date_precision', 'place', 'spouse_name', 'spouse_is_member'];

    protected function casts(): array
    {
        return [
            'type'             => SacramentType::class,
            'date'             => 'date',
            'date_precision'   => DatePrecision::class,
            'spouse_is_member' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
