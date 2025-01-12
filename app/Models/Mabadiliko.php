<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mabadiliko extends Model
{
    protected $fillable = [
        'loan_id',
        'performed_by',
        'action',
        'description',
    ];

    /**
     * Define the relationship with the Loan model.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Define the relationship with the User model (the one who performed the action).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
