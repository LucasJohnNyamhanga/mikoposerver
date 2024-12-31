<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOfisi extends Model
{
    public function position():BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the user for this KikundiUser.
     */
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Kikundi for this KikundiUser.
     */
    public function ofisi():BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }
}
