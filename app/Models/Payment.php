<?php

namespace App\Models;

use App\Jobs\CheckPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'user_id',
        'kifurushi_id',
        'ofisi_id',
        'reference',
        'status',
        'transaction_id',
        'channel',
        'phone',
        'amount',
        'sms_amount',
        'retries_count',
        'next_check_at',
        'paid_at',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'next_check_at' => 'datetime',
        'paid_at'       => 'datetime',
        'amount'        => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kifurushi(): BelongsTo
    {
        return $this->belongsTo(Kifurushi::class);
    }

    public function kifurushiPurchase(): HasOne
    {
        return $this->hasOne(KifurushiPurchase::class, 'reference', 'reference');
    }

    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Model Events
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        // Automatically dispatch job for pending payments
        static::created(function (Payment $payment) {
            if ($payment->status === 'pending') {
                CheckPaymentStatus::dispatch($payment);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include payments ready for retry.
     */
    public function scopeReadyForCheck($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('next_check_at')
            ->where('next_check_at', '<=', now());
    }
}
