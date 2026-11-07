<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $mail
 * @property int|null $facebook_id
 * @property string|null $last_name
 * @property string|null $nick_name
 * @property string|null $token
 * @property string $password
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'mail',
        'last_name',
        'nick_name',
        'facebook_id',
        'token',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'status'            => UserStatus::class,
        ];
    }

    // Sanctum utilise `mail` comme champ d'email
    public function getEmailForPasswordReset(): string
    {
        return $this->mail;
    }

    // Les notifications mail utilisent `mail` au lieu de `email`
    public function routeNotificationForMail(): string
    {
        return $this->mail;
    }

    // La vérification d'email utilise `mail` au lieu de `email`
    public function getEmailForVerification(): string
    {
        return $this->mail;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function firstnames(): HasMany
    {
        return $this->hasMany(Firstname::class)->orderBy('order');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function sacrament(): HasOne
    {
        return $this->hasOne(UserSacrament::class);
    }

    public function engagement(): HasOne
    {
        return $this->hasOne(UserEngagement::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(UserEmergencyContact::class);
    }

    public function massRoleAssignments(): HasMany
    {
        return $this->hasMany(MassRoleAssignment::class);
    }

    public function eventSubscriptions(): HasMany
    {
        return $this->hasMany(EventSubscription::class);
    }

    public function eventChecks(): HasMany
    {
        return $this->hasMany(EventCheck::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(UserPayment::class);
    }
}
