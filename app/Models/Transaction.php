<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    /**
     * Get the loan this transaction belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the ofisi this transaction belongs to.
     */
    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    /**
     * Get the user who created the transaction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the transaction.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user associated with the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer related to the transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the transaction changes related to this transaction.
     */
    public function transactionChanges(): HasMany
    {
        return $this->hasMany(TransactionChange::class);
    }

    /**
     * Scope a query to include related models for all transactions.
     */
    public function scopeWithAllRelations($query)
    {
        return $query->with([
            'user',
            'approver',
            'creator',
            'customer',
            'transactionChanges',
        ]);
    }

    /**
     * Get detailed transaction information by ID with related models and nested transactionChanges relations.
     */
    public static function getTransactionDetailsWithId(int $transactionId): self
    {
        return self::with([
            'user',
            'approver',
            'creator',
            'customer',
            'transactionChanges' => function ($query) {
                $query->with(['user', 'approver', 'creator'])
                    ->latest();
            }
        ])->findOrFail($transactionId);
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type',
        'category',
        'status',
        'method',
        'amount',
        'description',
        'created_by',
        'approved_by',
        'user_id',
        'ofisi_id',
        'loan_id',
        'customer_id',
        'edited',
        'is_loan_source',
    ];
}
