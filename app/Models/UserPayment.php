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
 * @property int $event_payment_id
 * @property int $paid_amount
 * @property int $amount_due
 * @property string|null $comment
 * @property bool $is_cancelled
 * @property bool $is_done
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserPayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'event_payment_id',
        'paid_amount',
        'amount_due',
        'comment',
        'is_cancelled',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'is_cancelled' => 'boolean',
            'is_done'      => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventPayment(): BelongsTo
    {
        return $this->belongsTo(EventPayment::class);
    }
}
