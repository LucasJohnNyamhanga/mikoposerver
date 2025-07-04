<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KifurushiPurchase extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kifurushi()
    {
        return $this->belongsTo(Kifurushi::class);
    }
}
