<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{

    protected $fillable = [
        'ofisi_id',
        'sender_id',
        'receiver_id',
        'message',
        'status',
    ];

    public function ofisi():BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    // The user who sent the message
    public function sender():BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // The user who received the message
    public function receiver():BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
