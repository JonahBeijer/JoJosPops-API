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

        // 🔥 FIX 1: Check of de pop 'open' is en GEEN ticket heeft (Gratis open flow)
        $isOpenAndFree = (strtolower($pop->access) === 'open' && !$pop->is_ticketed);

        // Als het open & gratis is, mag de status direct naar 'accepted'
        $status = $isOpenAndFree ? 'accepted' : 'pending';

        PopRequest::create([
            'user_id' => $request->user()->id,
            'pop_id' => $popId,
            'status' => $status
        ]);

        // Als de bezoeker direct is geaccepteerd, verhoog direct de gastenlijst-teller
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

        // Haal een eventueel bestaand verzoek op (bijv. als ze eerst 'pending' waren)
        $popRequest = PopRequest::where('pop_id', $popId)
            ->where('user_id', $userId)
            ->first();

        // Check of ze niet stiekem al als 'paid' of 'accepted' in de boeken staan
        // Dit voorkomt dat de teller dubbel ophoogt als ze vaker op de knop drukken
        $alreadyCounted = $popRequest && in_array($popRequest->status, ['accepted', 'paid']);

        if (!$popRequest) {
            // Als er nog geen verzoek was (direct gekocht bij open pop), maak hem aan
            $popRequest = PopRequest::create([
                'pop_id' => $popId,
                'user_id' => $userId,
                'status' => 'paid'
            ]);
        } else {
            // Als er al een verzoek was, update de status naar 'paid'
            $popRequest->update(['status' => 'paid']);
        }

        // 🔥 FIX 2: Als ze nog niet meegeteld waren als actieve gast, verhoog de teller!
        if (!$alreadyCounted) {
            $pop->increment('current_guests');
        }

        return response()->json([
            'message' => 'Betaling succesvol verwerkt en toegevoegd aan gastenlijst!',
            'status' => $popRequest->status
        ]);
    }

    /**
     * 2. Ophalen van álle binnenkomende aanvragen over alle events waarvan IK de host ben (Algemeen overzicht)
     */
    /**
     * 2. Ophalen van alle binnenkomende aanvragen en directe aanmeldingen (Algemeen overzicht)
     */
    public function getPendingRequests(Request $request)
    {
        $userId = $request->user()->id;

        $requests = PopRequest::whereHas('pop', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            // 🔥 NU: Haal zowel pending (verzoeken) als accepted/paid (directe aanmeldingen) op
            ->whereIn('status', ['pending', 'accepted', 'paid'])
            ->with(['user:id,name,username,profile_image', 'pop:id,title'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'user_id' => $req->user_id,
                    'name' => $req->user->name,
                    'username' => $req->user->username,
                    'pop_title' => $req->pop->title,
                    'profile_image' => $req->user->profile_image,
                    'status' => $req->status // 🔥 Cruciaal: stuur de status mee naar de frontend!
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    public function inviteGuest(Request $request, $id)
    {
        $host = $request->user();
        $invitedUserId = $request->input('user_id');

        // Check of de gebruiker niet toevallig zichzelf uitnodigt
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

        // Beveiliging: Alleen de host of de uitgenodigde persoon zelf mag accepteren
        if ($request->pop->user_id !== auth()->id() && $request->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 🔥 FIX: Update status en verhoog de gasten-teller alleen als het nog niet geaccepteerd was
        if ($request->status !== 'accepted' && $request->status !== 'paid') {
            $request->update(['status' => 'accepted']);
            $request->pop->increment('current_guests');
        }

        return response()->json(['message' => 'Geaccepteerd']);
    }
    /**
     * 3. Ophalen van alle verzoeken (pending, accepted & paid) voor één SPECIFIEKE pop-up
     */
    public function getRequestsForPop(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        // BEVEILIGINGSCHECK: Alleen de host van deze specifieke pop-up mag dit inzien
        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You are not the host.'], 403);
        }

        // Haal alle requests op en koppel de usergegevens eraan
        $requests = PopRequest::where('pop_id', $id)
            ->with(['user:id,name,username,profile_image'])
            ->get()
            ->map(function ($req) {
                // Mapping voor de frontend tabs
                return [
                    'id' => $req->id,
                    'status' => in_array($req->status, ['accepted', 'paid']) ? 'accepted' : 'pending',
                    'original_status' => $req->status, // Handig om te zien of het een invite of request was
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

        // BEVEILIGINGSCHECK: Alleen de host mag dit verzoek weigeren of iemand verwijderen
        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 🔥 FIX: Als de persoon die we verwijderen de status 'accepted' of 'paid' had,
        // dan moeten we de gasten-teller van de pop-up met 1 VERLAGEN.
        if (in_array($popRequest->status, ['accepted', 'paid'])) {
            if ($pop->current_guests > 0) {
                $pop->decrement('current_guests');
            }
        }

        // We verwijderen de aanvraag/ticket-reservering uit de database
        $popRequest->delete();

        return response()->json([
            'message' => 'Gebruiker succesvol van de gastenlijst verwijderd en teller bijgewerkt.',
            'current_guests' => $pop->fresh()->current_guests // Stuur de nieuwe teller mee terug
        ], 200);
    }



}
