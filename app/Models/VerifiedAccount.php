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
        static::creating(function ($verified) {
            // Enforce only if the kifurushi is active
            if (!$verified->kifurushi || !$verified->kifurushi->is_active) {
                throw new Exception("Huwezi badili ofisi, huna kifurushi hai.");
            }

            // Get new ofisi (to be assigned)
            $ofisi = Ofisi::with('kifurushiPurchases')->find($verified->ofisi_id);

            if (!$ofisi) {
                throw new Exception('Ofisi hii haijapatikana, inawezekana imefutwa.');
            }
        });

        static::updating(function ($model) {
            // Only check if ofisi_id is changing
            if ($model->isDirty('ofisi_id')) {
                // Get new ofisi (to be assigned)
                $ofisi = Ofisi::with(['kifurushiPurchases.kifurushi'])->find($model->ofisi_id);

                if (!$ofisi) {
                    throw new Exception('Ofisi hii haijapatikana, inawezekana imefutwa.');
                }

                // Check if the new ofisi has active kifurushi
                $activePurchase = $ofisi->kifurushiPurchases()
                    ->where('status', 'active')
                    ->with('kifurushi')
                    ->latest()
                    ->first();

                if (!$activePurchase || !$activePurchase->kifurushi || !$activePurchase->kifurushi->is_active) {
                    throw new Exception("Tumeshindwa badili ofisi, huna kifurushi hai");
                }

                // ✅ Only proceed if special is set (true or has content)
                if (!empty($activePurchase->kifurushi->special)) {
                    // Check modification limit based on ofisi history
                    $hasKifurushiHistory = $ofisi->kifurushiPurchases()->exists();
                    $maxChanges = $hasKifurushiHistory ? 8 : 4;

                    if ($model->ofisi_changes_count >= $maxChanges) {
                        throw new Exception("Umefikia kikomo cha kubadili ofisi, umebadili mara {$maxChanges}.");
                    }

                    // Increment count
                    $model->ofisi_changes_count += 1;
                }
            }
        });
    }


    public function hasActiveKifurushi(): bool
    {
        return $this->kifurushi && $this->kifurushi->is_active;
    }

    public function canModifyOfisi(): bool
    {
        return $this->ofisi_changes_count <= 5;
    }

}
