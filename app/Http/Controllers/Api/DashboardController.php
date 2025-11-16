<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BeemSmsService;
use App\Services\OfisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(OfisiService $ofisiService, BeemSmsService $smsService)
    {
        // 1️⃣ Validate authenticated office
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        // 2️⃣ Get authenticated user with active office
        $user = User::with(['activeOfisi.ofisi'])->find(Auth::id());

        // 3️⃣ Cache the dashboard stats for 5 minutes
        $stats = Cache::remember("dashboard_stats_{$user->id}", 300, function () {
            return DB::table('ofisis')
                ->leftJoin('kifurushi_purchases as kp', 'kp.ofisi_id', '=', 'ofisis.id')
                ->leftJoin('kifurushis as kf', 'kp.kifurushi_id', '=', 'kf.id')
                ->leftJoin('payments as p', function ($join) {
                    $join->on('p.ofisi_id', '=', 'ofisis.id')
                        ->where('p.status', 'completed')
                        ->where('p.paid_at', '>=', now()->subDays(7));
                })
                ->selectRaw("
                    COUNT(DISTINCT ofisis.id) AS total_ofisi,
                    COUNT(DISTINCT CASE WHEN ofisis.status = 'active' THEN ofisis.id END) AS active_ofisi,
                    COUNT(DISTINCT CASE WHEN ofisis.status = 'inactive' THEN ofisis.id END) AS inactive_ofisi,
                    COUNT(DISTINCT CASE WHEN kp.end_date BETWEEN NOW() AND (NOW() + INTERVAL '7 days') THEN ofisis.id END) AS expiring_in_7days,
                    COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN ofisis.id END) AS ofisi_paid_last_7days,
                    COUNT(DISTINCT CASE WHEN kp.created_at >= NOW() - INTERVAL '7 days' AND kf.name NOT ILIKE 'Majaribio' THEN ofisis.id END) AS ofisi_bought_new_kifurushi_last_7days,
                    COUNT(DISTINCT CASE WHEN kf.name ILIKE 'Majaribio' AND kp.is_active = TRUE THEN ofisis.id END) AS active_majaribio_ofisi,
                    COUNT(DISTINCT CASE WHEN DATE(ofisis.last_seen) = CURRENT_DATE THEN ofisis.id END) AS active_today_ofisi,
                    (SELECT COUNT(*) FROM users WHERE DATE(last_login_at) = CURRENT_DATE) AS active_today_users
                ")
                ->first();
        });

        // 4️⃣ Payments this month (cached)
        $today = now()->day;
        $payments = Cache::remember("dashboard_payments_{$user->id}", 300, function () use ($today) {
            $rawPayments = DB::table('payments')
                ->selectRaw('EXTRACT(DAY FROM paid_at)::int AS day, SUM(amount)::float AS total')
                ->where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->whereDay('paid_at', '<=', $today)
                ->groupBy('day')
                ->pluck('total', 'day')
                ->toArray();

            // Build array with zeros for missing days
            $paymentsArray = [];
            for ($i = 1; $i <= $today; $i++) {
                $paymentsArray[] = [
                    'day' => $i,
                    'total' => $rawPayments[$i] ?? 0,
                ];
            }
            return $paymentsArray;
        });

        // 5️⃣ SMS Balance (cached)
        $smsBalance = Cache::remember("dashboard_sms_{$user->id}", 300, function () use ($smsService) {
            try {
                return $smsService->checkBalance();
            } catch (\Throwable $e) {
                return [
                    'balance' => 0,
                    'error' => true,
                    'message' => 'Could not fetch SMS balance'
                ];
            }
        });

        // 6️⃣ Return JSON response
        return response()->json([
            'user' => $user,
            'stats' => $stats,
            'payments' => $payments,
            'sms' => $smsBalance,
        ]);
    }

}
