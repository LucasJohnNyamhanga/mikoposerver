<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\KifurushiPurchase;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    protected $ofisiService;

    // Inject your ofisi service via constructor or however you manage DI
    public function __construct($ofisiService)
    {
        $this->ofisiService = $ofisiService;
    }

    /**
     * Get the most urgent notification for the authenticated user based on SMS balance and kifurushi expiry.
     */
    public function getNotifications(): JsonResponse
    {
        // 1. Get authenticated office (ofisi)
        $ofisi = $this->ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi; // error or no office found response
        }

        $user = auth()->user();
        $ofisiTargetId = $ofisi->id;

        // === Notification Logic ===
        $notification = null;
        $notifications = [];

        // Step 1: Get all user IDs in this office
        $userIdsInOfisi = DB::table('user_ofisis')
            ->where('ofisi_id', $ofisiTargetId)
            ->pluck('user_id');

        if ($userIdsInOfisi->isNotEmpty()) {
            // Step 2: Calculate total SMS balance
            $totalSmsBalance = DB::table('sms_balances')
                ->whereIn('user_id', $userIdsInOfisi)
                ->where('status', 'active')
                ->whereDate('expires_at', '>=', now())
                ->selectRaw('SUM(allowed_sms - used_sms) as balance')
                ->value('balance');

            $totalSmsBalance = (int) $totalSmsBalance;

            // Step 3: Determine notification based on SMS balance or kifurushi expiry
            if ($totalSmsBalance <= 0) {
                $notification = Notification::getPrioritized(1, 3, 'sms_balance')->first();
            } elseif ($totalSmsBalance <= 10) {
                $notification = Notification::getPrioritized(1, 2, 'sms_balance')->first();
            } elseif ($totalSmsBalance <= 50) {
                $notification = Notification::getPrioritized(1, 1, 'sms_balance')->first();
            } else {
                $activePurchase = KifurushiPurchase::whereIn('user_id', $userIdsInOfisi)
                    ->where('status', 'active')
                    ->latest('expires_at')
                    ->first();

                if (!$activePurchase) {
                    $notification = Notification::getPrioritized(1, 3, 'kifurushi_expired')->first();
                } else {
                    $daysLeft = now()->diffInDays($activePurchase->expires_at, false);

                    if ($daysLeft < 0) {
                        $stage = 3;
                    } elseif ($daysLeft < 3) {
                        $stage = 2;
                    } elseif ($daysLeft < 7) {
                        $stage = 1;
                    } else {
                        $stage = null;
                    }

                    if ($stage !== null) {
                        $notification = Notification::getPrioritized(1, $stage, 'kifurushi_expiry')->first();
                    }
                }
            }
        }

        // Step 4: Fetch other info notifications excluding main keys, random order
        $excludedKeys = ['sms_balance', 'kifurushi_expiry', 'kifurushi_expired'];

        $infoNotifications = Notification::whereNotIn('condition_key', $excludedKeys)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        // Step 5: Prepare combined notifications list
        if ($notification) {
            $notifications[] = [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'stage' => $notification->stage,
                'condition_key' => $notification->condition_key,
                'image_url' => $notification->image_url,
            ];
        }

        foreach ($infoNotifications as $info) {
            $notifications[] = [
                'id' => $info->id,
                'title' => $info->title,
                'message' => $info->message,
                'type' => $info->type,
                'stage' => $info->stage,
                'condition_key' => $info->condition_key,
                'image_url' => $info->image_url,
            ];
        }

        // Return final JSON response (only notifications)
        return response()->json([
            'notifications' => $notifications,
        ]);
    }


}
