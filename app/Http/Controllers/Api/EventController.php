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
        $events = Pop::where('is_active', true)
            ->with([
                'user' => function ($query) {
                    $query->select(
                        'id',
                        'name',
                        'username',
                        'profile_image'
                    );
                }
            ])
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn($event) => $this->maskSensitiveData($event))
            ->all();

        return response()->json($events);
    }

    public function show($id)
    {
        $event = Pop::where('is_active', true)
            ->with([
                'user' => function ($query) {
                    $query->select(
                        'id',
                        'name',
                        'username',
                        'profile_image'
                    );
                }
            ])
            ->findOrFail($id);

        return response()->json([
            'event' => $this->maskSensitiveData($event),
            'is_revealed' => now()->gt(Carbon::parse($event->reveal_time))
        ]);
    }

    public function buyTicket(Request $request, $id)
    {
        $pop = Pop::where('is_active', true)
            ->with('user')
            ->findOrFail($id);

        if (!$pop->is_ticketed || !$pop->ticket_price) {
            return response()->json([
                'message' => 'Dit event is gratis of ongeldig.'
            ], 400);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {

            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($pop->ticket_price * 100),

                'currency' => 'eur',

                'payment_method_types' => [
                    'card',
                    'ideal',
                    'klarna',
                    'bancontact',
                    'satispay',
                    'amazon_pay',
                    'eps'
                ]
            ]);

            return response()->json([
                'paymentIntent' =>
                    $paymentIntent->client_secret
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Betaling mislukt',
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

        $validated['user_id'] =
            $request->user()->id;

        $validated['is_active'] =
            !in_array(
                strtolower($validated['access'] ?? ''),
                ['premium', 'premium only']
            )
            || $request->user()->is_premium;

        if ($request->hasFile('images')) {

            $stored = [];

            foreach ($request->file('images') as $file) {
                $stored[] =
                    $file->store('pops', 'public');
            }

            $validated['images'] = $stored;
        }

        if (empty($validated['reveal_time'])) {
            $validated['reveal_time'] = now();
        }

        $event = Pop::create($validated);

        return response()->json([
            'message' =>
                'Pop-up succesvol geplaatst 🚀',
            'event' => $event
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if (
            $pop->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'access' => 'nullable|string',
            'images' => 'nullable|array',
        ]);

        if (isset($validated['access'])) {

            $validated['is_active'] =
                !in_array(
                    strtolower($validated['access']),
                    ['premium', 'premium only']
                )
                || $request->user()->is_premium;
        }

        if ($request->hasFile('images')) {

            foreach (($pop->images ?? []) as $old) {
                Storage::disk('public')
                    ->delete($old);
            }

            $stored = [];

            foreach ($request->file('images') as $file) {
                $stored[] =
                    $file->store(
                        'pops',
                        'public'
                    );
            }

            $validated['images'] = $stored;
        }

        $pop->update($validated);

        return response()->json([
            'message' =>
                'Pop bijgewerkt',
            'event' => $pop
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if (
            $pop->user_id !==
            $request->user()->id
        ) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        foreach (($pop->images ?? []) as $img) {
            Storage::disk('public')
                ->delete($img);
        }

        $pop->delete();

        return response()->json([
            'message' =>
                'Pop verwijderd'
        ]);
    }

    public function nearby(Request $request)
    {
        $lat = $request->lat;
        $lng = $request->lng;

        $radius =
            $request->radius ?? 10;

        $pops = Pop::where('is_active', true)
            ->with('user')
            ->selectRaw("
                *,
                (
                    6371 *
                    acos(
                        cos(radians(?))
                        *
                        cos(radians(latitude))
                        *
                        cos(
                            radians(longitude)
                            -
                            radians(?)
                        )
                        +
                        sin(radians(?))
                        *
                        sin(
                            radians(latitude)
                        )
                    )
                ) as distance
            ", [$lat, $lng, $lat])
            ->having(
                'distance',
                '<',
                $radius
            )
            ->orderBy('distance')
            ->get()
            ->map(
                fn($e)
                =>
                $this->maskSensitiveData($e)
            );

        return response()->json($pops);
    }

    private function maskSensitiveData($event)
    {
        if (
            now()->lt(
                Carbon::parse(
                    $event->reveal_time
                )
            )
        ) {
            $event->location =
                'Location locked';
        }

        $event->image_urls = [];

        foreach (
            (array) $event->images
            as $img
        ) {

            if ($img) {
                $event->image_urls[] =
                    asset(
                        'storage/' . $img
                    );
            }
        }

        return $event;
    }
}
