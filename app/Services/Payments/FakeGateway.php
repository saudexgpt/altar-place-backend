<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Simulates an instantly-successful checkout with no external HTTP calls,
 * so the full subscription lifecycle (checkout -> active -> expiry) is
 * testable without real Paystack/Flutterwave credentials. Selected via
 * `PAYMENT_TESTING_MODE=true` regardless of which provider the user picked
 * — see PaymentGatewayManager.
 */
class FakeGateway implements PaymentGatewayContract
{
    public function initialize(Payment $payment, string $callbackUrl): array
    {
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';

        return [
            'authorization_url' => "{$callbackUrl}{$separator}reference={$payment->reference}&fake=1",
            'provider_reference' => $payment->reference,
        ];
    }

    public function verify(Payment $payment): array
    {
        return [
            'successful' => true,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }
}
