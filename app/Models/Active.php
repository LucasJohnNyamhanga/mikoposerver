<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Active extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }

    
    protected $fillable = [
        'user_id',
        'ofisi_id',
    ];
}
