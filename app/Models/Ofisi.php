<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ofisi extends Model
{
    public function users():BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_ofisis')->latest()
            ->withPivot('position_id', 'status','isActive')
            ->withTimestamps()
            ->leftJoin('positions', 'user_ofisis.position_id', '=', 'positions.id')
            ->select('users.*', 'positions.name as position_name');
            // ->wherePivot('status', 'accepted'); // Filter where pivot status is denied
    }

    public function acceptedUsers()
    {
        return $this->belongsToMany(User::class, 'user_ofisis')
                    ->withPivot('status')
                    ->wherePivot('status', 'accepted');
    }

    public function userOfisis(): HasMany
    {
        return $this->hasMany(UserOfisi::class);
    }

    public function positions():HasManyThrough
    {
        return $this->hasManyThrough(Position::class, UserOfisi::class, 'ofisi_id', 'id', 'id', 'position_id');
    }

    public function positionsWithUsers():HasManyThrough
    {
        return $this->positions()->with(['users' => function ($query) {
            $query->wherePivot('ofisi_id', $this->id);
        }]);
    }

    public function messages():HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function customers():HasMany
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

    public function transactionsMwezi()
    {
        return $this->hasMany(Transaction::class)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->latest();
    }

    public function ainamikopo():HasMany
    {
        return $this->hasMany(Aina::class);
    }

    public function dhamana(): HasMany
    {
        return $this->hasMany(Dhamana::class);
    }

    public function verifiedAccounts(): HasMany
    {
        return $this->hasMany(VerifiedAccount::class);
    }

    public function smsBalances(): HasMany
    {
        return $this->hasMany(SmsBalance::class);
    }

    public function kifurushiPurchases(): HasMany
    {
        return $this->hasMany(KifurushiPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPaymentToday()
    {
        return $this->hasOne(Payment::class)
            ->whereDate('paid_at', today())
            ->where('status', 'completed')
            ->latestOfMany('paid_at');
    }

    public function activeKifurushi()
    {
        return $this->hasOne(KifurushiPurchase::class, 'ofisi_id', 'id')
            ->where('status', 'approved')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->latestOfMany('end_date');
    }





    public function verifiedAccount(): HasOne
    {
        return $this->hasOne(VerifiedAccount::class);
    }

    public function scopeWithTodayCompletedPayments($query)
    {
        return $query->whereHas('payments', function ($q) {
            $q->whereDate('created_at', today())
            ->where('status', 'completed');
        });
    }


    protected $fillable = [
        'jina',
        'mkoa',
        'wilaya',
        'kata',
        'kujiunga_wapya',
        'maelezo',
        'last_seen',
    ];

}
