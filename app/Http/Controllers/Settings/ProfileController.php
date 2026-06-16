<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Pop;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

        // 1. Haal de pops op die van de gebruiker ZELF zijn
        $myPops = Pop::where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->get();

        // 2. 🚀 Haal de pops op waar deze gebruiker naar TOE GAAT
        // (Dus waarvoor hij in PopRequest de status 'accepted' of 'paid' heeft)
        $goingPops = Pop::whereHas('requests', function($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereIn('status', ['accepted', 'paid']);
        })
            ->where('is_active', true) // Check optioneel of het event niet verwijderd is
            ->orderBy('date', 'asc') // Sorteer op datum zodat de eerste de volgende is
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'profile_image' => $user->profile_image
            ],
            'my_pops' => $myPops,
            'going' => $goingPops // 👈 Hier sturen we de goede going lijst terug!
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
     * Status synchroniseren.
     */
    public function syncPremium(Request $request)
    {
        $request->validate(['is_premium' => 'required|boolean']);

        $user = $request->user();
        $user->is_premium = $request->boolean('is_premium');
        $user->save();

        return response()->json(['message' => 'Status synchronized', 'is_premium' => $user->is_premium]);
    }

    /**
     * Update de avatar en synchroniseer DIRECT naar Firebase Firestore rooms.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg|max:2048']);

        $path = $request->file('image')->store('profiles', 'public');

        $user = $request->user();
        $user->profile_image = $path;
        $user->save();

        try {
            $projectId = env('FIREBASE_PROJECT_ID');
            $firebaseUserId = "user_" . $user->id;

            if ($projectId) {
                $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery";

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
            \Log::error("Firebase avatar sync mislukt: " . $e->getMessage());
        }

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
