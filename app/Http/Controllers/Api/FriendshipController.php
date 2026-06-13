<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    /**
     * 1. Haal de lijst met geaccepteerde vrienden op (Startscherm van de app)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Haal alle vriendschappen op die geaccepteerd zijn en waar de ingelogde gebruiker onderdeel van is
        $friendships = Friendship::where('status', 'accepted')
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id);
            })
            ->get();

        // Transformeer de data zodat we de gegevens van de VRIEND teruggeven, niet van onszelf
        $friends = $friendships->map(function($friendship) use ($user) {
            // Als jij de 'user_id' bent, is de ander 'friend_id'. En vice versa.
            $friendId = ($friendship->user_id === $user->id) ? $friendship->friend_id : $friendship->user_id;

            $friendUser = User::find($friendId);

            if (!$friendUser) {
                return null;
            }

            return [
                'id' => $friendUser->id,
                'name' => $friendUser->name,
                'username' => $friendUser->username,
                'profile_image' => $friendUser->profile_image, // 📸 TOEGEVOEGD
                'friendship_status' => 'accepted'
            ];
        })->filter(); // filter() verwijdert eventuele null-waarden

        return response()->json(['friends' => array_values($friends->toArray())]);
    }

    /**
     * Invite a specific user to a Pop
     */
    public function inviteGuest(Request $request, $id)
    {
        $host = $request->user();
        $invitedUserId = $request->input('user_id');

        // Check of de gebruiker niet toevallig zichzelf uitnodigt
        if ($host->id == $invitedUserId) {
            return response()->json(['message' => 'Je kunt jezelf niet uitnodigen.'], 400);
        }

        // Check of er al een aanvraag of uitnodiging bestaat voor deze gebruiker en deze pop
        // Gebruik hier de naam van jouw model (bijv. EventRequest of PopRequest)
        $existingRequest = \App\Models\PopRequest::where('pop_id', $id)
            ->where('user_id', $invitedUserId)
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'Deze gebruiker staat al op de lijst of heeft al een aanvraag lopen.'], 400);
        }

        // Maak de uitnodiging aan. We zetten de status op 'accepted' zodat
        // de uitgenodigde persoon direct op de gastenlijst (Guestlist tab) staat.
        \App\Models\PopRequest::create([
            'pop_id' => $id,
            'user_id' => $invitedUserId,
            'status' => 'accepted' // Of 'pending_invite' als de gast nog moet accepteren
        ]);

        return response()->json(['success' => true, 'message' => 'Uitnodiging verstuurd!']);
    }

    /**
     * 2. Zoek naar gebruikers om toe te voegen (en zie de status van je vriendschap)
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $query = $request->input('q');

        if (blank($query)) {
            return response()->json(['users' => []]);
        }

        // Zoek gebruikers op naam of username, behalve jezelf
        $users = User::where('id', '!=', $user->id)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('username', 'LIKE', "%{$query}%");
            })
            ->select('id', 'name', 'username', 'profile_image') // 📸 TOEGEVOEGD
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
                        // Kijken wie de zender was
                        $status = ($friendship->user_id === $user->id) ? 'pending_sent' : 'pending_received';
                    }
                }

                return [
                    'id' => $searchedUser->id,
                    'name' => $searchedUser->name,
                    'username' => $searchedUser->username,
                    'profile_image' => $searchedUser->profile_image, // 📸 TOEGEVOEGD
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

        // Check of er al een relatie of verzoek bestaat
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
            // 📸 profile_image toegevoegd in de selectie via de relatie
            ->with('sender:id,name,username,profile_image')
            ->get()
            ->map(function($friendship) {
                return [
                    'id' => $friendship->id,
                    'sender_id' => $friendship->user_id,
                    'name' => $friendship->sender->name ?? 'Onbekende Gebruiker',
                    'username' => $friendship->sender->username ?? 'gebruiker',
                    'profile_image' => $friendship->sender->profile_image ?? null, // 📸 TOEGEVOEGD
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

        // Zoek de vriendschap op (ongeacht wie de zender of ontvanger was)
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
