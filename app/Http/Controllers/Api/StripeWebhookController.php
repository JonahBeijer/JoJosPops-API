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
        Stripe::setApiKey(config('services.stripe.secret'));

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('Stripe webhook verification failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Webhook signature verification failed',
            ], 400);
        }

        if ($event->type === 'account.updated') {
            $stripeAccount = $event->data->object;

            $user = User::where('stripe_account_id', $stripeAccount->id)->first();

            if ($user) {
                $user->stripe_payouts_enabled = $stripeAccount->payouts_enabled;
                $user->save();
            }
        }

        return response()->json(['status' => 'success']);
    }
}
