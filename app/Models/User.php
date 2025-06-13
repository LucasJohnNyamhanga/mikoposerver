<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $mobile
 * @property string $jina_kamili
 * @property string|null $jina_mdhamini
 * @property string|null $simu_mdhamini
 * @property string|null $picha
 * @property string $username
 * @property string $anakoishi
 * @property bool $is_manager
 * @property bool $is_admin
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Ofisi[] $maofisi
 * @property-read \App\Models\Active|null $activeOfisi
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Position[] $positions
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message[] $sentMessages
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message[] $receivedMessages
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Customer[] $customer
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Loan[] $loans
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Transaction[] $transactions
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function maofisi(): BelongsToMany
    {
        return $this->belongsToMany(Ofisi::class, 'user_ofisis')
                    ->withPivot('position_id', 'status')
                    ->withTimestamps();
    }

    public function ofisis()
    {
        return $this->belongsToMany(Ofisi::class, 'user_ofisis')
            ->withPivot('status')
            ->wherePivot('status', 'accepted')
            ->withTimestamps();
    }

    public function positions(): HasManyThrough
    {
        return $this->hasManyThrough(Position::class, UserOfisi::class, 'user_id', 'id', 'id', 'position_id');
    }

    public function activeOfisi(): HasOne
    {
        return $this->hasOne(Active::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function customer(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected $fillable = [
        'mobile',
        'jina_kamili',
        'jina_mdhamini',
        'simu_mdhamini',
        'picha',
        'is_manager',
        'is_admin',
        'username',
        'is_active',
        'password',
        'anakoishi',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            // add casting if needed
        ];
    }
}
