<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $contact_name
 * @property string|null $contact_phone
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserEmergencyContact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'contact_name', 'contact_phone'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
