<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserKifurushi extends Model
{
    protected $table = 'user_kifurushis';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kifurushi()
    {
        return $this->belongsTo(Kifurushi::class);
    }
}
