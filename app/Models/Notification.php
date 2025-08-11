<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property int $stage
 * @property string $condition_key
 * @property string|null $image_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',          // 'sms', 'vifurushi', 'tip'
        'stage',         // 1, 2, 3 urgency level
        'condition_key', // e.g., 'sms_low_balance', 'vifurushi_expiring'
        'image_url',
        'is_active',
    ];

    /**
     * Scope to only active notifications.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only SMS and Vifurushi notifications.
     */
    public function scopeReminders($query)
    {
        return $query->whereIn('type', ['sms', 'vifurushi']);
    }

    /**
     * Scope to only Tips.
     */
    public function scopeTips($query)
    {
        return $query->where('type', 'tip');
    }

    /**
     * Scope by stage (1-3).
     */
    public function scopeStage($query, $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Scope by condition key.
     */
    public function scopeCondition($query, $conditionKey)
    {
        return $query->where('condition_key', $conditionKey);
    }

    /**
     * Get prioritized notifications:
     * - SMS & Vifurushi first
     * - Fill with random tips if less than $limit
     * - Optionally filter by stage & condition
     */
    public static function getPrioritized($limit = 5, $stage = null, $conditionKey = null)
    {
        $query = self::active()->reminders()->latest();

        if (!is_null($stage)) {
            $query->stage($stage);
        }

        if (!is_null($conditionKey)) {
            $query->condition($conditionKey);
        }

        $reminders = $query->take($limit)->get();

        // Fill remaining with tips if needed
        if ($reminders->count() < $limit) {
            $tipsNeeded = $limit - $reminders->count();

            $tipsQuery = self::active()->tips()->inRandomOrder();

            if (!is_null($stage)) {
                $tipsQuery->stage($stage);
            }

            if (!is_null($conditionKey)) {
                $tipsQuery->condition($conditionKey);
            }

            $tips = $tipsQuery->take($tipsNeeded)->get();

            $reminders = $reminders->merge($tips);
        }

        return $reminders;
    }
}
