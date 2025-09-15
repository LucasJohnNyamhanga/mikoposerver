<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OfisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(OfisiService $ofisiService)
    {
        $ofisi = $ofisiService->getAuthenticatedOfisiUser();
        if ($ofisi instanceof JsonResponse) {
            return $ofisi;
        }

        // Current authenticated user with activeOfisi and ofisi details
        $user = User::with(['activeOfisi' => function ($query) {
            $query->with('ofisi'); // get ofisi details for activeOfisi
        }])->find(Auth::id());

        // Stats za ofisi na vifurushi
        $stats = DB::table('ofisis')
            ->leftJoin('kifurushi_purchases', 'ofisis.id', '=', 'kifurushi_purchases.ofisi_id')
            ->selectRaw('COUNT(DISTINCT ofisis.id) as total_ofisi')
            ->selectRaw("COUNT(DISTINCT CASE WHEN ofisis.status = 'active' THEN ofisis.id END) as active_ofisi")
            ->selectRaw("COUNT(DISTINCT CASE WHEN ofisis.status = 'inactive' THEN ofisis.id END) as inactive_ofisi")
            ->selectRaw("COUNT(DISTINCT CASE WHEN kifurushi_purchases.end_date BETWEEN NOW() AND (NOW() + INTERVAL '7 days') THEN ofisis.id END) as expiring_in_7days")
            ->first();

        // Payment totals
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();

        $payments = [
            'today' => DB::table('payments')
                ->whereBetween('created_at', [$today, $today->copy()->endOfDay()])
                ->sum('amount'),

            'this_week' => DB::table('payments')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->sum('amount'),

            'this_month' => DB::table('payments')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount'),
        ];

        // Monthly loan disbursement (chart)
        $monthlyLoanDisbursement = DB::table('loans')
            ->selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(id) as total_loans")
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return response()->json([
            'user' => $user,
            'stats' => $stats,
            'payments' => $payments,
            'monthlyLoanDisbursement' => $monthlyLoanDisbursement,
        ]);
    }




}
