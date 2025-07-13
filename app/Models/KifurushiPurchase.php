<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KifurushiPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'kifurushi_id',
        'status',
        'start_date',
        'end_date',
        'is_active',
        'approved_at',
        'reference',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'approved_at'  => 'datetime',
        'is_active'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kifurushi(): BelongsTo
    {
        return $this->belongsTo(Kifurushi::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'reference', 'reference');
    }
}
