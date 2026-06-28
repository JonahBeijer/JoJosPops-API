<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class ChatNotificationController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase
    ) {}

    public function notify(Request $request)
    {
        $sender = $request->user();

        $message = $request->input('message');
        $chatId = $request->input('chat_id');
        $chatName = $request->input('chat_name');
        $senderAvatar = $sender->profile_image;
        $receiverIds = $request->input('receivers');

        $usersToNotify = User::whereIn('id', $receiverIds)
            ->whereNotNull('device_token')
            ->get();

        foreach ($usersToNotify as $user) {

            $this->firebase->send(
                $user->device_token,
                [
                    'title'       => $chatName,
                    'body'        => $message,
                    'type'        => 'chat',
                    'chat_id'     => (string) $chatId,
                    'sender_name' => $sender->name,
                    'avatar_url'  => $senderAvatar,
                ]
            );

        }

        return response()->json([
            'success' => true
        ]);
    }
}
