<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatNotificationController extends Controller
{
    public function notify(Request $request)
    {
        $sender = $request->user();
        $message = $request->input('message');
        $chatId = $request->input('chat_id');
        $chatName = $request->input('chat_name');
        $senderAvatar = $sender->profile_image;
        $receiverIds = $request->input('receivers');

        $usersToNotify = User::whereIn('id', $receiverIds)->whereNotNull('device_token')->get();

        foreach ($usersToNotify as $user) {
            Http::post('https://exp.host/--/api/v2/push/send', [
                'to' => $user->device_token,

                // 1. ZICHTBARE DATA (Moet in de root staan!)
                'title' => $chatName,
                'body' => $message,
                'sound' => 'default',

                // 2. ANDROID KANAAL (Essentieel voor Android 8.0+)
                'channelId' => 'chat_messages',

                // 3. VERBORGEN DATA (Voor afhandeling bij een klik in React Native)
                'data' => [
                    'type' => 'chat',
                    'chat_id' => $chatId,
                    'avatar_url' => $senderAvatar,
                ],
            ]);
        }

        return response()->json(['success' => true]);
    }
}
