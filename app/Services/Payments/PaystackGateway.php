<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Paystack's transaction API takes amounts in the smallest currency unit
 * (kobo for NGN), which matches how `payments.amount` is already stored.
 */
class PaystackGateway implements PaymentGatewayContract
{
    /**
     * @param  list<string>|null  $channels  Restrict checkout to specific
     *                                        payment channels, e.g. ['bank_transfer'].
     */
    public function __construct(private ?array $channels = null) {}

    public function initialize(Payment $payment, string $callbackUrl): array
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/transaction/initialize', array_filter([
                'email' => $payment->user->email,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'callback_url' => $callbackUrl,
                'channels' => $this->channels,
            ]))
            ->throw()
            ->json();

        return [
            'authorization_url' => $response['data']['authorization_url'],
            'provider_reference' => $response['data']['reference'],
        ];
    }

    public function verify(Payment $payment): array
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->get("https://api.paystack.co/transaction/verify/{$payment->reference}")
            ->throw()
            ->json();

        $data = $response['data'];

        return [
            'successful' => $data['status'] === 'success',
            'amount' => $data['amount'],
            'currency' => $data['currency'],
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), (string) config('services.paystack.secret'));

        return $signature !== null && hash_equals($expected, $signature);
    }
}
