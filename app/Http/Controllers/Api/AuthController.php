<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // ==========================================
    // 1. FORGOT & RESET PASSWORD
    // ==========================================

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'If the email address is known, we have sent a link.'], 200);
        }

        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        $resetLink = "jojospops://reset-password?token={$token}&email={$user->email}";
        $this->sendMailgun($user->email, "Reset Password - JoJo's Pops", "Hi {$user->name},\n\nClick the link to reset your password:\n\n{$resetLink}\n\nThis link is valid for 60 minutes.");

        return response()->json(['message' => 'If the email address is known, we have sent a link.'], 200);
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
            return response()->json(['message' => 'Link is invalid or expired.'], 422);
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

        return response()->json(['message' => 'Password successfully changed.'], 200);
    }

    // ==========================================
    // 2. LOGIN WITH 2FA
    // ==========================================

    public function login(Request $request)
    {
        $request->validate(['username' => 'required', 'password' => 'required']);

        $user = User::where('username', $request->username)->orWhere('email', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        // Generate OTP
        $otp = rand(100000, 999999);
        $user->update([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        // Send 2FA email
        $this->sendMailgun($user->email, "Your 2FA Code", "Hi {$user->name}, your 2FA code is: {$otp}. This code is valid for 10 minutes.");

        return response()->json(['message' => '2FA code sent to your email.', 'requires_2fa' => true], 200);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['username' => 'required', 'otp' => 'required']);
        $user = User::where('username', $request->username)->orWhere('email', $request->username)->first();

        if (!$user || !$user->otp_code || !Hash::check($request->otp, $user->otp_code) || now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'Code incorrect or expired.'], 422);
        }

        // Reset OTP and provide token
        $user->update(['otp_code' => null, 'otp_expires_at' => null]);
        $token = $user->createToken('app_auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Successfully logged in!',
            'access_token' => $token,
            'user' => $user
        ], 200);
    }

    // ==========================================
    // 3. REGISTER & LOGOUT
    // ==========================================

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users|max:50',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8'
        ]);

        // 1. Genereer een random 6-cijferige verificatiecode
        $otp = rand(100000, 999999);

        // 2. Maak de gebruiker aan met de gehashte code en verloooptijd (net als bij login)
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(15) // 15 minuten de tijd om te registreren
        ]);

        // 3. Stuur de mail met de code erin
        try {
            $this->sendMailgun(
                $user->email,
                "Welcome to JoJo's Pops! Verify your account",
                "Hi {$user->name}, welcome to the community! Your verification code is: {$otp}. This code is valid for 15 minutes."
            );
        } catch (\Exception $e) {
            Log::error("Mail at registration failed: " . $e->getMessage());
        }

        // 4. Geef de code (en eventueel GEEN token als ze eerst moeten verifiëren) terug in de respons
        return response()->json([
            'message' => 'Account successfully created. Please verify with the code sent to your email.',
            'code' => $otp, // Handig voor testen/frontend
            'requires_verification' => true,
            'user' => $user
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out.'], 200);
    }

    // ==========================================
    // 4. MAILGUN HELPER FUNCTION (DRY)
    // ==========================================

    private function sendMailgun($to, $subject, $text)
    {
        $domain = env('MAILGUN_DOMAIN');
        $secret = env('MAILGUN_SECRET');
        $from = env('MAIL_FROM_ADDRESS');
        $name = env('MAIL_FROM_NAME');

        $endpoint = env('MAILGUN_ENDPOINT', 'api.mailgun.net');

        if (!$domain || !$secret || !$from) {
            Log::error('Mailgun configuration missing in .env');
            return false;
        }

        $response = Http::withBasicAuth('api', $secret)
            ->asForm()
            ->post("https://{$endpoint}/v3/{$domain}/messages", [
                'from'    => "{$name} <{$from}>",
                'to'      => $to,
                'subject' => $subject,
                'text'    => $text
            ]);

        if ($response->failed()) {
            Log::error('Mailgun API error: ' . $response->body());
            return false;
        }

        return true;
    }
}
