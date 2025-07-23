<?php

namespace App\Services;

use App\Models\KifurushiPurchase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ZenoPayService
{
    protected string $baseUrl = 'https://zenoapi.com/api';

    public function createPayment(
        string $orderId,
        string $buyerEmail,
        string $buyerName,
        string $buyerPhone,
        float|int $amount,
        ?string $webhookUrl = null
    ): array {
        $apiKey = config('services.zenopay.token');
        $webhookUrl = $webhookUrl ?? config('services.zenopay.webhook_url');

        $payload = [
            'order_id'    => $orderId,
            'buyer_email' => $buyerEmail,
            'buyer_name'  => $buyerName,
            'buyer_phone' => $buyerPhone,
            'amount'      => $amount,
        ];

        if ($webhookUrl) {
            $payload['webhook_url'] = $webhookUrl;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Accept'    => 'application/json',
            ])
            ->timeout(30)
            ->retry(3, 1000)
            ->post("{$this->baseUrl}/payments/mobile_money_tanzania", $payload);

            if ($response->failed()) {
                throw new \Exception('ZenoPay API Error: ' . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            throw new \Exception("ZenoPay request failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function checkStatus(string $reference): array
    {
        $apiKey = config('services.zenopay.token');

        try {
            $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'Accept'    => 'application/json',
                ])
                ->timeout(15)
                ->retry(3, 2000)
                ->get("{$this->baseUrl}/payments/order-status?order_id={$reference}");

            Log::info("ZenoPay status response for {$reference}: {$response->body()}");

            $responseData = $response->json();

            if (
                $response->successful() &&
                isset($responseData['data'][0])
            ) {
                $paymentInfo = $responseData['data'][0];
                $status = strtolower($paymentInfo['payment_status']);

                $payment = Payment::where('reference', $reference)->first();

                if (!$payment) {
                    Log::warning("Payment with reference {$reference} not found.");
                    return ['status' => 'not_found'];
                }

                DB::transaction(function() use ($payment, $paymentInfo, $status, $reference) {
                    $payment->update([
                        'status' => $status,
                        'transaction_id' => $paymentInfo['transid'] ?? $payment->transaction_id,
                        'channel' => $paymentInfo['channel'] ?? $payment->channel,
                        'amount' => $paymentInfo['amount'] ?? $payment->amount,
                        'paid_at' => now(),
                    ]);

                    if ($status === 'completed') {
                        $alreadyLinked = KifurushiPurchase::where('reference', $reference)->exists();
                        if (!$alreadyLinked) {
                            KifurushiPurchase::create([
                                'user_id' => $payment->user_id,
                                'kifurushi_id' => $payment->kifurushi_id,
                                'status' => 'approved',
                                'start_date' => now(),
                                'end_date' => now()->addDays($payment->kifurushi->duration_in_days),
                                'is_active' => true,
                                'approved_at' => now(),
                                'reference' => $payment->reference,
                            ]);
                        }
                    }
                });

                return [
                    'status' => $status,
                    'details' => $paymentInfo,
                ];
            }

            Log::warning("ZenoPay unexpected response format for {$reference}");

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("ZenoPay request exception for {$reference}: {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::error("ZenoPay unknown error for {$reference}: {$e->getMessage()}");
        }

        return [
            'status'  => 'pending',
            'details' => null,
        ];
    }
}
