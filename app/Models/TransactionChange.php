<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionChange extends Model
{
    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
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
}
