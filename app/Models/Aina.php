<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aina extends Model
{
    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    protected $fillable = [
        'jina',
        'riba',
        'fomu',
        'loan_type',
        'ofisi_id',
    ];
}
