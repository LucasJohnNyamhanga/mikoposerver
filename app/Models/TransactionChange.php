<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionChange extends Model
{
    /**
     * Get the ofisi this transaction change belongs to.
     */
    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    /**
     * Get the transaction this change belongs to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who created this transaction change.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this transaction change.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user this transaction change is associated with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'type',
        'category',
        'status',
        'method',
        'amount',
        'description',
        'admin_details',
        'action_type',
        'created_by',
        'approved_by',
        'user_id',
        'ofisi_id',
        'transaction_id',
        'reason',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        // Add other casts if needed, for example:
        // 'status' => 'string',
        // 'created_at' => 'datetime',
    ];
}
