<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dhamana extends Model
{
    public function loan():BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer():BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    protected $fillable = [
        'jina', 'thamani', 'maelezo', 'picha',
        'is_ofisi_owned', 'is_sold', 'stored_at',
        'loan_id', 'customer_id', 'ofisi_id',
    ];
}
