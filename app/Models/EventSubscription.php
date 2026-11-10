<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $event_id
 * @property Carbon $subscribed_at
 * @property bool $has_paid
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EventSubscription extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'event_id', 'subscribed_at', 'has_paid', 'paid_at'];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'has_paid'      => 'boolean',
            'paid_at'       => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
