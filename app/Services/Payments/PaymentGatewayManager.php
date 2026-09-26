<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * Resolves the gateway for a checkout's chosen provider. When
 * `PAYMENT_TESTING_MODE` is enabled (the default until real Paystack/
 * Flutterwave keys are configured), every provider transparently resolves
 * to the FakeGateway instead — the provider selection UX is fully
 * exercised, but no real API calls are made.
 */
class PaymentGatewayManager
{
    /**
     * @var list<string>
     */
    public const PROVIDERS = ['paystack', 'flutterwave', 'bank_transfer'];

    public function resolve(string $provider): PaymentGatewayContract
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException("Unsupported payment provider [{$provider}].");
        }

        if (config('services.payments.testing_mode')) {
            return new FakeGateway;
        }

        return match ($provider) {
            'paystack' => new PaystackGateway,
            // Bank transfer is a Paystack checkout restricted to the bank
            // transfer channel, not a separate integration.
            'bank_transfer' => new PaystackGateway(channels: ['bank_transfer']),
            'flutterwave' => new FlutterwaveGateway,
        };
    }
}
