<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** --------------------
     *  Relationships
     *  -------------------- */

    public function kifurushis(): BelongsToMany
    {
        return $this->belongsToMany(Kifurushi::class, 'user_kifurushis')
            ->withPivot(['start_date', 'end_date'])
            ->withTimestamps();
    }

    public function maofisi(): BelongsToMany
    {
        return $this->belongsToMany(Ofisi::class, 'user_ofisis')
            ->withPivot('position_id', 'status', 'isActive')
            ->withTimestamps();
    }

    public function ofisis(): BelongsToMany
    {
        return $this->belongsToMany(Ofisi::class, 'user_ofisis')
            ->using(UserOfisi::class)
            ->withPivot(['position_id', 'status', 'isActive'])
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

    public function transactionChanges(): HasMany
    {
        return $this->hasMany(TransactionChange::class);
    }

    public function kifurushiPurchases(): HasMany
    {
        return $this->hasMany(KifurushiPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function verifiedAccounts(): User|HasMany
    {
        return $this->hasMany(VerifiedAccount::class);
    }

    public function smsBalances()
    {
        return $this->hasMany(SmsBalance::class);
    }

    // Optionally, get active SMS balance for a specific office
    public function activeSmsForOffice($ofisiId)
    {
        return $this->smsBalances()
            ->where('ofisi_id', $ofisiId)
            ->where('status', 'active')
            ->whereDate('expires_at', '>=', now())
            ->first();
    }


    /** --------------------
     *  Custom Methods
     *  -------------------- */

    public function getCheoKwaOfisi(int $ofisiId): ?string
    {
        $userOfisi = UserOfisi::where('user_id', $this->id)
            ->where('ofisi_id', $ofisiId)
            ->first();

        return $userOfisi?->position;
    }

    public function positionInOfisi(int $ofisiId): ?Position
    {
        $userOfisi = $this->maofisi()
            ->where('ofisi_id', $ofisiId)
            ->first();

        return $userOfisi && $userOfisi->pivot->position_id
            ? Position::find($userOfisi->pivot->position_id)
            : null;
    }

    /** --------------------
     *  Attributes
     *  -------------------- */

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

    protected $casts = [
        'is_manager' => 'boolean',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
