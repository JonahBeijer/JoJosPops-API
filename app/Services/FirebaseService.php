<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected string $projectId = 'jojo-s-pops-3758e';

    protected function getAccessToken(): string
    {
        $client = new Client();

        // 1. Bouw de credentials array veilig op vanuit de .env bestanden
        $credentials = [
            'type' => 'service_account',
            'project_id' => env('FIREBASE_PROJECT_ID'),
            // Zorg dat de letterlijke \n tekens worden omgezet naar echte line-breaks
            'private_key' => str_replace('\\n', "\n", env('FIREBASE_PRIVATE_KEY')),
            'client_email' => env('FIREBASE_CLIENT_EMAIL'),
            'client_id' => '100583925862199086313',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/' . urlencode(env('FIREBASE_CLIENT_EMAIL')),
            'universe_domain' => 'googleapis.com',
        ];

        // 2. Geef de array door aan de Google Client (in plaats van een pad naar een bestand)
        $client->setAuthConfig($credentials);

        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();

        if (!isset($token['access_token'])) {
            throw new \Exception('Unable to obtain Firebase access token.');
        }

        return $token['access_token'];
    }

    public function send(
        string $deviceToken,
        array $data
    ): array {

        $response = Http::withToken($this->getAccessToken())
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
                [
                    'message' => [

                        'token' => $deviceToken,

                        // Alleen DATA zodat Notifee alles afhandelt
                        'data' => $data,

                        'android' => [
                            'priority' => 'HIGH',
                        ],

                        'apns' => [
                            'headers' => [
                                'apns-priority' => '10',
                            ],
                        ],
                    ]
                ]
            );

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}
