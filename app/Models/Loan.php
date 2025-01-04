<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }

    // A loan belongs to a user (the issuer)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // A loan can have multiple customers (group loans)
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'loan_customers');
    }

    // Check if the loan is personal
    public function isPersonal()
    {
        return $this->customers()->count() === 1;
    }

    // Check if the loan is group
    public function isGroup()
    {
        return $this->customers()->count() > 1;
    }

    // Retrieve the customer for a personal loan
    public function personalCustomer()
    {
        if ($this->isPersonal()) {
            return $this->customers()->first();
        }
        return null;
    }

    public function mabadiliko()
    {
        return $this->hasMany(Mabadiliko::class);
    }

    // $personalLoans = Loan::whereHas('customers', function ($query) {
    //     $query->havingRaw('count(*) = 1'); // Personal loan has only 1 customer
    // })
    // ->with('customers') // Eager load the customers relationship
    // ->get();

    // $groupLoans = Loan::whereHas('customers', function ($query) {
    //     $query->havingRaw('count(*) > 1'); // Group loan has more than 1 customer
    // })
    // ->with('customers') // Eager load the customers relationship
    // ->get();

    public function wadhamini()
    {
        return $this->belongsToMany(Customer::class, 'mdhaminis', 'loan_id', 'customer_id')
                    ->withTimestamps();
    }

    public function dhamana()
    {
        return $this->hasMany(Dhamana::class);
    }

}
