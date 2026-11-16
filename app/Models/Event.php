<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $date_start
 * @property Carbon|null $date_end
 * @property bool $need_payment
 * @property int|null $payment_amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'date_start', 'date_end', 'need_payment', 'payment_amount'];

    protected function casts(): array
    {
        return [
            'date_start'    => 'datetime',
            'date_end'      => 'datetime',
            'need_payment'  => 'boolean',
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
