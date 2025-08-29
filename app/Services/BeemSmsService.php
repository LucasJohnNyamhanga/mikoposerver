<?php

namespace App\Services;

use App\Models\SmsBalance;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BeemSmsService
{
    protected string $apiKey;
    protected string $secretKey;
    protected string $baseUrl = "https://apisms.beem.africa";
    protected string $dlrUrl = "https://dlrapi.beem.africa";

    public function __construct()
    {
        $this->apiKey = config('services.beem.api_key');
        $this->secretKey = config('services.beem.secret_key');
    }

    /**
     * Send SMS
     */
    public function sendSms(string $senderId, string $message, array $recipients, User $user, string $scheduleTime = ""): array
    {
        try {
            // Get active SMS balance for the user and office
            $balance = SmsBalance::where('user_id', $user->id)
                ->where('ofisi_id', $user->ofisi_id)
                ->where('status', 'active')
                ->first();

            $senderIdName = $balance->sender_id ?? $senderId;

            // Calculate SMS segments per message (1 SMS = 160 characters)
            $segmentsPerMessage = (int) ceil(strlen($message) / 160);

            // Total SMS to deduct = segments × number of recipients
            $totalSmsToDeduct = $segmentsPerMessage * count($recipients);

            // Check if balance exists and is sufficient
            if ($balance && ($balance->allowed_sms > $balance->used_sms)) {

                // Optional: still send even if totalSmsToDeduct exceeds remaining balance
                // fallback behavior can be handled if needed

                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->secretKey}"),
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/v1/send", [
                    'source_addr'   => $senderIdName,
                    'schedule_time' => $scheduleTime,
                    'encoding'      => 0,
                    'message'       => $message,
                    'recipients'    => $this->formatRecipients($recipients),
                ]);

                $responseData = $response->json();

                if ($response->successful() && (!isset($responseData['code']) || $responseData['code'] == 100)) {
                    // Deduct total SMS segments from balance
                    $balance->used_sms += $totalSmsToDeduct;
                    $balance->save();

                    return [
                        'successful' => true,
                        'data' => $responseData,
                        'used_sms' => $balance->used_sms,
                        'allowed_sms' => $balance->allowed_sms,
                        'segments_per_message' => $segmentsPerMessage,
                        'recipients_count' => count($recipients),
                        'total_sms_deducted' => $totalSmsToDeduct
                    ];
                }
            }

            // Original fallback message
            return ['successful' => false, 'message' => 'SMS failed to send or insufficient balance.'];

        } catch (\Exception $e) {
            Log::error("Beem Send SMS Error: " . $e->getMessage());
            return ['successful' => false, 'message' => $e->getMessage()];
        }
    }


    /**
     * Check Balance
     */
    public function checkBalance()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->secretKey}"),
                'Content-Type'  => 'application/json',
            ])->get("{$this->baseUrl}/public/v1/vendors/balance");

            return $response->json();

        } catch (\Exception $e) {
            Log::error("Beem Check Balance Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }


    /**
     * Get Delivery Report
     */
    public function getDeliveryReport(string $requestId, string $destAddr)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->secretKey}"),
                'Content-Type'  => 'application/json',
            ])->get("{$this->dlrUrl}/public/v1/delivery-reports", [
                'request_id' => $requestId,
                'dest_addr'  => $destAddr,
            ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error("Beem Delivery Report Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get Sender Names
     */
    public function getSenderNames()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->secretKey}"),
                'Content-Type'  => 'application/json',
            ])->get("{$this->baseUrl}/public/v1/sender-names");

            return $response->json();

        } catch (\Exception $e) {
            Log::error("Beem Get Sender Names Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Request New Sender Name
     */
    public function requestSenderName(string $senderId, string $sampleContent)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->secretKey}"),
                'Content-Type'  => 'application/json',
            ])->post("{$this->baseUrl}/public/v1/sender-names", [
                'senderid'       => $senderId,
                'sample_content' => $sampleContent,
            ]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error("Beem Request Sender Name Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Helper: Format Recipients
     */
    /**
     * Format recipients for Beem API and normalize numbers
     */
    private function formatRecipients(array $recipients): array
    {
        return collect($recipients)->map(function($number, $index) {
            // Remove any spaces, dashes, or plus signs
            $number = preg_replace('/[\s\-\+]/', '', $number);

            // Convert local number starting with 0 to international format (Tanzania example)
            if (str_starts_with($number, '0')) {
                $number = '255' . substr($number, 1);
            }

            return [
                'recipient_id' => $index + 1,
                'dest_addr'    => $number,
            ];
        })->toArray();
    }

}
