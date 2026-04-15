<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pop; // Gebruik je Pop model
use Illuminate\Http\Request;
use Carbon\Carbon;

class EventController extends Controller
{
    // 1. Haal alle pops op voor de feed
    public function index()
    {
        return response()->json(Pop::all());
    }

    // 2. Toon één specifieke pop (Detail pagina)
    public function show($id)
    {
        $event = Pop::findOrFail($id);

        // Check of de reveal_time al is geweest
        // We casten reveal_time naar een Carbon object voor de vergelijking
        $revealTime = Carbon::parse($event->reveal_time);
        $isRevealed = now()->gt($revealTime);

        if (!$isRevealed) {
            $event->location = "Locatie wordt onthuld om " . $revealTime->format('H:i');
        }

        return response()->json([
            'event' => $event,
            'is_revealed' => $isRevealed
        ]);
    }

    // 3. Maak een nieuwe pop aan
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'neighbourhood' => 'required|string',
            'location'      => 'required|string',
            'genre'         => 'required|in:Meet-up,Cars,Stores,Bush-Party,Raves',
            'date'          => 'required|date',
            'time'          => 'required',
            'access'        => 'required|in:Public,Private,Invite-only',
            'event_type'    => 'required|in:Official,Unofficial',
            'reveal_time'   => 'nullable|date',
            'images'        => 'nullable|array', // Vergeet deze niet voor je foto's!
        ]);

        // Forceer een waarde voor reveal_time als deze leeg is
        $validated['reveal_time'] = $validated['reveal_time'] ?? now();

        $event = Pop::create($validated);

        return response()->json([
            'message' => 'Pop-up opgeslagen in pops 🎉',
            'event' => $event
        ], 201);
    }
}
