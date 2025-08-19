<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfisiChange extends Model
{

    protected $table = 'ofisi_changes';

    protected $fillable = [
        'user_id',
        'kifurushi_id',
        'ofisi_changes_count',
        'ofisi_creation_count',
    ];

    /**
     * Mtumiaji anayehusiana na mabadiliko ya ofisi
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kifurushi kinachohusiana na mabadiliko ya ofisi
     */
    public function kifurushi(): BelongsTo
    {
        return $this->belongsTo(Kifurushi::class);
    }
}
