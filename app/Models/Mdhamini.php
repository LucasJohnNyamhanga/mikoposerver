<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mdhamini extends Model
{
    protected $fillable = [
        'loan_id',
        'customer_id',
    ];

    /**
     * Define the relationship with the Loan model.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Define the relationship with the User model (the guarantor).
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
