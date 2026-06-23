<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ==========================================
    // 1. WACHTWOORD VERGETEN & RESETTEN (Magic Link)
    // ==========================================

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Voor veiligheid geven we altijd een succesmelding terug,
        // zelfs als het e-mailadres niet bestaat (voorkomt scraping).
        if (!$user) {
            return response()->json([
                'message' => 'Als het e-mailadres bekend is, hebben we een link gestuurd.'
            ], 200);
        }

        // 1. Genereer een veilige random token
        $token = Str::random(60);

        // 2. Sla op in de standaard Laravel reset tabel
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );

        // 3. Maak de Deep Link voor je Expo App
        // Let op: 'jojopops' moet exact overeenkomen met het 'scheme' in je app.json
        $resetLink = "jojospops://reset-password?token={$token}&email={$user->email}";

        // 4. Stuur de mail netjes via je mailer
        Mail::raw("Hoi {$user->name},\n\nKlik op de onderstaande link om je wachtwoord te resetten:\n\n{$resetLink}\n\nDeze link is 60 minuten geldig.", function ($message) use ($user) {
            $message->to($user->email)->subject("Wachtwoord Herstellen - JoJo's Pops");
        });

        return response()->json([
            'message' => 'Als het e-mailadres bekend is, hebben we een link gestuurd.'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8'
        ]);

        // 1. Zoek de token in de database
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'message' => 'Deze reset link is ongeldig of verlopen.'
            ], 422);
        }

        // 2. Optioneel: Check of de token niet ouder is dan 60 minuten
        if (now()->subMinutes(60)->gt($resetRecord->created_at)) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Deze reset link is verlopen.'
            ], 422);
        }

        // 3. Update het wachtwoord van de gebruiker
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $user->password = Hash::make($request->password);

            // Verwijder oude OTP velden voor de zekerheid als ze nog in de DB stonden
            $user->otp_code = null;
            $user->otp_expires_at = null;

            $user->save();
        }

        // 4. Ruim de gebruikte token op uit de database
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Wachtwoord is succesvol gewijzigd. Je kunt nu inloggen!'
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
