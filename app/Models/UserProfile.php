<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $voice_group_id
 * @property int|null $voice_id
 * @property string|null $address
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property string|null $birth_place
 * @property string|null $region_registration_number
 * @property string|null $picture_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'voice_group_id',
        'voice_id',
        'address',
        'phone',
        'birth_date',
        'birth_place',
        'region_registration_number',
        'picture_url',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date',];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voiceGroup(): BelongsTo
    {
        return $this->belongsTo(VoiceGroup::class);
    }

    public function voice(): BelongsTo
    {
        return $this->belongsTo(Voice::class);
    }
}
