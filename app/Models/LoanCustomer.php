<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCustomer extends Model
{
    // Each LoanCustomer belongs to a Loan
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    // Each LoanCustomer belongs to a Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    protected $fillable = [
        'loan_id',
        'customer_id',
    ];
}
