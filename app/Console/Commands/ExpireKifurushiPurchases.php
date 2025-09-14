<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KifurushiPurchase;
use App\Models\SmsBalance;
use App\Services\NotificationService;
use Carbon\Carbon;

class ExpireKifurushiPurchases extends Command
{
    protected $signature = 'kifurushi:expire';
    protected $description = 'Mark kifurushi purchases as expired if end_date is reached or passed';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

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
                if ($smsBalance->used_sms > $smsBalance->offered_sms) {
                    $excess = $smsBalance->used_sms - $smsBalance->offered_sms;
                    $smsBalance->bought_sms = max(0, $smsBalance->bought_sms - $excess);
                }

                $smsBalance->offered_sms = 0;
                $smsBalance->used_sms    = 0;
                $smsBalance->save();
            }

            // 3️⃣ Send notification to the user
            if (!empty($purchase->user->fcm_token)) {
                $title = "Kifurushi chako kimekwisha";
                $body  = "Kifurushi chako cha SMS kimekwisha leo. Tafadhali nunua kifurushi kipya ili kuendelea kutumia huduma.";

                $this->notificationService->sendFcmNotification(
                    $purchase->user->fcm_token,
                    $title,
                    $body,
                    ['purchase_id' => $purchase->id]
                );
            }
        }

        $this->info("Vifurushi {$expiredPurchases->count()} vimebadilishwa baada ya kuexpire na notifications zimetumwa.");
    }
}
