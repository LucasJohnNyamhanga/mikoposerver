<?php

namespace App\Models;

use App\Jobs\CheckPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'kifurushi_id',
        'user_id',
        'reference',
        'status',
        'transaction_id',
        'channel',
        'phone',
        'amount',
        'retries_count',
        'next_check_at',
        'paid_at',
    ];

    protected $casts = [
        'next_check_at' => 'datetime',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kifurushi(): BelongsTo
    {
        return $this->belongsTo(Kifurushi::class);
    }

    public function kifurushiPurchase()
    {
        return $this->hasOne(KifurushiPurchase::class, 'reference', 'reference');
    }


    protected static function booted()
    {
        static::created(function ($payment) {
            if ($payment->status === 'pending') {
                CheckPaymentStatus::dispatch($payment);
            }
        });
    }
}
