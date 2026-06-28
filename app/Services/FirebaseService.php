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

        $client->setAuthConfig(
            storage_path('app/firebase/firebase-service-account.json')
        );

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
