<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SubscriptionActivator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Provider-initiated backstop for the checkout-callback flow: if a payer
 * closes the browser before the frontend calls /subscriptions/verify, the
 * webhook still reconciles the payment. Idempotent with the verify
 * endpoint via SubscriptionActivator (both can fire for the same payment
 * without double-activating).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private SubscriptionActivator $activator,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $gateway = $this->gateways->resolve($provider);

        if (! $gateway->verifyWebhookSignature($request)) {
            Log::warning('Rejected payment webhook with an invalid signature.', ['provider' => $provider]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $reference = $provider === 'flutterwave'
            ? $request->input('data.tx_ref')
            : $request->input('data.reference');

        $payment = Payment::where('reference', $reference)->with('subscription.plan')->first();

        if (! $payment) {
            return response()->json(['message' => 'Unknown payment reference.'], 404);
        }

        $result = $gateway->verify($payment);

        if ($result['successful']) {
            $this->activator->activate($payment);
        } else {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'Webhook processed.']);
    }
}
