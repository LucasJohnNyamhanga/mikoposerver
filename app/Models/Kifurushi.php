<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kifurushi extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_kifurushis')
                    ->withPivot(['start_date', 'end_date'])
                    ->withTimestamps();
    }

    public function userKifurushis()
    {
        return $this->hasMany(UserKifurushi::class);
    }

    public function purchases()
    {
        return $this->hasMany(KifurushiPurchase::class);
    }
}
