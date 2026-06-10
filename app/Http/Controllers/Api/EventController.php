<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pop;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class EventController extends Controller
{
    public function index()
    {
        $events = Pop::with(['user' => function($query) {
            $query->select('id', 'name', 'username' , 'profile_image');
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
        $event = Pop::with(['user' => function($query) {
            $query->select('id', 'name', 'username', 'profile_image');
        }])->findOrFail($id);

        $revealTime = Carbon::parse($event->reveal_time);
        $isRevealed = now()->gt($revealTime);

        return response()->json([
            'event' => $this->maskSensitiveData($event),
            'is_revealed' => $isRevealed
        ]);
    }

    /**
     * Ticket kopen via Stripe (Tijdelijk zonder Connect splitsing voor testen)
     */
    /**
     * Ticket kopen via Stripe met ondersteuning voor meerdere betaalmethoden
     */
    public function buyTicket(Request $request, $id)
    {
        // 1. Zoek de pop en laad de bijbehorende host (user) in
        $pop = Pop::with('user')->findOrFail($id);

        // 2. Check of het wel een betaald event is
        if (!$pop->is_ticketed || !$pop->ticket_price) {
            return response()->json(['message' => 'Dit event is gratis of heeft geen geldige prijs.'], 400);
        }

        // 3. Configureer Stripe met jouw geheime sleutel uit de .env file
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // 4. Reken de prijs om naar centen (Stripe vereist dit) en bereken de 15% commissie
        $ticketPriceInCents = (int) round($pop->ticket_price * 100);
        $applicationFeeInCents = (int) round($ticketPriceInCents * 0.15);

        try {
            // 5. Maak de Payment Intent aan bij Stripe met alle gewenste betaalmethoden
            $paymentIntent = PaymentIntent::create([
                'amount' => $ticketPriceInCents,
                'currency' => 'eur',

                // Alle ondersteunde betaalmethoden expliciet gedefinieerd volgens Stripe API standaarden:
                'payment_method_types' => [
                    'card',
                    'ideal',
                    'klarna',
                    'bancontact',
                    'satispay',
                    'amazon_pay',
                    'eps'
                ],

                // ⚠️ TIJDELIJK UITGEZET VOOR TESTEN (Voorkomt crash op missend stripe_account_id):
                // 'application_fee_amount' => $applicationFeeInCents,
                // 'transfer_data' => [
                //     'destination' => $pop->user->stripe_account_id,
                // ],
            ]);

            // 6. Stuur het secret terug naar de Expo app
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
            return response()->json(['message' => 'Unauthorized. You do not own this pop-up.'], 403);
        }

        $validated = $request->validate([
            'title'         => 'sometimes|required|string|max:255',
            'neighbourhood' => 'sometimes|required|string',
            'description'   => 'nullable|string',
            'location'      => 'nullable|string',
            'latitude'      => 'sometimes|required|numeric',
            'longitude'     => 'sometimes|required|numeric',
            'capacity'      => 'nullable|integer',
            'event_type'    => 'nullable|string',
            'date'          => 'nullable|date',
            'event_time'    => 'nullable|string',
            'access'        => 'nullable|string',
            'reveal_time'   => 'nullable|date',
            'images'        => 'nullable|array',
            'is_ticketed'   => 'nullable|boolean',
            'ticket_price'  => 'nullable|numeric',
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

        $pop->update($validated);

        return response()->json([
            'message' => 'Pop-up successfully updated! ✏️',
            'event' => $pop
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You do not own this pop-up.'], 403);
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

    /**
     * Nieuwe pop-up opslaan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'neighbourhood' => 'required|string',
            'description'   => 'nullable|string',
            'location'      => 'nullable|string',
            'latitude'      => 'required|numeric',
            'longitude'     => 'required|numeric',
            'capacity'      => 'nullable|integer',
            'event_type'    => 'nullable|string',
            'date'          => 'nullable|date',
            'event_time'    => 'nullable|string',
            'access'        => 'nullable|string',
            'reveal_time'   => 'nullable|date',
            'images'        => 'nullable|array',
            'is_ticketed'   => 'nullable|boolean',
            'ticket_price'  => 'nullable|numeric',
        ]);

        $validated['user_id'] = $request->user()->id;

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

        $pops = Pop::with(['user' => function($query) {
            $query->select('id', 'name', 'username' , 'profile_image');
        }])->selectRaw("
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

    /**
     * Helper om data af te schermen en publieke URL-arrays op te bouwen
     */
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
