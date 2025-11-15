<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BeemSmsService;
use App\Services\OfisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // 3️⃣ Aggregate office stats in one query
        $stats = DB::table('ofisis')
        ->leftJoin('kifurushi_purchases', 'ofisis.id', '=', 'kifurushi_purchases.ofisi_id')
        ->leftJoin('kifurushis', 'kifurushi_purchases.kifurushi_id', '=', 'kifurushis.id')
        ->leftJoin('payments', function ($join) {
            $join->on('ofisis.id', '=', 'payments.ofisi_id')
                    ->where('payments.status', 'completed')
                    ->where('payments.paid_at', '>=', now()->subDays(7));
        })
        ->selectRaw("
            COUNT(DISTINCT ofisis.id) AS total_ofisi,
            COUNT(DISTINCT CASE WHEN ofisis.status = 'active' THEN ofisis.id END) AS active_ofisi,
            COUNT(DISTINCT CASE WHEN ofisis.status = 'inactive' THEN ofisis.id END) AS inactive_ofisi,
            COUNT(DISTINCT CASE WHEN kifurushi_purchases.end_date BETWEEN NOW() AND (NOW() + INTERVAL '7 days') THEN ofisis.id END) AS expiring_in_7days,
            COUNT(DISTINCT CASE WHEN payments.id IS NOT NULL THEN ofisis.id END) AS ofisi_paid_last_7days,
            COUNT(DISTINCT CASE WHEN kifurushi_purchases.created_at >= NOW() - INTERVAL '7 days' THEN ofisis.id END) AS ofisi_bought_new_kifurushi_last_7days,
            COUNT(DISTINCT CASE WHEN kifurushis.name = 'majaribio' AND ofisis.status = 'active' THEN ofisis.id END) AS active_majaribio_ofisi,
            COUNT(DISTINCT CASE WHEN DATE(ofisis.last_seen) = CURRENT_DATE THEN ofisis.id END) AS active_today_ofisi,
            (SELECT COUNT(*) FROM users WHERE DATE(last_login_at) = CURRENT_DATE) AS active_today_users
        ")
        ->first();


        // 4️⃣ Payments this month (optimized single query)
        $today = now()->day;
        $rawPayments = DB::table('payments')
            ->selectRaw('EXTRACT(DAY FROM paid_at)::int AS day, SUM(amount)::float AS total')
            ->where('status', 'completed')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->whereDay('paid_at', '<=', $today)
            ->groupBy('day')
            ->pluck('total', 'day');

        // Build array with zeros for missing days
        $payments = [];
        for ($i = 1; $i <= $today; $i++) {
            $payments[] = [
                'day' => $i,
                'total' => $rawPayments[$i] ?? 0,
            ];
        }

        // 5️⃣ SMS Balance
        $smsBalance = $smsService->checkBalance();

        // 6️⃣ Monthly loan disbursement (optimized)
        $monthlyLoanDisbursement = DB::table('loans')
            ->selectRaw("DATE_TRUNC('month', created_at) AS month, COUNT(id) AS total_loans")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 7️⃣ Return JSON response
        return response()->json([
            'user' => $user,
            'stats' => $stats,
            'payments' => $payments,
            'monthlyLoanDisbursement' => $monthlyLoanDisbursement,
            'sms' => $smsBalance,
        ]);
    }

}
