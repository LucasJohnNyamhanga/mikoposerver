<?php

namespace App\Console\Commands;

use App\Services\BeemSmsService;
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

    public function handle(BeemSmsService $smsService)
    {
        $today = Carbon::now('Africa/Nairobi')->setTime(3, 0, 0);

        // Get all active but expired purchases, eager-load user
        $expiredPurchases = KifurushiPurchase::with('user')
            ->where('is_active', true)
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

            // 2️⃣ Adjust SMS balance
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

            // 3️⃣ Send FCM notification
            if (!empty($purchase->user->fcm_token)) {
                $title = "Kifurushi chako kimekwisha";
                $body  = "Kifurushi chako cha Mfumo kimekwisha leo. Tafadhali nunua kifurushi kipya ili kuendelea kutumia huduma.";

                $this->notificationService->sendFcmNotification(
                    $purchase->user->fcm_token,
                    $title,
                    $body,
                    ['purchase_id' => $purchase->id]
                );
            }

            // 4️⃣ Send free SMS
            if (!empty($purchase->user->mobile)) {
                $recipients = [$purchase->user->mobile];
                $message = "Habari {$this->jina($purchase->user->jina_kamili)}! Kifurushi chako cha mfumo wa Mikopo Center kimekwisha. Nunua upya ili uendelelee kutatua changamoto kiurahisi kupitia mfumo wa kidigitali!";
                $senderId = "Datasoft";

                $smsService->sendFreeSms($senderId, $message, $recipients);
            }
        }

        $this->info("Vifurushi {$expiredPurchases->count()} vimebadilishwa baada ya kuexpire na notifications zimetumwa.");
    }

    protected function jina(string $jina): string
    {
        if (!$jina) return '';
        $parts = explode(' ', trim($jina));
        return $parts[0] ?? '';
    }

}
