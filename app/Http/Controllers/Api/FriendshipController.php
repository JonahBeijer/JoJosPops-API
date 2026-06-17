<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FriendshipController extends Controller
{
    /**
     * Helper to send push notifications via Expo
     */
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

    /**
     * 1. Haal de lijst met geaccepteerde vrienden op (Startscherm van de app)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $friendships = Friendship::where('status', 'accepted')
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id);
            })
            ->get();

        $friends = $friendships->map(function($friendship) use ($user) {
            $friendId = ($friendship->user_id === $user->id) ? $friendship->friend_id : $friendship->user_id;
            $friendUser = User::find($friendId);

            if (!$friendUser) {
                return null;
            }

            return [
                'id' => $friendUser->id,
                'name' => $friendUser->name,
                'username' => $friendUser->username,
                'profile_image' => $friendUser->profile_image,
                'friendship_status' => 'accepted'
            ];
        })->filter();

        return response()->json(['friends' => array_values($friends->toArray())]);
    }

    /**
     * 2. Zoek naar gebruikers om toe te voegen (en zie de status van je vriendschap)
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $query = strtolower(ltrim($request->input('q'), '@'));

        if (blank($query)) {
            return response()->json(['users' => []]);
        }

        $users = User::where('id', '!=', $user->id)
            ->where(function($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$query}%"]);
            })
            ->select('id', 'name', 'username', 'profile_image')
            ->get()
            ->map(function ($searchedUser) use ($user) {
                $status = 'none';

                $friendship = Friendship::where(function($q) use ($user, $searchedUser) {
                    $q->where('user_id', $user->id)->where('friend_id', $searchedUser->id);
                })->orWhere(function($q) use ($user, $searchedUser) {
                    $q->where('user_id', $searchedUser->id)->where('friend_id', $user->id);
                })->first();

                if ($friendship) {
                    if ($friendship->status === 'accepted') {
                        $status = 'accepted';
                    } else {
                        $status = ($friendship->user_id === $user->id) ? 'pending_sent' : 'pending_received';
                    }
                }

                return [
                    'id' => $searchedUser->id,
                    'name' => $searchedUser->name,
                    'username' => $searchedUser->username,
                    'profile_image' => $searchedUser->profile_image,
                    'friendship_status' => $status
                ];
            });

        return response()->json(['users' => $users]);
    }

    /**
     * 3. Stuur een vriendschapsverzoek
     */
    public function sendRequest(Request $request)
    {
        $user = $request->user();
        $friendId = $request->input('friend_id');

        if ($user->id == $friendId) {
            return response()->json(['message' => 'Je kunt jezelf niet toevoegen.'], 400);
        }

        $exists = Friendship::where(function($q) use ($user, $friendId) {
            $q->where('user_id', $user->id)->where('friend_id', $friendId);
        })->orWhere(function($q) use ($user, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $user->id);
        })->exists();

        if ($exists) {
            return response()->json(['message' => 'Er is al een verzoek of vriendschap.'], 400);
        }

        Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $friendId,
            'status' => 'pending'
        ]);

        // 🔥 FIRE PUSH NOTIFICATION TO THE FRIEND
        $friendUser = User::find($friendId);
        $this->sendPushNotification(
            $friendUser->device_token ?? null,
            'New Friend Request 👋',
            "{$user->name} sent you a friend request.",
            ['type' => 'friend_request', 'user_id' => $user->id]
        );

        return response()->json(['success' => true, 'message' => 'Verzoek verzonden!']);
    }

    /**
     * 4. Accepteer een vriendschapsverzoek
     */
    public function acceptRequest(Request $request)
    {
        $user = $request->user();
        $senderId = $request->input('friend_id');

        $friendship = Friendship::where('user_id', $senderId)
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$friendship) {
            return response()->json(['message' => 'Geen openstaand verzoek gevonden.'], 404);
        }

        $friendship->update(['status' => 'accepted']);

        // 🔥 FIRE PUSH NOTIFICATION BACK TO SENDER
        $senderUser = User::find($senderId);
        $this->sendPushNotification(
            $senderUser->device_token ?? null,
            'Friend Request Accepted ✅',
            "{$user->name} accepted your friend request.",
            ['type' => 'friend_accept', 'user_id' => $user->id]
        );

        return response()->json(['success' => true, 'message' => 'Vriendschap geaccepteerd!']);
    }

    /**
     * 5. Haal alle openstaande vriendschapsverzoeken op
     */
    public function getPendingRequests(Request $request)
    {
        $user = $request->user();

        $requests = Friendship::where('friend_id', $user->id)
            ->where('status', 'pending')
            ->with('sender:id,name,username,profile_image')
            ->get()
            ->map(function($friendship) {
                return [
                    'id' => $friendship->id,
                    'sender_id' => $friendship->user_id,
                    'name' => $friendship->sender->name ?? 'Onbekende Gebruiker',
                    'username' => $friendship->sender->username ?? 'gebruiker',
                    'profile_image' => $friendship->sender->profile_image ?? null,
                    'time' => $friendship->created_at ? $friendship->created_at->diffForHumans() : null
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    /**
     * 6. Verwijder een vriendschap of annuleer een verzoek (Unfriend)
     */
    public function removeFriend(Request $request)
    {
        $user = $request->user();
        $friendId = $request->input('friend_id');

        $friendship = Friendship::where(function($q) use ($user, $friendId) {
            $q->where('user_id', $user->id)->where('friend_id', $friendId);
        })->orWhere(function($q) use ($user, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $user->id);
        })->first();

        if (!$friendship) {
            return response()->json(['message' => 'Geen actieve vriendschap of verzoek gevonden.'], 404);
        }

        $friendship->delete();

        return response()->json(['success' => true, 'message' => 'Vriendschap succesvol beëindigd.']);
    }
}
