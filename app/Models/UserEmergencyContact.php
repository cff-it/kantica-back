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
 * @property string|null $relationship
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserEmergencyContact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'contact_name', 'contact_phone', 'relationship'];

    // La clé primaire reste `id` ; l'UUID est dans la colonne `uuid`
    public function uniqueIds(): array
    {
        return ['uuid',];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
