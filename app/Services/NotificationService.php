<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

class NotificationService
{
    protected string $serviceAccountPath;

    public function __construct()
    {
        // Path to your Firebase service account JSON
        $this->serviceAccountPath = storage_path('app/firebase/service-account.json');
    }

    /**
     * Send FCM notification using HTTP v1 API.
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendFcmNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        // Load service account credentials
        $credentials = new ServiceAccountCredentials(
            null,
            [
                'keyFile' => $this->serviceAccountPath,
                'scopes'  => ['https://www.googleapis.com/auth/firebase.messaging'],
            ]
        );

        // Get OAuth token
        $accessToken = $credentials->fetchAuthToken()['access_token'] ?? null;

        if (!$accessToken) {
            return false;
        }

        $client = new Client();

        $projectId = json_decode(file_get_contents($this->serviceAccountPath), true)['project_id'];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => $data,
            ]
        ];

        $response = $client->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response->getStatusCode() === 200;
    }
}
