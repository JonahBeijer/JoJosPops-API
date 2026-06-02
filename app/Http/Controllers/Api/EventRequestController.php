<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PopRequest;
use App\Models\Pop;
use Illuminate\Http\Request;

class EventRequestController extends Controller
{
    /**
     * 1. Zelf een request sturen (aangeroepen door je 'Request to join' knop)
     */
    public function storeRequest(Request $request, $popId)
    {
        $pop = Pop::findOrFail($popId);

        // Check of er al een request is of dat de gebruiker de host is
        $exists = PopRequest::where('user_id', $request->user()->id)
            ->where('pop_id', $popId)
            ->exists();

        if ($exists || $pop->user_id === $request->user()->id) {
            return response()->json(['message' => 'Already requested or host'], 400);
        }

        PopRequest::create([
            'user_id' => $request->user()->id,
            'pop_id' => $popId,
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Request sent!']);
    }

    /**
     * 2. Ophalen van álle binnenkomende aanvragen over alle events waarvan IK de host ben (Algemeen overzicht)
     */
    public function getPendingRequests(Request $request)
    {
        $userId = $request->user()->id;

        $requests = PopRequest::whereHas('pop', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('status', 'pending')
            ->with(['user:id,name,username,profile_image', 'pop:id,title'])
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'user_id' => $req->user_id,
                    'name' => $req->user->name,
                    'username' => $req->user->username,
                    'pop_title' => $req->pop->title,
                    'profile_image' => $req->user->profile_image
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    /**
     * 3. Ophalen van alle verzoeken (pending & accepted) voor één SPECIFIEKE pop-up (Manage Guests pagina)
     */
    public function getRequestsForPop(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        // BEVEILIGINGSCHECK: Alleen de host van deze specifieke pop-up mag dit inzien
        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You are not the host.'], 403);
        }

        // Haal alle requests op en koppel de usergegevens (id, name, username) eraan
        $requests = PopRequest::where('pop_id', $id)
            ->with(['user:id,name,username'])
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'status' => $req->status, // Geeft 'pending' of 'accepted' mee voor je frontend tabs
                    'user' => [
                        'id' => $req->user->id,
                        'name' => $req->user->name,
                        'username' => $req->user->username,
                    ]
                ];
            });

        return response()->json(['requests' => $requests], 200);
    }

    /**
     * 4. Accepteren van een request
     */
    public function acceptRequest($requestId)
    {
        $request = PopRequest::where('status', 'pending')->findOrFail($requestId);

        // Check of de ingelogde gebruiker wel de host is van dit event
        if ($request->pop->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Status updaten naar geaccepteerd
        $request->update(['status' => 'accepted']);

        // Gasten-teller van de pop-up verhogen met 1
        $request->pop->increment('current_guests');

        return response()->json(['message' => 'Accepted']);
    }

    /**
     * 5. Weigeren/Verwijderen van een join request
     */
    public function declineRequest(Request $request, $requestId)
    {
        $popRequest = PopRequest::findOrFail($requestId);
        $pop = Pop::findOrFail($popRequest->pop_id);

        // BEVEILIGINGSCHECK: Alleen de host mag dit verzoek weigeren
        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // We verwijderen de aanvraag uit de database zodat het verdwijnt uit de lijsten
        $popRequest->delete();

        return response()->json(['message' => 'Declined successfully'], 200);
    }
}
