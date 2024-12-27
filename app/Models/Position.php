<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_ofisis')
                    ->withTimestamps();
    }

    public function maofisi()
    {
        return $this->hasManyThrough(
            Ofisi::class,
            UserOfisi::class,
            'position_id', 
            'id',          
            'id', 
            'ofisi_id'   
        );
    }
}
