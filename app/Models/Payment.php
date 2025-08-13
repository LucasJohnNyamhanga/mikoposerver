<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $kifurushi_id
 * @property int $ofisi_id
 * @property string $reference
 * @property string $status
 * @property string|null $transaction_id
 * @property string|null $channel
 * @property string|null $phone
 * @property float $amount
 * @property int $retries_count
 * @property Carbon|null $next_check_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $sms_amount
 */

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ofisi_id',
        'kifurushi_id',
        'amount',
        'status',
        'reference',
        'transaction_id',
        'channel',
        'phone',
        'retries_count',
        'next_check_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'next_check_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** --------------------
     *  Relationships
     *  -------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    public function kifurushi(): BelongsTo
    {
        return $this->belongsTo(Kifurushi::class);
    }

    /** --------------------
     *  Scopes
     *  -------------------- */

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
