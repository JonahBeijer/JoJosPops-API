<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    // ==========================================
    // 1. WACHTWOORD VERGETEN & RESETTEN
    // ==========================================

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Als het e-mailadres bekend is, hebben we een link gestuurd.'], 200);
        }

        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        $resetLink = "jojospops://reset-password?token={$token}&email={$user->email}";
        $this->sendMailgun($user->email, "Wachtwoord Herstellen - JoJo's Pops", "Hoi {$user->name},\n\nKlik op de link om je wachtwoord te resetten:\n\n{$resetLink}\n\nDeze link is 60 minuten geldig.");

        return response()->json(['message' => 'Als het e-mailadres bekend is, hebben we een link gestuurd.'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8'
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord || now()->subMinutes(60)->gt($resetRecord->created_at)) {
            return response()->json(['message' => 'Link is ongeldig of verlopen.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($request->password),
                'otp_code' => null,
                'otp_expires_at' => null
            ]);
        }

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Wachtwoord succesvol gewijzigd.'], 200);
    }

    // ==========================================
    // 2. INLOGGEN MET 2FA
    // ==========================================

    public function login(Request $request)
    {
        $request->validate(['username' => 'required', 'password' => 'required']);

        $user = User::where('username', $request->username)->orWhere('email', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Gegevens onjuist.'], 422);
        }

        // Genereer OTP
        $otp = rand(100000, 999999);
        $user->update([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        // Verstuur 2FA mail
        $this->sendMailgun($user->email, "Jouw 2FA Code", "Hoi {$user->name}, je 2FA code is: {$otp}. Deze is 10 minuten geldig.");

        return response()->json(['message' => '2FA code verzonden naar je e-mail.', 'requires_2fa' => true], 200);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['username' => 'required', 'otp' => 'required']);
        $user = User::where('username', $request->username)->orWhere('email', $request->username)->first();

        if (!$user || !$user->otp_code || !Hash::check($request->otp, $user->otp_code) || now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'Code onjuist of verlopen.'], 422);
        }

        // Reset OTP en geef token
        $user->update(['otp_code' => null, 'otp_expires_at' => null]);
        $token = $user->createToken('app_auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Succesvol ingelogd!',
            'access_token' => $token,
            'user' => $user
        ], 200);
    }

    // ==========================================
    // 3. REGISTREREN & LOGOUT
    // ==========================================

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'access_token' => $user->createToken('app_auth_token')->plainTextToken,
            'user' => $user
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Succesvol uitgelogd.'], 200);
    }

    // ==========================================
    // 4. HULPFUNCTIE MAILGUN (DRY)
    // ==========================================

    private function sendMailgun($to, $subject, $text)
    {
        $domain = env('MAILGUN_DOMAIN');
        $secret = env('MAILGUN_SECRET');
        $from = env('MAIL_FROM_ADDRESS');
        $name = env('MAIL_FROM_NAME');

        Http::withBasicAuth('api', $secret)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", [
                'from'    => "{$name} <{$from}>",
                'to'      => $to,
                'subject' => $subject,
                'text'    => $text
            ]);
    }
}
