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
            $query->select('id', 'name', 'username');
        }])
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($event) {
                return $this->maskSensitiveData($event);
            })
            ->all(); // 👈 Omgezet naar pure array om JSON serialisatie-crashes te voorkomen

        return response()->json($events);
    }

    public function show($id)
    {
        $event = Pop::with(['user' => function($query) {
            $query->select('id', 'name', 'username');
        }])->findOrFail($id);

        $revealTime = Carbon::parse($event->reveal_time);
        $isRevealed = now()->gt($revealTime);

        return response()->json([
            'event' => $this->maskSensitiveData($event),
            'is_revealed' => $isRevealed
        ]);
    }

    /**
     * Ticket kopen via Stripe Connect (15% commissie voor JoJo's)
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
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        // 4. Reken de prijs om naar centen (Stripe vereist dit) en bereken de 15% commissie
        $ticketPriceInCents = (int) round($pop->ticket_price * 100);
        $applicationFeeInCents = (int) round($ticketPriceInCents * 0.15); // Jouw 15% winst!

        try {
            // 5. Maak de Payment Intent aan bij Stripe
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $ticketPriceInCents,
                'currency' => 'eur',
                'automatic_payment_methods' => ['enabled' => true],

                // 🔥 Jouw 15% die direct naar de JoJo's bankrekening gaat
                'application_fee_amount' => $applicationFeeInCents,

                // 💸 De overige 85% gaat naar de Stripe account van de maker
                'transfer_data' => [
                    // LET OP: Je moet zorgen dat de maker een gekoppeld Stripe ID in de database heeft
                    'destination' => $pop->user->stripe_account_id,
                ],
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
        // 1. Zoek de pop-up op (gooit automatisch 404 als hij niet bestaat)
        $pop = Pop::findOrFail($id);

        // 2. BEVEILIGINGSCHECK: Is de ingelogde gebruiker wel de host?
        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You do not own this pop-up.'], 403);
        }

        // 3. Valideer de binnenkomende gegevens (bijna gelijk aan je store methode)
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
        ]);

        // 4. Afbeeldingen verwerken indien er nieuwe worden geüpload
        if ($request->hasFile('images')) {
            // Optioneel: Verwijder oude afbeeldingen uit de storage om ruimte te besparen
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

        // 5. Update de database rij
        $pop->update($validated);

        return response()->json([
            'message' => 'Pop-up successfully updated! ✏️',
            'event' => $pop
        ], 200);
    }
    public function destroy(Request $request, $id)
    {
        // 1. Zoek de pop-up op
        $pop = Pop::findOrFail($id);

        // 2. BEVEILIGINGSCHECK: Mag deze gebruiker dit wel doen?
        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You do not own this pop-up.'], 403);
        }

        try {
            // 3. Schoonmaak: Verwijder de fysieke afbeeldingen van de server/storage
            if (!empty($pop->images)) {
                foreach ($pop->images as $path) {
                    Storage::disk('public')->delete($path);
                }
            }

            // 4. Verwijder de pop-up uit de database
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
     * Nieuwe pop-up opslaan (Geüpdatet voor multi-image verwerking)
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
            'images'        => 'nullable|array', // Valideer binnenkomend als array
        ]);

        $validated['user_id'] = $request->user()->id;

        // Verwerk meerdere afbeeldingen indien aanwezig
        if ($request->hasFile('images')) {
            $storedPaths = [];

            // Loop door elk bestand in de images[] array
            foreach ($request->file('images') as $file) {
                $path = $file->store('pops', 'public');
                $storedPaths[] = $path;
            }

            // Paden opslaan (wordt automatisch JSON via de cast in het Model)
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
            $query->select('id', 'name', 'username');
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
            ->all(); // 👈 Ook hier naar pure array omgezet voor stabiliteit

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
            // Veiligheidscheck: als het stiekem nog een string is uit een oude migratie, zet het om naar een array
            $imagesArray = is_array($event->images)
                ? $event->images
                : (json_decode($event->images, true) ?? [$event->images]);

            foreach ($imagesArray as $path) {
                if ($path) {
                    $urls[] = asset('storage/' . $path);
                }
            }
        }

        // Voeg het dynamische veld toe met volledige URLs voor je React Native Image componenten
        $event->image_urls = $urls;

        return $event;
    }
}
