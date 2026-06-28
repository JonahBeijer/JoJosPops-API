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
        $message = $request->input('message');
        $chatId = $request->input('chat_id');
        $chatName = $request->input('chat_name'); // 🔥 Pakt nu de juiste naam (Groepsnaam of Gebruikersnaam)
        $receiverIds = $request->input('receivers');

        if (empty($receiverIds)) {
            return response()->json(['success' => false, 'message' => 'Geen ontvangers opgegeven'], 400);
        }

        // Haal alle gebruikers op die in deze chat zitten (behalve de afzender)
        $usersToNotify = User::whereIn('id', $receiverIds)
            ->whereNotNull('device_token')
            ->get();

        foreach ($usersToNotify as $user) {
            $this->sendPushNotification(
                $user->device_token,
                $chatName,     // Titel = De naam die React Native meestuurt
                $message,      // Body = Het getypte bericht
                ['type' => 'chat', 'chat_id' => $chatId] // Data voor je Expo Router
            );
        }

        return response()->json(['success' => true]);
    }
}
