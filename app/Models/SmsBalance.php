<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class SmsBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ofisi_id',
        'allowed_sms',
        'used_sms',
        'start_date',
        'expires_at',
        'status',
    ];

    protected $dates = [
        'start_date',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ofisi()
    {
        return $this->belongsTo(Ofisi::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->whereDate('expires_at', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereDate('expires_at', '<', now());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function remainingSms(): int
    {
        return max(0, $this->allowed_sms - $this->used_sms);
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && $this->status === 'active';
    }

    public function hasSmsLeft(): bool
    {
        return $this->remainingSms() > 0;
    }

    public function incrementUsage(int $count = 1): void
    {
        $this->used_sms += $count;
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Auto-setting 1-year validity
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function ($sms) {
            $sms->start_date = $sms->start_date ?? now();
            $sms->expires_at = $sms->expires_at ?? Carbon::parse($sms->start_date)->addYear();
        });
    }
}
