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
        $receiverIds = $request->input('receivers');

        // Veilig de avatar URLs opbouwen (voorkomt dubbele 'http://...' nesting)
        $avatarUrl = null;
        if (!empty($sender->profile_image)) {
            $avatarUrl = filter_var($sender->profile_image, FILTER_VALIDATE_URL)
                ? $sender->profile_image
                : url("/api/pops/image?path=" . urlencode($sender->profile_image));
        }

        $groupAvatarUrl = null;
        if (!empty($groupAvatar)) {
            $groupAvatarUrl = filter_var($groupAvatar, FILTER_VALIDATE_URL)
                ? $groupAvatar
                : url("/api/pops/image?path=" . urlencode($groupAvatar));
        }

        $usersToNotify = User::whereIn('id', $receiverIds)
            ->whereNotNull('device_token')
            ->get();

        foreach ($usersToNotify as $user) {
            try {
                $cleanToken = trim($user->device_token);
                if (empty($cleanToken)) continue;

                $payload = [
                    'title'        => (string) $chatName,
                    'body'         => (string) $message,
                    'type'         => 'chat',
                    'chat_id'      => (string) $chatId,
                    'sender_id'    => (string) $sender->id,
                    'sender_name'  => (string) $sender->name,
                    'avatar_url'   => (string) $avatarUrl,
                    'is_group'     => $isGroup ? 'true' : 'false',
                    'group_name'   => (string) $chatName,
                    'group_avatar' => (string) $groupAvatarUrl,
                ];

                $this->firebase->send($cleanToken, $payload);
            } catch (\Exception $e) {
                Log::error("Fout bij verzenden push notificatie naar User ID {$user->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notificaties succesvol verwerkt.'
        ]);
    }
}
