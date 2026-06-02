<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http; // 🚀 Belangrijk voor de Firebase REST API
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Haal de gegevens op voor de mobiele Expo app.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id, // 🔑 GEFIXT: id meesturen zodat Expo weet welk Firebase-document hij moet hebben
                'name' => $user->name,
                'username' => $user->username,
                'profile_image' => $user->profile_image
             ],
            'my_pops' => $user->pops()
                    ->select('pops.id', 'pops.title', 'pops.location', 'pops.date', 'pops.image_emoji')
                    ->get() ?? [],

            'favorites' => $user->favoritePops()
                    ->select('pops.id', 'pops.title', 'pops.location', 'pops.date', 'pops.image_emoji')
                    ->get() ?? [],
        ], 200);
    }

    /**
     * Show the user's profile settings page (Inertia - Web).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }



    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Update de avatar en synchroniseer DIRECT naar Firebase Firestore rooms.
     */

    public function syncPremium(Request $request)
    {
        $request->validate(['is_premium' => 'required|boolean']);

        $user = $request->user();
        $user->is_premium = $request->boolean('is_premium');
        $user->save();

        return response()->json(['message' => 'Status synchronized', 'is_premium' => $user->is_premium]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg|max:2048']);

        // 1. Sla de afbeelding lokaal op in Laravel public storage
        $path = $request->file('image')->store('profiles', 'public');

        $user = $request->user();
        $user->profile_image = $path;
        $user->save();

        // 🚀 2. FIREBASE REALTIME SYNCHRONISATIE VIA REST API
        try {
            // Haal je Firebase Project ID op uit je .env bestand
            $projectId = env('FIREBASE_PROJECT_ID');
            $firebaseUserId = "user_" . $user->id;

            if ($projectId) {
                $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery";

                // Query om alle chats te vinden waar deze gebruiker in zit
                $queryPayload = [
                    'structuredQuery' => [
                        'from' => [['collectionId' => 'chats']],
                        'where' => [
                            'fieldFilter' => [
                                'field' => ['fieldPath' => 'participants'],
                                'op' => 'ARRAY_CONTAINS',
                                'value' => ['stringValue' => $firebaseUserId]
                            ]
                        ]
                    ]
                ];

                $response = Http::post($firestoreUrl, $queryPayload);

                if ($response->successful()) {
                    $chats = $response->json();

                    foreach ($chats as $chatContainer) {
                        if (!isset($chatContainer['document'])) continue;

                        $documentName = $chatContainer['document']['name'];

                        // PATCH-request om specifiek jouw geneste avatar-veld in userData te overschrijven
                        Http::patch("https://firestore.googleapis.com/v1/{$documentName}?updateMask.fieldPaths=userData.{$firebaseUserId}.avatar", [
                            'fields' => [
                                'userData' => [
                                    'mapValue' => [
                                        'fields' => [
                                            $firebaseUserId => [
                                                'mapValue' => [
                                                    'fields' => [
                                                        'avatar' => ['stringValue' => $path]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Mocht Firebase een keertje down zijn, log het, maar laat de app-request niet crashen
            \Log::error("Firebase avatar sync mislukt: " . $e->getMessage());
        }

        // 3. Geef antwoord in de structuur die ProfileScreen.tsx verwacht te ontvangen
        return response()->json([
            'message' => 'Profielfoto succesvol bijgewerkt!',
            'profile_image' => $path,
            'url' => asset('storage/' . $path),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'profile_image' => $path
            ]
        ], 200);
    }
}
