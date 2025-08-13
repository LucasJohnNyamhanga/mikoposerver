<?php

namespace App\Services;

use App\Models\KifurushiPurchase;
use App\Models\Payment;
use App\Models\SmsBalance;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class ZenoPayService
{
    protected string $baseUrl = 'https://zenoapi.com/api';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.zenopay.token');
    }

    /**
     * Create a mobile money payment request using ZenoPay.
     * @throws Exception
     */
    public function createPayment(
        string $orderId,
        string $buyerEmail,
        string $buyerName,
        string $buyerPhone,
        float|int $amount,
        ?string $webhookUrl = null
    ): array {
        $webhookUrl ??= config('services.zenopay.webhook_url');

        $payload = [
            'order_id'    => $orderId,
            'buyer_email' => $buyerEmail,
            'buyer_name'  => $buyerName,
            'buyer_phone' => $buyerPhone,
            'amount'      => $amount,
            'webhook_url' => $webhookUrl,
        ];

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->retry(3, 1000)
                ->post("$this->baseUrl/payments/mobile_money_tanzania", $payload);

            if ($response->failed()) {
                throw new Exception('ZenoPay API Error: ' . $response->body());
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error("ZenoPay createPayment error: " . $e->getMessage());
            throw new Exception("ZenoPay request failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check the payment status from ZenoPay and update local records.
     */
    public function checkStatus(string $reference): array
    {
        $url = "$this->baseUrl/payments/order-status?order_id=$reference";

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->retry(3, 2000)
                ->get($url);

            Log::info("ZenoPay status response for $reference: {$response->body()}");

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['data'][0])) {
                return $this->handleSuccessfulStatusResponse($reference, $responseData['data'][0]);
            }

            Log::warning("ZenoPay unexpected response format for $reference");
        } catch (Throwable $e) {
            Log::error("ZenoPay checkStatus error for $reference: " . $e->getMessage());
        }

        return [
            'status'  => 'pending',
            'details' => null,
        ];
    }

    /**
     * Shared headers for all requests.
     */
    protected function headers(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'Accept'    => 'application/json',
        ];
    }

    /**
     * Handle ZenoPay success response and update local payment record.
     */
    protected function handleSuccessfulStatusResponse(string $reference, array $paymentInfo): array
    {
        $status = strtolower($paymentInfo['payment_status'] ?? 'pending');

        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            Log::warning("Payment with reference $reference not found.");
            return ['status' => 'not_found'];
        }

        try {
            DB::transaction(function () use ($payment, $paymentInfo, $status) {
                // Update local payment
                $payment->update([
                    'status' => $status,
                    'transaction_id' => $paymentInfo['transid'] ?? $payment->transaction_id,
                    'channel' => $paymentInfo['channel'] ?? $payment->channel,
                    'amount' => $paymentInfo['amount'] ?? $payment->amount,
                    'paid_at' => now(),
                ]);

                // ✅ If SMS purchase, update SMS balance
                if ($status === 'completed' && !empty($payment->sms_amount)) {
                    $this->updateSmsBalance($payment);
                }

                // ✅ Kifurushi purchase, create purchase record
                    $this->createKifurushiPurchaseIfNotExists($payment);

            });
        } catch (Throwable $e) {
            Log::error("Error updating payment $reference: " . $e->getMessage());
        }

        return [
            'status'  => $status,
            'details' => $paymentInfo,
        ];
    }

    /**
     * Create a kifurushi purchase record if not already linked.
     */
    protected function createKifurushiPurchaseIfNotExists(Payment $payment): void
    {
        if (!KifurushiPurchase::where('reference', $payment->reference)->exists()) {
            KifurushiPurchase::create([
                'user_id'       => $payment->user_id,
                'kifurushi_id'  => $payment->kifurushi_id,
                'status'        => 'approved',
                'start_date'    => now(),
                'end_date'      => now()->addDays($payment->kifurushi->duration_in_days),
                'is_active'     => true,
                'approved_at'   => now(),
                'reference'     => $payment->reference,
                'ofisi_id'      => $payment->ofisi_id,
            ]);
        }
    }

    /**
     * Update SMS balance when a user purchases SMS bundles.
     */
    protected function updateSmsBalance(Payment $payment): void
    {
        try {
            DB::transaction(function () use ($payment) {
                $balance = SmsBalance::where('user_id', $payment->user_id)
                    ->where('ofisi_id', $payment->ofisi_id)
                    ->where('status', 'active')
                    ->first();

                if ($balance) {
                    // Add purchased SMS to existing balance
                    $balance->increment('allowed_sms', $payment->sms_amount);
                } else {
                    // Create a new SMS balance record
                    SmsBalance::create([
                        'user_id' => $payment->user_id,
                        'ofisi_id' => $payment->ofisi_id,
                        'allowed_sms' => $payment->sms_amount,
                        'used_sms' => 0,
                        'start_date' => now()->toDateString(),
                        'expires_at' => now()->addMonth()->toDateString(),
                        'status' => 'active',
                        'sender_id' => $payment->sender_id ?? null,
                        'phone' => $payment->phone,
                    ]);
                }
            });
        } catch (Throwable $e) {
            Log::error("Failed to update SMS balance for payment {$payment->reference}: " . $e->getMessage());
        }
    }
}
