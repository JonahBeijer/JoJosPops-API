<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pop;
use App\Models\PopRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class EventController extends Controller
{
    /**
     * Veilige helper om de query te filteren op:
     * 1. Datum (in de toekomst, of betaald in het verleden)
     * 2. Strakke Access Rules (Open, Private, Invite-only, Premium)
     */
    private function applyEventVisibility($query, $user = null, $upcomingOnly = false)
    {
        return $query->where('is_active', true)

            // --- DEEL 1: DATUM CHECK ---
            ->where(function ($q) use ($user, $upcomingOnly) {
                // Regel A: Event is in de toekomst (of vandaag)
                $q->where('date', '>=', now()->toDateString());

                // Regel B: Event is al geweest, maar user heeft betaald
                if ($user && !$upcomingOnly) {
                    $q->orWhere(function ($subQ) use ($user) {
                        $subQ->where('date', '<', now()->toDateString())
                            ->whereHas('requests', function ($requestQuery) use ($user) {
                                $requestQuery->where('user_id', $user->id)
                                    ->whereIn('status', ['paid', 'accepted']);
                            });
                    });
                }
            })

            // --- DEEL 2: ACCESS CHECK (Beveiligt nu FYP, Index én Nearby!) ---
            ->where(function ($q) use ($user) {
                // 1. Iedereen (ook niet-ingelogd) mag Open en Private events zien
                $q->whereIn('access', ['open', 'private', 'Open', 'Private'])
                    ->orWhereNull('access');

                if ($user) {
                    // 2. Host mag altijd zijn eigen event zien
                    $q->orWhere('user_id', $user->id);

                    // 3. 🔥 DE VIP PASS: Als je een invite hebt gekregen (of al geaccepteerd/betaald hebt),
                    // overruled dit ALLES en mag je de pop altijd op Home en Nearby zien!
                    $q->orWhereHas('requests', function ($reqQuery) use ($user) {
                        $reqQuery->where('user_id', $user->id)
                            ->whereIn('status', ['pending_invite', 'accepted', 'paid']);
                    });

                    // 4. Premium check
                    if (isset($user->is_premium) && $user->is_premium) {
                        $q->orWhereIn('access', ['premium', 'Premium']);
                    }
                }
            });

    }

    public function index(Request $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();

        $query = Pop::query();
        $query = $this->applyEventVisibility($query, $user);

        $events = $query->with(['user' => function($query) {
            $query->select('id', 'name', 'username', 'profile_image');
        }])
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($event) {
                return $this->maskSensitiveData($event);
            })
            ->all();

        return response()->json($events);
    }

    public function show(Request $request, $id)
    {
        $event = Pop::with(['user' => function($query) {
            $query->select('id', 'name', 'username', 'profile_image');
        }])
            ->findOrFail($id);

        $user = $request->user() ?? auth('sanctum')->user();

        $rsvpStatus = 'none';
        $hasPaid = false;

        if ($user) {
            if ($event->user) {
                $event->user->is_following = $user->following()
                    ->where('following_id', $event->user->id)
                    ->exists();
            }

            $popRequest = PopRequest::where('pop_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            if ($popRequest) {
                $rsvpStatus = $popRequest->status;
                if ($rsvpStatus === 'paid') {
                    $hasPaid = true;
                }
            }
        } else {
            if ($event->user) {
                $event->user->is_following = false;
            }
        }

        // We checken alleen nog of het event inactief/verwijderd is.
        // Oude events mogen nu gewoon bekeken worden!
        if (!$event->is_active) {
            return response()->json([
                'message' => 'Dit event is niet meer actief.'
            ], 403);
        }

        $revealTime = Carbon::parse($event->reveal_time);
        $isRevealed = now()->gt($revealTime);

        $event->user_rsvp_status = $rsvpStatus;

        return response()->json([
            'event' => $this->maskSensitiveData($event),
            'is_revealed' => $isRevealed,
            'rsvp_status' => $rsvpStatus,
            'has_paid' => $hasPaid
        ]);
    }

    public function fypFeed(Request $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();

        if (!$user) {
            return response()->json([]);
        }

        $followingIds = $user->following()->allRelatedIds()->toArray();
        $lat = $request->lat;
        $lng = $request->lng;

        $query = Pop::query()
            ->where(function ($q) use ($followingIds, $user) {
                // A. Je volgt de host
                if (!empty($followingIds)) {
                    $q->whereIn('user_id', $followingIds);
                } else {
                    $q->whereRaw('0 = 1');
                }

                // B. OF je hebt een connectie met dit event
                $q->orWhereHas('requests', function ($reqQuery) use ($user) {
                    $reqQuery->where('user_id', $user->id);
                });
            })
            ->with(['user' => function($q) {
                $q->select('id', 'name', 'username', 'profile_image');
            }]);

        // Hier filteren we automatisch de verboden invites en premium events weg
        $query = $this->applyEventVisibility($query, $user, true);

        // Afstand berekening
        if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
            $query->select('*')
                ->selectRaw("(6371 * acos( cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)) )) AS distance", [$lat, $lng, $lat])
                ->orderBy('distance', 'asc');
        } else {
            $query->orderBy('date', 'asc');
        }

        $events = $query->get()->map(function($event) {
            return $this->maskSensitiveData($event);
        });

        return response()->json($events);
    }

    public function nearby(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius ?? 10;

        $user = $request->user() ?? auth('sanctum')->user();

        $query = Pop::query();

        // Regels voor Premium en Invite worden hier ook strak toegepast
        $query = $this->applyEventVisibility($query, $user);

        $pops = $query->select('*')
            ->with(['user' => function($query) {
                $query->select('id', 'name', 'username', 'profile_image');
            }])
            ->selectRaw("(6371 * acos( cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)) )) AS distance", [$lat, $lng, $lat])
            ->having("distance", "<", $radius)
            ->orderBy("distance")
            ->get()
            ->map(function($event) {
                return $this->maskSensitiveData($event);
            })
            ->all();

        return response()->json($pops);
    }

    public function buyTicket(Request $request, $id)
    {
        $pop = Pop::where('is_active', true)
            ->where('date', '>=', now()->toDateString())
            ->with('user')
            ->findOrFail($id);

        if (!$pop->is_ticketed || !$pop->ticket_price) {
            return response()->json([
                'message' => 'Dit event is gratis of heeft geen geldige prijs.'
            ], 400);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $ticketPriceInCents = (int) round($pop->ticket_price * 100);
        $applicationFeeInCents = (int) round($ticketPriceInCents * 0.15);

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $ticketPriceInCents,
                'currency' => 'eur',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return response()->json([
                'paymentIntent' => $paymentIntent->client_secret
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fout bij het opzetten van de betaling.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You do not own this pop-up.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'neighbourhood' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
            'capacity' => 'nullable|integer',
            'event_type' => 'nullable|string',
            'date' => 'nullable|date',
            'event_time' => 'nullable|string',
            'access' => 'nullable|string',
            'reveal_time' => 'nullable|date',
            'images' => 'nullable|array',
            'kept_images' => 'nullable|array',
            'is_ticketed' => 'nullable|boolean',
            'ticket_price' => 'nullable|numeric',
            'has_first_aider' => 'nullable|boolean', // 👈 Nieuw
            'has_security' => 'nullable|boolean',    // 👈 Nieuw
        ]);

        // 1. Haal de oude foto's op (als array)
        $oldImages = is_array($pop->images) ? $pop->images : (json_decode($pop->images, true) ?? []);

        // 2. Haal de foto's op die de gebruiker wil BEHOUDEN (gestuurd vanuit frontend)
        $keptImages = $request->input('kept_images', []);

        // 3. Wat niet in keptImages zit, moet VERWIJDERD worden
        $imagesToDelete = array_diff($oldImages, $keptImages);
        foreach ($imagesToDelete as $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        // 4. Start de definitieve lijst met de foto's die we behouden
        $finalImages = $keptImages;

        // 5. Upload gloednieuwe foto's
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('pops', 'public');
                $finalImages[] = $path;
            }
        }

        $validated['images'] = $finalImages;
        unset($validated['kept_images']); // Verwijder uit validatie array voor we updaten

        if (isset($validated['access'])) {
            $validated['is_active'] = $pop->is_active ?? true;
        }

        $pop->update($validated);

        return response()->json([
            'message' => 'Pop-up successfully updated! ✏️',
            'event' => $pop
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You do not own this pop-up.'
            ], 403);
        }

        try {
            if (!empty($pop->images)) {
                $imagesArray = is_array($pop->images) ? $pop->images : (json_decode($pop->images, true) ?? []);
                foreach ($imagesArray as $path) {
                    Storage::disk('public')->delete($path);
                }
            }

            $pop->delete();

            return response()->json([
                'message' => 'Pop-up successfully deleted! 🗑️'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete pop-up.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'neighbourhood' => 'required|string',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'capacity' => 'nullable|integer',
            'event_type' => 'nullable|string',
            'date' => 'nullable|date',
            'event_time' => 'nullable|string',
            'access' => 'nullable|string',
            'reveal_time' => 'nullable|date',
            'images' => 'nullable|array',
            'is_ticketed' => 'nullable|boolean',
            'ticket_price' => 'nullable|numeric',
            'has_first_aider' => 'nullable|boolean', // 👈 Nieuw
            'has_security' => 'nullable|boolean',    // 👈 Nieuw
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['is_active'] = true;

        if ($request->hasFile('images')) {
            $storedPaths = [];
            foreach ($request->file('images') as $file) {
                $path = $file->store('pops', 'public');
                $storedPaths[] = $path;
            }
            $validated['images'] = $storedPaths;
        }

        if (empty($validated['reveal_time'])) {
            $validated['reveal_time'] = now();
        }

        $event = Pop::create($validated);

        return response()->json([
            'message' => 'Pop-up successfully dropped! 🚀',
            'event' => $event
        ], 201);
    }

    private function maskSensitiveData($event)
    {
        $revealTime = Carbon::parse($event->reveal_time);

        if (now()->lt($revealTime)) {
            $event->location = "Location locked until " . $revealTime->format('H:i');
        } else {
            $event->location = $event->location ?? $event->neighbourhood;
        }

        $urls = [];
        if (!empty($event->images)) {
            $imagesArray = is_array($event->images)
                ? $event->images
                : (json_decode($event->images, true) ?? [$event->images]);

            foreach ($imagesArray as $path) {
                if ($path) {
                    $urls[] = asset('storage/' . $path);
                }
            }
        }
        $event->image_urls = $urls;

        return $event;
    }
}
