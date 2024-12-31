<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // A customer can have multiple loans
    public function loans()
    {
        return $this->belongsToMany(Loan::class, 'loan_customers');
    }

    protected $fillable = [
        'jina',
        'jinaMaarufu',
        'jinsia',
        'anapoishi',
        'simu',
        'kazi',
        'picha',
        'office_id',
        'user_id',
    ];

}
