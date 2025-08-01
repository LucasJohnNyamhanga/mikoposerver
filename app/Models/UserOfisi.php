<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserOfisi extends Pivot
{
    protected $table = 'user_ofisis';

    protected $fillable = [
        'user_id',
        'ofisi_id',
        'position_id',
        'status',
        'isActive',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }
}
