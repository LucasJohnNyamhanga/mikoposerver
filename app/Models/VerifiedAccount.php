<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * @method static where(string $string, int $user_id)
 */
class VerifiedAccount extends Model
{
    protected $fillable = [
        'user_id',
        'kifurushi_id',
        'ofisi_id',
        'ofisi_changes_count',
        'ofisi_creation_count',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kifurushi(): BelongsTo
    {
        return $this->belongsTo(Kifurushi::class);
    }

    public function ofisi(): BelongsTo
    {
        return $this->belongsTo(Ofisi::class);
    }

    public function hasActiveKifurushi(): bool
    {
        return $this->kifurushi && $this->kifurushi->is_active;
    }

    public function canModifyOfisi(): bool
    {
        // Adjust limit as needed, or make dynamic
        return ($this->ofisi_changes_count ?? 0) <= 5;
    }
}
