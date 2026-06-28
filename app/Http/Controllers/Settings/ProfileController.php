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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Fetch the data for the mobile Expo app.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Fetch the pops owned by the user THEMSELVES
        $myPops = Pop::where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->get();

        // 2. Fetch the pops the user is ATTENDING
        $goingPops = Pop::whereHas('requests', function($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereIn('status', ['accepted', 'paid']);
        })
            ->where('is_active', true)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                // 🔥 AANGEPAST: Gebruikt nu onze SFTP proxy-route in plaats van S3
                'profile_image' => $user->profile_image ? url("/api/pops/image?path=" . urlencode($user->profile_image)) : null
            ],
            'my_pops' => $myPops,
            'going' => $goingPops
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
     * Synchronize premium status.
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
     * Update the avatar and sync DIRECTLY to Firebase Firestore rooms.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120']);

        // 🔥 AANGEPAST: Slaat nu op via SFTP
        $path = $request->file('image')->store('profiles', 'sftp');

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
            \Log::error("Firebase avatar sync failed: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Profile picture successfully updated!',
            'profile_image' => $path,
            // 🔥 AANGEPAST: Geeft nu de SFTP proxy url terug
            'url' => url("/api/pops/image?path=" . urlencode($path)),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'profile_image' => $path
            ]
        ], 200);
    }

    public function updateEmail(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'required'
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 403);
        }

        $user->email = $request->email;
        $user->save();

        return response()->json([
            'message' => 'Email address successfully updated.',
            'user' => $user
        ], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 403);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password successfully updated.'
        ], 200);
    }
}
