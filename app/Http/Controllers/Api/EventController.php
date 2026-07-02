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
    private function applyEventVisibility($query, $user = null, $upcomingOnly = false)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($user, $upcomingOnly) {
                $q->whereDate('date', '>=', now()->toDateString())
                    ->orWhereNull('date');

                if ($user && !$upcomingOnly) {
                    $q->orWhere(function ($subQ) use ($user) {
                        $subQ->whereDate('date', '<', now()->toDateString())
                            ->whereHas('requests', function ($requestQuery) use ($user) {
                                $requestQuery->where('user_id', $user->id)
                                    ->whereIn('status', ['paid', 'accepted']);
                            });
                    });
                }
            })
            ->where(function ($q) use ($user) {
                $q->whereIn('access', ['open', 'private', 'Open', 'Private'])
                    ->orWhereNull('access');

                if ($user) {
                    $q->orWhere('user_id', $user->id);
                    $q->orWhereHas('requests', function ($reqQuery) use ($user) {
                        $reqQuery->where('user_id', $user->id)
                            ->whereIn('status', ['invited', 'pending_invite', 'accepted', 'paid', 'requested']);
                    });

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
        $query = $this->applyEventVisibility($query, $user, true);

        $events = $query->with(['user' => function ($query) {
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
        $event = Pop::with(['user' => function ($query) {
            $query->select('id', 'name', 'username', 'profile_image');
        }])->findOrFail($id);

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

        if (!$event->is_active) {
            return response()->json(['message' => 'This event is no longer active.'], 403);
        }

        $revealTime = Carbon::parse($event->reveal_time);
        $event->user_rsvp_status = $rsvpStatus;

        return response()->json([
            'event' => $this->maskSensitiveData($event),
            'is_revealed' => now()->gt($revealTime),
            'rsvp_status' => $rsvpStatus,
            'has_paid' => $hasPaid
        ]);
    }

    public function fypFeed(Request $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();
        if (!$user) return response()->json([]);

        $followingIds = $user->following()->allRelatedIds()->toArray();
        $lat = $request->lat;
        $lng = $request->lng;

        $query = Pop::query()
            ->where(function ($q) use ($followingIds, $user) {
                if (!empty($followingIds)) {
                    $q->whereIn('user_id', $followingIds);
                } else {
                    $q->whereRaw('0 = 1');
                }

                $q->orWhereHas('requests', function ($reqQuery) use ($user) {
                    $reqQuery->where('user_id', $user->id);
                });
            })
            ->with(['user' => function ($q) {
                $q->select('id', 'name', 'username', 'profile_image');
            }]);

        $query = $this->applyEventVisibility($query, $user, true);

        if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
            $query->select('*')
                ->selectRaw("(6371 * acos( cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)) )) AS distance", [$lat, $lng, $lat])
                ->orderBy('distance', 'asc');
        } else {
            $query->orderBy('date', 'asc');
        }

        $events = $query->get()->map(function ($event) {
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

        $user = $request->user() ?? auth('sanctum')->user();
        $query = Pop::query();
        $query = $this->applyEventVisibility($query, $user, true);

        $pops = $query->select('*')
            ->with(['user' => function ($query) {
                $query->select('id', 'name', 'username', 'profile_image');
            }])
            ->selectRaw("(6371 * acos( cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)) )) AS distance", [$request->lat, $request->lng, $request->lat])
            ->having("distance", "<", $request->radius ?? 10)
            ->orderBy("distance")
            ->get()
            ->map(function ($event) {
                return $this->maskSensitiveData($event);
            })
            ->all();

        return response()->json($pops);
    }

    // 🔥 DEZE METHODE IS AANGEPAST VOOR STRIPE CONNECT
    public function buyTicket(Request $request, $id)
    {
        $pop = Pop::where('is_active', true)
            ->where('date', '>=', now()->toDateString())
            ->with('user')
            ->findOrFail($id);

        if (!$pop->is_ticketed || !$pop->ticket_price) {
            return response()->json(['message' => 'This event is free or does not have a valid ticket price.'], 400);
        }

        // Check of de host Stripe Connect heeft afgerond
        if (!$pop->user->stripe_account_id || !$pop->user->stripe_payouts_enabled) {
            return response()->json(['message' => 'The host is not ready to receive payments yet.'], 400);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $totalAmountCents = (int) round($pop->ticket_price * 100);

        // Bereken 10% commissie (voor jouw platform)
        $applicationFeeCents = (int) round($totalAmountCents * 0.10);

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $totalAmountCents,
                'currency' => 'eur',
                'payment_method_types' => ['card', 'ideal'], // Of wat je maar ondersteunt

                // Jouw 10% fee
                'application_fee_amount' => $applicationFeeCents,

                // De overige 90% gaat automatisch naar de Stripe account van de host
                'transfer_data' => [
                    'destination' => $pop->user->stripe_account_id,
                ],
            ]);

            return response()->json([
                'paymentIntent' => $paymentIntent->client_secret
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initialize the payment.',
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
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'kept_images' => 'nullable|array',
            'is_ticketed' => 'nullable|boolean',
            'ticket_price' => 'nullable|numeric',
            'has_first_aider' => 'nullable|boolean',
            'has_security' => 'nullable|boolean',
        ]);

        $oldImages = is_array($pop->images) ? $pop->images : (json_decode($pop->images, true) ?? []);
        $keptImages = $request->input('kept_images', []);

        foreach (array_diff($oldImages, $keptImages) as $oldPath) {
            Storage::disk('sftp')->delete($oldPath);
        }

        $finalImages = $keptImages;

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $finalImages[] = $file->store('pops', 'sftp');
            }
        }

        $validated['images'] = $finalImages;
        unset($validated['kept_images']);

        if (isset($validated['access'])) {
            $validated['is_active'] = $pop->is_active ?? true;
        }

        $pop->update($validated);

        return response()->json([
            'message' => 'Pop-up successfully updated.',
            'event' => $pop
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $pop = Pop::findOrFail($id);

        if ($pop->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. You do not own this pop-up.'], 403);
        }

        try {
            if (!empty($pop->images)) {
                $imagesArray = is_array($pop->images) ? $pop->images : (json_decode($pop->images, true) ?? []);

                foreach ($imagesArray as $path) {
                    Storage::disk('sftp')->delete($path);
                }
            }

            $pop->delete();

            return response()->json([
                'message' => 'Pop-up successfully deleted.'
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
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_ticketed' => 'nullable|boolean',
            'ticket_price' => 'nullable|numeric',
            'has_first_aider' => 'nullable|boolean',
            'has_security' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['is_active'] = true;

        if ($request->hasFile('images')) {
            $storedPaths = [];

            foreach ($request->file('images') as $file) {
                $storedPaths[] = $file->store('pops', 'sftp');
            }

            $validated['images'] = $storedPaths;
        } else {
            $validated['images'] = [];
        }

        if (empty($validated['reveal_time'])) {
            $validated['reveal_time'] = now();
        }

        $event = Pop::create($validated);

        return response()->json([
            'message' => 'Pop-up successfully created.',
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
            $imagesArray = is_string($event->images) ? json_decode($event->images, true) : $event->images;

            if (is_array($imagesArray)) {
                foreach ($imagesArray as $path) {
                    if ($path) {
                        $urls[] = url("/api/pops/image?path=" . urlencode($path));
                    }
                }
            }
        }

        $event->image_urls = $urls;

        return $event;
    }

    public function serveImage(Request $request)
    {
        $path = $request->query('path');

        if (!$path || !Storage::disk('sftp')->exists($path)) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $file = Storage::disk('sftp')->get($path);
        $mimeType = Storage::disk('sftp')->mimeType($path);

        return response($file, 200)->header('Content-Type', $mimeType);
    }
}
