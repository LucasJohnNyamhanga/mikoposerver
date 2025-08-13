<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\ZenoPayService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ZenoPayController extends Controller
{
    protected ZenoPayService $zenoPay;

    public function __construct(ZenoPayService $zenoPay)
    {
        $this->zenoPay = $zenoPay;
    }

    /**
     * Initiates a payment via ZenoPay API
     */
    public function initiatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'mobile' => 'required|string|min:10|max:15',
            'reference' => 'required|string|max:50|unique:payments,reference',
            'kifurushiId' => 'required|exists:kifurushis,id',
            'buyerEmail' => 'nullable|email',
            'buyerName' => 'nullable|string|max:100',
            'smsAmount' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $buyerEmail = $request->buyerEmail ?? 'datasofttanzania@gmail.com';
        $buyerName = $request->buyerName ?? 'Anonymous User';
        $webhookUrl = config('services.zenopay.webhook_url');

        try {
            // Create payment via ZenoPay API
            $response = $this->zenoPay->createPayment(
                orderId: $request->reference,
                buyerEmail: $buyerEmail,
                buyerName: $buyerName,
                buyerPhone: $request->mobile,
                amount: $request->amount,
                webhookUrl: $webhookUrl
            );

            $channel = $this->getMtandaoFromNumber($request->mobile);

            // Determine ofisi_id, e.g. from user's activeOfisi relation or fallback

            if ($user->relationLoaded('activeOfisi') && $user->activeOfisi) {
                $ofisiId = $user->activeOfisi->ofisi_id ?? null;
            } else {
                // Fallback: get first accepted ofisi if no activeOfisi loaded
                $firstOfisi = $user->ofisis()->first();
                $ofisiId = $firstOfisi?->id;
            }

            // Save to local DB with ofisi_id
            $payment = Payment::create([
                'reference'     => $request->reference,
                'amount'        => $request->amount,
                'status'        => 'pending',
                'kifurushi_id'  => $request->kifurushiId,
                'phone'         => $request->mobile,
                'user_id'       => $user->id,
                'ofisi_id'      => $ofisiId,
                'channel'       => $channel,
                'sms_amount'    => $request->smsAmount, // NEW
            ]);

            Log::info('ZenoPay initiated successfully', [
                'reference' => $payment->reference,
                'mobile' => $payment->phone,
                'user_id' => $user->id,
                'ofisi_id' => $ofisiId,
                'api_response' => $response,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subiri meseji mtandao kukamilisha malipo.',
                'data' => [
                    'zenopay' => $response,
                    'reference' => $payment->reference,
                    'payment_id' => $payment->id,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('ZenoPay initiation failed', [
                'reference' => $request->reference ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Malipo yameshindikana kuanzishwa, jaribu tena baadae.',
            ], 500);
        }
    }

    /**
     * Detect mobile network operator from phone number
     */
    private function getMtandaoFromNumber(string $number): string
    {
        // Remove non-numeric characters
        $number = preg_replace('/\D+/', '', $number);

        // Standardize number to start with 0
        if (str_starts_with($number, '255')) {
            $number = '0' . substr($number, 3);
        }

        if (!preg_match('/^0\d{9}$/', $number)) {
            return 'Mtandao';
        }

        $prefix = substr($number, 0, 3);

        return match ($prefix) {
            '075', '076', '074'         => 'Mpesa',       // Vodacom
            '078', '068', '069', '079'  => 'AirtelMoney', // Airtel
            '071', '077', '065'         => 'TigoPesa',    // Tigo
            '062', '061'                => 'HaloPesa',    // Halotel
            '073'                       => 'TTCLPesa',    // TTCL
            default                     => 'Mtandao',     // Unknown/Other
        };
    }
}
