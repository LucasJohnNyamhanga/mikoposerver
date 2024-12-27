<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{

    protected $fillable = [
        'ofisi_id',
        'sender_id',
        'receiver_id',
        'message',
        'status',
    ];

    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }

    // The user who sent the message
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // The user who received the message
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
