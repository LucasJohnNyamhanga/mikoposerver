<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Active extends Model
{
    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ofisi():BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    
    protected $fillable = [
        'user_id',
        'ofisi_id',
    ];
}
