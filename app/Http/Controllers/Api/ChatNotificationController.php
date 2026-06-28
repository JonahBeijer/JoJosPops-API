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
        // 1. Veiligheid eerst: valideer de inkomende data
        $request->validate([
            'receivers' => 'required|array',
            'message'   => 'required|string',
            'chat_id'   => 'required',
            'chat_name' => 'required|string'
        ]);

        $sender = $request->user();

        $message = $request->input('message');
        $chatId = $request->input('chat_id');
        $chatName = $request->input('chat_name');
        $senderAvatar = $sender->profile_image;
        $receiverIds = $request->input('receivers');

        // 2. 🔥 Zet de TransIP SFTP pad-string om in een publieke proxy URL voor Notifee
        $avatarUrl = $senderAvatar
            ? url("/api/pops/image?path=" . urlencode($senderAvatar))
            : null;

        // 3. Haal alleen de ontvangers op die push notificaties aan hebben staan (een token hebben)
        $usersToNotify = User::whereIn('id', $receiverIds)
            ->whereNotNull('device_token')
            ->get();

        // 4. Stuur de data-only payload via Firebase Cloud Messaging
        foreach ($usersToNotify as $user) {
            try {
                // 🔥 FIX: Knip onzichtbare spaties of enters weg van het token!
                $cleanToken = trim($user->device_token);

                // Controleer voor de zekerheid of het token na het trimmen niet leeg is
                if (empty($cleanToken)) {
                    continue;
                }

                $this->firebase->send(
                    $cleanToken, // Gebruik hier het schone token
                    [
                        'title'       => (string) $chatName,
                        'body'        => (string) $message,
                        'type'        => 'chat',
                        'chat_id'     => (string) $chatId,
                        'sender_name' => (string) $sender->name,
                        'avatar_url'  => (string) $avatarUrl,
                    ]
                );
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
