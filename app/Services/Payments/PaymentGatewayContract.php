<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    /**
     * Start a checkout, returning the URL to send the payer to and the
     * provider-side reference to reconcile against later.
     *
     * @return array{authorization_url: string, provider_reference: string}
     */
    public function initialize(Payment $payment, string $callbackUrl): array;

    /**
     * Check a payment's real status with the provider.
     *
     * @return array{successful: bool, amount: int, currency: string}
     */
    public function verify(Payment $payment): array;

    /**
     * Validate an inbound webhook actually came from the provider.
     */
    public function verifyWebhookSignature(Request $request): bool;
}
