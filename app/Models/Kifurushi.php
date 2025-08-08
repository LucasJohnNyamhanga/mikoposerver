<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kifurushi extends Model
{

    public function purchases(): HasMany
    {
        return $this->hasMany(KifurushiPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function verifiedAccounts(): HasMany|Kifurushi
    {
        return $this->hasMany(VerifiedAccount::class);
    }


    protected $fillable = [
        'name',
        'description',
        'number_of_offices',
        'duration_in_days',
        'price',
        'is_active',
        'offer',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];
}
