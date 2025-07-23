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

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Relationships
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

    /**
     * Get the user's position in a given ofisi.
     */
    public function positionInOfisi(int $ofisiId): ?Position
    {
        $userOfisi = $this->maofisi()
                         ->where('ofisi_id', $ofisiId)
                         ->first();

        return $userOfisi && $userOfisi->pivot->position_id
            ? Position::find($userOfisi->pivot->position_id)
            : null;
    }

    // Mass assignable attributes
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

    // Hidden attributes for arrays
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Attribute casting
    protected $casts = [
        'is_manager' => 'boolean',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
