<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }

    // Relationship: Creator (User who created the transaction)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship: Approver (User who approved the transaction)
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Relationship: User (User who the transaction belongs to or is associated with)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactionChanges(): HasMany
    {
        return $this->hasMany(TransactionChange::class);
    }

    public function getAllTransaction($query)
    {
        return $query->with([
            'user',
            'approver',
            'creator',
            'customer',
            'transactionChanges',
        ]);
    }

    public static function getTransactionDetailsWithId($transactionId)
    {
        return self::with([
            'user',
            'approver',
            'creator',
            'customer',
            'transactionChanges' => function ($query) {
                $query->with([
                    'user', 'approver', 'creator'
                ])->latest();
            }
        ])->findOrFail($transactionId);
    }


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
