<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

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
        }

        return response()->json(['status' => 'success']);
    }
}
