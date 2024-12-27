<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOfisi extends Model
{
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the user for this KikundiUser.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Kikundi for this KikundiUser.
     */
    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }
}
