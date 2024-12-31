<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Position extends Model
{
    public function users():BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_ofisis')
                    ->withTimestamps();
    }

    public function maofisi():HasManyThrough
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
