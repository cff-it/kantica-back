<?php

namespace App\Models;

use App\Enums\EventType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property EventType $type
 * @property Carbon $date_start
 * @property Carbon|null $date_end
 * @property string|null $location
 * @property bool $need_payment
 * @property int|null $payment_amount
 * @property Carbon|null $attendances_recorded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Event extends Model
{
    use HasFactory;

    // Même défaut que la colonne `type` : un événement créé sans type est déjà un « autre » en mémoire
    protected $attributes = ['type' => EventType::Other->value];

    // `attendances_recorded_at` n'est pas modifiable en masse : seule la saisie des présences le pose
    protected $fillable = ['name', 'type', 'date_start', 'date_end', 'location', 'need_payment', 'payment_amount'];

    protected function casts(): array
    {
        return [
            'type'                    => EventType::class,
            'date_start'              => 'datetime',
            'date_end'                => 'datetime',
            'need_payment'            => 'boolean',
            'attendances_recorded_at' => 'datetime',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(EventSubscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventPayment::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(EventCheck::class);
    }
}
