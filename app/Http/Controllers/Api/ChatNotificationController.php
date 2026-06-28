<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatNotificationController extends Controller
{
    private function sendPushNotification($token, $title, $body, $data = [])
    {
        if (!$token) return;

        Http::post('https://exp.host/--/api/v2/push/send', [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ]);
    }

    public function notify(Request $request)
    {
        $sender = $request->user();
        $message = $request->input('message');
        $chatId = $request->input('chat_id');
        $chatName = $request->input('chat_name');
        $senderAvatar = $sender->profile_image; // De pad-string
        $receiverIds = $request->input('receivers');

        $usersToNotify = User::whereIn('id', $receiverIds)->whereNotNull('device_token')->get();

        foreach ($usersToNotify as $user) {
            // We sturen geen 'title' of 'body' via de Expo server,
            // maar pure 'data'. De app doet de rest.
            Http::post('https://exp.host/--/api/v2/push/send', [
                'to' => $user->device_token,
                'data' => [
                    'type' => 'chat',
                    'chat_id' => $chatId,
                    'title' => $chatName,
                    'body' => $message,
                    'avatar_url' => $senderAvatar, // Stuur de path string mee
                ],
            ]);
        }
        return response()->json(['success' => true]);
    }
}
