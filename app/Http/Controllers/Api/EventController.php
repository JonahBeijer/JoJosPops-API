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
    public function index()
    {
        $events = Pop::where('is_active', true)
            ->with(['user' => function($query) {
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

    public function show($id)
    {
        $event = Pop::where('is_active', true)
            ->with(['user' => function($query) {
                $query->select('id', 'name', 'username', 'profile_image');
            }])
            ->findOrFail($id);

        $revealTime = Carbon::parse($event->reveal_time);
        $isRevealed = now()->gt($revealTime);

        $rsvpStatus = 'none';
        $hasPaid = false;

        $user = auth()->user() ?? auth('sanctum')->user();

        if ($user) {
            // 🔄 NIEUW: Controleer of de ingelogde gebruiker de host van deze Pop volgt
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
            // Als er geen gebruiker is ingelogd, is is_following altijd false
            if ($event->user) {
                $event->user->is_following = false;
            }
        }

        return response()->json([
            'event' => $this->maskSensitiveData($event),
            'is_revealed' => $isRevealed,
            'rsvp_status' => $rsvpStatus,
            'has_paid' => $hasPaid
        ]);
    }
    public function fypFeed(Request $request)
    {
        $user = $request->user() ?? auth('sanctum')->user(); // Fallback voor de zekerheid

        // 1. Voorkom crash als de gebruiker niet is ingelogd
        if (!$user) {
            return response()->json([]);
        }

        // 2. Pluck 'id' (of 'users.id') in plaats van 'following_id'
        $followingIds = $user->following()->pluck('users.id')->toArray();

        // Als je nog niemand volgt, kunnen we direct een lege array teruggeven om een query te besparen
        if (empty($followingIds)) {
            return response()->json([]);
        }

        $lat = $request->lat;
        $lng = $request->lng;

        $query = Pop::where('is_active', true)
            ->whereIn('user_id', $followingIds) // Alleen van gevolgde accounts
            ->with(['user' => function($q) {
                $q->select('id', 'name', 'username', 'profile_image');
            }]);

        if ($lat && $lng) {
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

    public function buyTicket(Request $request, $id)
    {
        $pop = Pop::where('is_active', true)
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

        // NIEUW
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

    public function nearby(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius ?? 10;

        $pops = Pop::where('is_active', true)
            ->with(['user' => function($query) {
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



    private function maskSensitiveData($event)
    {
        $revealTime = Carbon::parse($event->reveal_time);

        if (now()->lt($revealTime)) {
            $event->location =
                "Location locked until " .
                $revealTime->format('H:i');
        } else {
            $event->location =
                $event->location ??
                $event->neighbourhood;
        }

        $urls = [];

        if (!empty($event->images)) {

            $imagesArray =
                is_array($event->images)
                    ? $event->images
                    : (json_decode($event->images, true)
                    ?? [$event->images]);

            foreach ($imagesArray as $path) {
                if ($path) {
                    $urls[] =
                        asset('storage/' . $path);
                }
            }
        }

        $event->image_urls = $urls;

        return $event;
    }
}
