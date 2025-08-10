<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected static function booted(): void
    {
        static::creating(function ($model) {
            // Enforce only if the kifurushi is active
            if (!$model->kifurushi || !$model->kifurushi->is_active) {
                throw new Exception("Huwezi badili ofisi, huna kifurushi hai.");
            }

            $ofisi = Ofisi::with('kifurushiPurchases')->find($model->ofisi_id);
            if (!$ofisi) {
                throw new Exception('Ofisi hii haijapatikana, inawezekana imefutwa.');
            }

            $activePurchase = $ofisi->kifurushiPurchases()
                ->where('status', 'active')
                ->with('kifurushi')
                ->latest()
                ->first();

            $numberOfChanges = $activePurchase->kifurushi->ofisi_creation_count ?? 0;

            $hasKifurushiHistory = $ofisi->kifurushiPurchases()->exists();

            $maxChanges = $hasKifurushiHistory ? $numberOfChanges + 8 : $numberOfChanges + 5;

            if (($model->ofisi_creation_count ?? 0) >= $maxChanges) {
                throw new Exception("Umefikia kikomo cha kuongeza ofisi, umeongeza mara {$maxChanges}.");
            }

            $model->ofisi_creation_count = ($model->ofisi_creation_count ?? 0) + 1;
        });

        static::updating(function ($model) {
            if ($model->isDirty('ofisi_id')) {
                $ofisi = Ofisi::with(['kifurushiPurchases.kifurushi'])->find($model->ofisi_id);

                if (!$ofisi) {
                    throw new Exception('Ofisi hii haijapatikana, inawezekana imefutwa.');
                }

                $activePurchase = $ofisi->kifurushiPurchases()
                    ->where('status', 'active')
                    ->with('kifurushi')
                    ->latest()
                    ->first();

                if (!$activePurchase || !$activePurchase->kifurushi || !$activePurchase->kifurushi->is_active) {
                    throw new Exception("Tumeshindwa badili ofisi, huna kifurushi hai.");
                }

                $numberOfOfisi = $activePurchase->kifurushi->number_of_offices ?? 0;

                $hasKifurushiHistory = $ofisi->kifurushiPurchases()->exists();

                if (!empty($activePurchase->kifurushi->special)) {
                    $maxChanges = $hasKifurushiHistory ? $numberOfOfisi + 8 : $numberOfOfisi + 5;
                } else {
                    // Using floor to ensure integer result
                    $maxChanges = $hasKifurushiHistory
                        ? $numberOfOfisi + floor($numberOfOfisi / 2)
                        : $numberOfOfisi + floor($numberOfOfisi / 4);
                }

                if (($model->ofisi_changes_count ?? 0) >= $maxChanges) {
                    throw new Exception("Umefikia kikomo cha kubadili ofisi, umebadili mara {$maxChanges}.");
                }

                $model->ofisi_changes_count = ($model->ofisi_changes_count ?? 0) + 1;
            }
        });
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
