<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KifurushiPurchase;
use Carbon\Carbon;

class ExpireKifurushiPurchases extends Command
{
    protected $signature = 'kifurushi:expire';
    protected $description = 'Mark kifurushi purchases as expired if end_date is reached or passed';

    public function handle()
    {
        $today = Carbon::now('Africa/Nairobi')->setTime(3, 0, 0);

        // Pata vifurushi vyote vilivyo hai lakini vimekoma
        $expiredPurchases = KifurushiPurchase::where('is_active', true)
            ->where('status', 'approved')
            ->whereDate('end_date', '<=', $today)
            ->get();

        foreach ($expiredPurchases as $purchase) {
            // 1️⃣ Mark kifurushi as expired
            $purchase->update([
                'status'     => 'expired',
                'is_active'  => false,
                'updated_at' => now(),
            ]);

            // 2️⃣ Adjust SMS balance kwa user + ofisi
            $smsBalance = SmsBalance::where('user_id', $purchase->user_id)
                ->where('ofisi_id', $purchase->ofisi_id)
                ->where('status', 'active')
                ->first();

            if ($smsBalance) {
                // Ikiwa used_sms > offered_sms, punguza difference kwenye bought_sms
                if ($smsBalance->used_sms > $smsBalance->offered_sms) {
                    $excess = $smsBalance->used_sms - $smsBalance->offered_sms;
                    $smsBalance->bought_sms = max(0, $smsBalance->bought_sms - $excess);
                }

                // Reset offered_sms na used_sms
                $smsBalance->offered_sms = 0;
                $smsBalance->used_sms    = 0;

                // Optional: deactivate balance
                //$smsBalance->status = 'expired';

                $smsBalance->save();
            }
        }

        $this->info("Vifurushi {$expiredPurchases->count()} vimebadilishwa baada ya kuexpire.");
    }

}
