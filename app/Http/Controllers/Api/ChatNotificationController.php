<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatNotificationController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase
    ) {}

    public function notify(Request $request)
    {
        // 1. Valideer de inkomende data
        $request->validate([
            'receivers' => 'required|array',
            'message'   => 'required|string',
            'chat_id'   => 'required',
            'chat_name' => 'required|string',
            'is_group'  => 'boolean',
            'group_avatar' => 'nullable|string'
        ]);

        $sender = $request->user();
        $message = $request->input('message');
        $chatId = $request->input('chat_id');
        $chatName = $request->input('chat_name');

        // Groeps-specifieke data
        $isGroup = $request->input('is_group', false);
        $groupAvatar = $request->input('group_avatar');

        $senderAvatar = $sender->profile_image;
        $receiverIds = $request->input('receivers');

        $avatarUrl = $senderAvatar
            ? url("/api/pops/image?path=" . urlencode($senderAvatar))
            : null;

        // Maak de avatar URL voor de groep ook publiek toegankelijk (als deze bestaat)
        $groupAvatarUrl = $groupAvatar
            ? url("/api/pops/image?path=" . urlencode($groupAvatar))
            : null;

        $usersToNotify = User::whereIn('id', $receiverIds)
            ->whereNotNull('device_token')
            ->get();

        foreach ($usersToNotify as $user) {
            try {
                $cleanToken = trim($user->device_token);
                if (empty($cleanToken)) continue;

                // 🔥 Hier voegen we de groep-data toe aan de payload
                // In ChatNotificationController.php (rond regel 54)
                $payload = [
                    'title'        => (string) $chatName,
                    'body'         => (string) $message,
                    'type'         => 'chat',
                    'chat_id'      => (string) $chatId,
                    'sender_id'    => (string) $sender->id, // 🟢 VOEG DEZE TOE
                    'sender_name'  => (string) $sender->name,
                    'avatar_url'   => (string) $avatarUrl,
                    'is_group'     => $isGroup ? 'true' : 'false',
                    'group_name'   => (string) $chatName,
                    'group_avatar' => (string) $groupAvatarUrl,
                ];

                $this->firebase->send($cleanToken, $payload);
            } catch (\Exception $e) {
                \Log::error("Fout bij verzenden push notificatie naar User ID {$user->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notificaties succesvol verwerkt.'
        ]);
    }
}
