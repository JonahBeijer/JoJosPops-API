<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;

class StripeConnectController extends Controller
{
    public function connect(Request $request)
    {
        $user = $request->user();

        // Haal het land uit de request, met 'NL' als veilige fallback
        $countryCode = $request->input('country', 'NL');

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // 1. Maak een Express account aan als de gebruiker er nog geen heeft
        if (!$user->stripe_account_id) {
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => strtoupper($countryCode), // Zorg dat het altijd hoofdletters is (bijv. NL, BE, US)
                'email' => $user->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            $user->stripe_account_id = $account->id;
            $user->save();
        }

        // 2. Genereer de unieke URL
        $accountLink = \Stripe\AccountLink::create([
            'account' => $user->stripe_account_id,
            'refresh_url' => env('FRONTEND_URL') . '/stripe/refresh',
            'return_url' => env('FRONTEND_URL') . '/stripe/success',
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'url' => $accountLink->url
        ]);
    }

    public function checkStatus(Request $request)
    {
        $user = $request->user();
        if (!$user->stripe_account_id) {
            return response()->json(['payouts_enabled' => false]);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));
        $account = Account::retrieve($user->stripe_account_id);

        $user->stripe_payouts_enabled = $account->payouts_enabled;
        $user->save();

        return response()->json([
            'payouts_enabled' => $account->payouts_enabled
        ]);
    }
}
