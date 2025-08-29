<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $kifurushi_id
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property Carbon|null $approved_at
 * @property string $reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property User $user
 * @property Kifurushi $kifurushi
 * @property Ofisi $ofisi
 */
class KifurushiPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'kifurushi_id',
        'ofisi_id',
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

    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'reference', 'reference');
    }
}
