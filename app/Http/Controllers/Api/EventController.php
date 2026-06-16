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
     * Helper om de query te filteren op:
     * 1. Datum (in de toekomst, of betaald in het verleden)
     * 2. Access Rules (Open, Private, Invite-only, Premium)
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
                $q->whereIn('access', ['open', 'private'])
                    ->orWhereNull('access');

                // Als er een user is ingelogd, controleren we de speciale permissies
                if ($user) {
                    $isUserPremium = $user->is_premium ?? false;

                    // 2. De host mag natuurlijk altijd zijn eigen event zien
                    $q->orWhere('user_id', $user->id);

                    // 3. Premium events
                    if ($isUserPremium) {
                        $q->orWhere('access', 'premium');
                    }

                    // 4. Invite-only events (alleen als user de juiste status heeft)
                    $q->orWhere(function ($inviteCheck) use ($user) {
                        $inviteCheck->where('access', 'invite')
                            ->whereHas('requests', function ($reqQuery) use ($user) {
                                $reqQuery->where('user_id', $user->id)
                                    ->whereIn('status', ['pending_invite', 'accepted']);
                            });
                    });
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

        $eventDate = Carbon::parse($event->date);
        $isPastEvent = $eventDate->isPast() && !$eventDate->isToday();

        if (!$event->is_active || ($isPastEvent && !$hasPaid)) {
            return response()->json([
                'message' => 'Dit event is afgelopen en alleen toegankelijk voor bezoekers.'
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

        // Als je niet bent ingelogd, is je "For You" lijst leeg. Dit klopt!
        if (!$user) {
            return response()->json([]);
        }

        $followingIds = $user->following()->allRelatedIds()->toArray();
        $lat = $request->lat;
        $lng = $request->lng;

        // FYP Query: Kijkt nu ALLEEN nog maar naar "Waarom staat dit op je feed?"
        // De permissies (open, invite, premium) worden verderop door de helper afgehandeld.
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

        // Afstand berekenen en sorteren
        if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
            $query->selectRaw("
            *,
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(latitude))
            )) AS distance
        ", [$lat, $lng, $lat])
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

        // Sanctum checkt de user (als die is ingelogd) of blijft null (als die is uitgelogd)
        $user = $request->user() ?? auth('sanctum')->user();

        $query = Pop::query();

        // Door de geüpdatete helper worden Premium en Invite-only hier nu AUTOMATISCH verborgen
        // voor onbevoegden (en niet-ingelogde gebruikers)!
        $query = $this->applyEventVisibility($query, $user);

        $pops = $query->with(['user' => function($query) {
            $query->select('id', 'name', 'username', 'profile_image');
        }])
            ->selectRaw("
            *,
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(latitude))
            )) AS distance
        ", [$lat, $lng, $lat])
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
            'is_ticketed' => 'nullable|boolean',
            'ticket_price' => 'nullable|numeric',
        ]);

        if ($request->hasFile('images')) {
            if (!empty($pop->images)) {
                foreach ($pop->images as $oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $storedPaths = [];
            foreach ($request->file('images') as $file) {
                $path = $file->store('pops', 'public');
                $storedPaths[] = $path;
            }
            $validated['images'] = $storedPaths;
        }

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
                foreach ($pop->images as $path) {
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
