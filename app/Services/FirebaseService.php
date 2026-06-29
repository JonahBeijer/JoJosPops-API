<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected function getAccessToken(): string
    {
        $client = new Client();

        $credentials = [
            'type' => 'service_account',
            'project_id' => config('services.firebase.project_id'),
            'private_key_id' => config('services.firebase.private_key_id'),
            'private_key' => str_replace('\\n', "\n", config('services.firebase.private_key')),
            'client_email' => config('services.firebase.client_email'),
            'client_id' => config('services.firebase.client_id'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => config('services.firebase.client_x509_cert_url'),
            'universe_domain' => 'googleapis.com',
        ];

        $client->setAuthConfig($credentials);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();

        if (!isset($token['access_token'])) {
            throw new \Exception(json_encode($token));
        }

        return $token['access_token'];
    }

    public function send(string $deviceToken, array $data): array
    {
        $projectId = config('services.firebase.project_id');

        $payload = [
            'message' => [
                'token' => trim($deviceToken),

                // 🔥 VERWIJDER DE 'notification' KEY VOLLEDIG

                // Stuur alleen 'data'. Dit dwingt de app om onMessage te gebruiken.
                'data' => array_map('strval', $data),
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            // 🔥 Geen 'sound' of 'alert' hier! Alleen content-available.
                            // Dit voorkomt de dubbele notificatie op iOS.
                            'content-available' => 1,
                        ],
                    ],

                ],
            ],
        ];

        \Log::info($payload);

        $response = Http::withToken($this->getAccessToken())
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                $payload
            );

        if (!$response->successful()) {
            \Log::error("Firebase fout: " . $response->body());
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}
