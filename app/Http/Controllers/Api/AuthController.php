<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail; // Terug naar de standaard Mail facade
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ==========================================
    // 1. WACHTWOORD VERGETEN & RESETTEN
    // ==========================================

    private function generateAndSendOTP(User $user, $subject)
    {
        $otp = sprintf("%06d", mt_rand(1, 999999));

        $user->otp_code = $otp;
        $user->otp_expires_at = now()->addMinutes(15);
        $user->save();

        // 🚀 OPLOSSING: We gebruiken gewoon Laravel's eigen Mail systeem.
        // Via Gmail gaat dit feilloos langs de restricties van Railway.
        Mail::raw("Je verificatiecode is: {$otp}\n\nDeze code is 15 minuten geldig.", function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Als het e-mailadres bekend is, hebben we een code gestuurd.'
            ], 200);
        }

        $this->generateAndSendOTP($user, 'Wachtwoord Herstellen - JoJo\'s Pops');

        return response()->json([
            'message' => 'Als het e-mailadres bekend is, hebben we een code gestuurd.'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->otp_code !== $request->code || now()->gt($user->otp_expires_at)) {
            return response()->json([
                'message' => 'De ingevoerde code is onjuist of verlopen.'
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Wachtwoord is succesvol gewijzigd. Je kunt nu inloggen.'
        ], 200);
    }

    // ==========================================
    // 2. INLOGGEN, REGISTREREN & UITLOGGEN
    // ==========================================

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'De ingevoerde gebruikersnaam of het wachtwoord is onjuist.'
            ], 422);
        }

        $token = $user->createToken('app_auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Succesvol ingelogd! 👋',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
            ]
        ], 200);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|alpha_dash|max:50|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'username.unique' => 'Deze gebruikersnaam is helaas al bezet.',
            'email.unique' => 'Dit e-mailadres is al in gebruik.',
            'password.min' => 'Het wachtwoord moet minimaal 8 tekens bevatten.',
            'username.alpha_dash' => 'Je gebruikersnaam mag alleen letters, cijfers, streepjes of underscores bevatten.'
        ]);

        $profileImagePath = null;
        if ($request->hasFile('image')) {
            $profileImagePath = $request->file('image')->store('profiles', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_image' => $profileImagePath,
        ]);

        $token = $user->createToken('app_auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Account succesvol aangemaakt! 🚀',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'profile_image' => $user->profile_image,
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
