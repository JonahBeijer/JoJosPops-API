<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PopRequest;
use App\Models\Pop;
use Illuminate\Http\Request;

class EventRequestController extends Controller
{
    /**
     * 1. Zelf een request sturen óf een openstaande invite accepteren
     */
    public function storeRequest(Request $request, $popId)
    {
        $pop = Pop::findOrFail($popId);
        $userId = $request->user()->id;

        if ($pop->user_id === $userId) {
            return response()->json(['message' => 'You are the host'], 400);
        }

        $existingRequest = PopRequest::where('user_id', $userId)
            ->where('pop_id', $popId)
            ->first();

        // 🔥 FIX 1: Als de gebruiker al een invite heeft gekregen en op 'Join' drukt, accepteren we hem!
        if ($existingRequest) {
            if ($existingRequest->status === 'pending_invite') {
                $existingRequest->update(['status' => 'accepted']);
                $pop->increment('current_guests');

                return response()->json([
                    'message' => 'Uitnodiging geaccepteerd! 🎉',
                    'status' => 'accepted'
                ]);
            }
            return response()->json(['message' => 'Already requested or accepted'], 400);
        }

        // Check of de pop 'open' is en GEEN ticket heeft
        $isOpenAndFree = (!empty($pop->access) && strtolower($pop->access) === 'open' && !$pop->is_ticketed);

        $status = $isOpenAndFree ? 'accepted' : 'pending';

        PopRequest::create([
            'user_id' => $userId,
            'pop_id' => $popId,
            'status' => $status
        ]);

        if ($isOpenAndFree) {
            $pop->increment('current_guests');
            return response()->json([
                'message' => 'Direct toegelaten tot dit open evenement! 🎉',
                'status' => 'accepted'
            ]);
        }

        return response()->json([
            'message' => 'Request sent!',
            'status' => 'pending'
        ]);
    }

    /**
     * Bevestigen van de betaling (Ticket flow)
     */
    public function confirmPayment(Request $request, $popId)
    {
        $userId = $request->user()->id;
        $pop = Pop::findOrFail($popId);

        $popRequest = PopRequest::where('pop_id', $popId)
            ->where('user_id', $userId)
            ->first();

        $alreadyCounted = $popRequest && in_array($popRequest->status, ['accepted', 'paid']);

        if (!$popRequest) {
            $popRequest = PopRequest::create([
                'pop_id' => $popId,
                'user_id' => $userId,
                'status' => 'paid'
            ]);
        } else {
            $popRequest->update(['status' => 'paid']);
        }

        if (!$alreadyCounted) {
            $pop->increment('current_guests');
        }

        return response()->json([
            'message' => 'Betaling succesvol verwerkt en toegevoegd aan gastenlijst!',
            'status' => $popRequest->status
        ]);
    }

    /**
     * 2. Ophalen van alle binnenkomende aanvragen en directe aanmeldingen (Algemeen overzicht)
     */
    public function getPendingRequests(Request $request)
    {
        $userId = $request->user()->id;

        $requests = PopRequest::whereHas('pop', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->whereIn('status', ['pending', 'accepted', 'paid'])
            ->with(['user:id,name,username,profile_image', 'pop:id,title'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'pop_id' => $req->pop_id,
                    'user_id' => $req->user_id,
                    'name' => $req->user->name,
                    'username' => $req->user->username,
                    'pop_title' => $req->pop->title,
                    'profile_image' => $req->user->profile_image,
                    'status' => $req->status
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    public function inviteGuest(Request $request, $id)
    {
        $host = $request->user();
        $invitedUserId = $request->input('user_id');

        \App\Models\PopRequest::create([
            'pop_id' => $id,
            'user_id' => $invitedUserId,
            'status' => 'pending_invite'
        ]);

        return response()->json(['success' => true, 'message' => 'Uitnodiging verstuurd!']);
    }

    public function acceptRequest($requestId)
    {
        $request = PopRequest::findOrFail($requestId);

        if ($request->pop->user_id !== auth()->id() && $request->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->status !== 'accepted' && $request->status !== 'paid') {
            $request->update(['status' => 'accepted']);
            $request->pop->increment('current_guests');
        }

        // 🔥 FIX 2: Frontend heeft de nieuwe status nodig om de UI te updaten
        return response()->json([
            'message' => 'Geaccepteerd',
            'status' => 'accepted'
        ]);
    }

    /**
     * 3. Ophalen van alle verzoeken voor één SPECIFIEKE pop-up
     */
    public function getRequestsForPop(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You are not the host.'], 403);
        }

        $requests = PopRequest::where('pop_id', $id)
            ->with(['user:id,name,username,profile_image'])
            ->get()
            ->map(function ($req) {
                $frontendStatus = 'pending';
                if (in_array($req->status, ['accepted', 'paid'])) {
                    $frontendStatus = 'accepted';
                } elseif ($req->status === 'pending_invite') {
                    $frontendStatus = 'invited';
                }

                return [
                    'id' => $req->id,
                    'status' => $frontendStatus,
                    'original_status' => $req->status,
                    'user' => [
                        'id' => $req->user->id,
                        'name' => $req->user->name,
                        'username' => $req->user->username,
                        'profile_image' => $req->user->profile_image,
                    ]
                ];
            });

        return response()->json(['requests' => $requests], 200);
    }

    /**
     * 5. Weigeren van een verzoek óf een bestaande gast/ticket-koper VERWIJDEREN
     */
    public function declineRequest(Request $request, $requestId)
    {
        $popRequest = PopRequest::findOrFail($requestId);
        $pop = Pop::findOrFail($popRequest->pop_id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (in_array($popRequest->status, ['accepted', 'paid'])) {
            if ($pop->current_guests > 0) {
                $pop->decrement('current_guests');
            }
        }

        $popRequest->delete();

        return response()->json([
            'message' => 'Gebruiker succesvol van de gastenlijst verwijderd en teller bijgewerkt.',
            'current_guests' => $pop->fresh()->current_guests
        ], 200);
    }

    /**
     * Ophalen van uitnodigingen die JIJ hebt ontvangen van een host
     */
    public function getUserInvites(Request $request)
    {
        $userId = $request->user()->id;

        $invites = PopRequest::where('user_id', $userId)
            ->where('status', 'pending_invite')
            ->whereHas('pop.user')
            ->with(['pop.user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'pop_id' => $req->pop_id,
                    'user_id' => $req->pop->user_id,
                    'name' => $req->pop->user->name,
                    'username' => $req->pop->user->username,
                    'pop_title' => $req->pop->title,
                    'profile_image' => $req->pop->user->profile_image,
                    'status' => $req->status
                ];
            });

        return response()->json(['invites' => $invites]);
    }
}
