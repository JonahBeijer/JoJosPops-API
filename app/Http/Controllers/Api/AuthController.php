<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validatie van de binnenkomende app-aanvraag
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. Zoek flexibel: match op de username kolom OF de email kolom
        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        // 3. Matched het wachtwoord met de bcrypt hash in de database?
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'De ingevoerde gebruikersnaam of het wachtwoord is onjuist.'
            ], 422);
        }

        // 5. Genereer een nieuw tokensysteem via Sanctum
        $token = $user->createToken('app_auth_token')->plainTextToken;

        // 6. Stuur het token terug naar Expo SecureStore
        return response()->json([
            'message' => 'Succesvol ingelogd! 👋',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_image' => $user->profile_image, // ✅ Toegevoegd zodat login ook direct de foto heeft
            ]
        ], 200);
    }

    public function register(Request $request)
    {
        // 1. Valideer de invoer streng en vang unieke velden af
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|alpha_dash|max:50|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // ✅ Toegevoegd voor de profielfoto validatie
        ], [
            'username.unique' => 'Deze gebruikersnaam is helaas al bezet.',
            'email.unique' => 'Dit e-mailadres is al in gebruik.',
            'password.min' => 'Het wachtwoord moet minimaal 8 tekens bevatten.',
            'username.alpha_dash' => 'Je gebruikersnaam mag alleen letters, cijfers, streepjes of underscores bevatten.'
        ]);

        // 🚀 NIEUW: Verwerk de afbeelding als deze is meegestuurd
        $profileImagePath = null;
        if ($request->hasFile('image')) {
            // Sla de foto op in storage/app/public/profiles
            $profileImagePath = $request->file('image')->store('profiles', 'public');
        }

        // 2. Maak de gebruiker aan in de database
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_image' => $profileImagePath, // ✅ Sla het gegenereerde pad op in de database!
        ]);

        // 3. Genereer direct een Sanctum API-token
        $token = $user->createToken('app_auth_token')->plainTextToken;

        // 4. Stuur successtatus, het token en de user data terug
        return response()->json([
            'message' => 'Account succesvol aangemaakt! 🚀',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_image' => $user->profile_image, // ✅ Stuur het pad mee terug naar Expo
            ]
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Succesvol uitgelogd.'
        ], 200);
    }
}
