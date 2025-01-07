<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
