<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // FIX: Laravel request methodes in plaats van ruwe PHP functies
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');
        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Webhook signature verification failed'], 400);
        }

        // We luisteren naar het event wanneer een account geüpdatet is
        if ($event->type === 'account.updated') {
            $stripeAccount = $event->data->object;

            $user = User::where('stripe_account_id', $stripeAccount->id)->first();

            if ($user) {
                // Update de status in je database
                $user->stripe_payouts_enabled = $stripeAccount->payouts_enabled;
                $user->save();
            }
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            } catch (\Exception $e) {
                // Dit logt de exacte reden waarom Stripe de payload weigert in storage/logs/laravel.log
                Log::error('Stripe Webhook Verificatie Mislukt: ' . $e->getMessage());
                return response()->json(['error' => 'Webhook signature verification failed'], 400);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
