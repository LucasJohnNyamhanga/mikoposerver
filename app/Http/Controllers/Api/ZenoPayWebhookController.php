<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Services\ZenoPayService;

class ZenoPayWebhookController extends Controller
{
    protected ZenoPayService $zenoPay;

    public function __construct(ZenoPayService $zenoPay)
    {
        $this->zenoPay = $zenoPay;
    }

    public function handle(Request $request)
    {
        $data = $request->all();
        $orderId = $data['order_id'] ?? null;
        $status  = strtolower($data['payment_status'] ?? '');

        if (!$orderId || $status !== 'completed') {
            Log::info("ZenoPay Webhook Ignored: Missing order_id or non-completed status.", $data);
            return response()->json(['message' => 'Ignored or invalid status'], 200);
        }

        $payment = Payment::where('reference', $orderId)->first();

        if (!$payment) {
            Log::warning("ZenoPay Webhook: Payment not found for order ID: $orderId");
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($payment->status === 'completed') {
            Log::info("ZenoPay Webhook: Payment already marked as completed for $orderId.");
            return response()->json(['message' => 'Already completed'], 200);
        }

        // Let ZenoPayService handle final verification and local updates (including KifurushiPurchase creation)
        $result = $this->zenoPay->checkStatus($orderId);

        if (($result['status'] ?? '') !== 'completed') {
            Log::info("ZenoPay Webhook: Status check did not confirm completion for $orderId.");
            return response()->json(['message' => 'Status not confirmed'], 200);
        }

        Log::info("ZenoPay Webhook: Payment $orderId marked as COMPLETED.");

        return response()->json(['message' => 'Payment updated'], 200);
    }
}
