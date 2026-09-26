<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Flutterwave's v3 API takes amounts in the currency's major unit (Naira,
 * not kobo), unlike `payments.amount` which is stored in kobo — every call
 * here divides by 100 to convert.
 */
class FlutterwaveGateway implements PaymentGatewayContract
{
    public function initialize(Payment $payment, string $callbackUrl): array
    {
        $response = Http::withToken(config('services.flutterwave.secret'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $payment->reference,
                'amount' => $payment->amount / 100,
                'currency' => $payment->currency,
                'redirect_url' => $callbackUrl,
                'customer' => ['email' => $payment->user->email],
            ])
            ->throw()
            ->json();

        return [
            'authorization_url' => $response['data']['link'],
            'provider_reference' => $payment->reference,
        ];
    }

    public function verify(Payment $payment): array
    {
        $response = Http::withToken(config('services.flutterwave.secret'))
            ->get('https://api.flutterwave.com/v3/transactions/verify_by_reference', [
                'tx_ref' => $payment->reference,
            ])
            ->throw()
            ->json();

        $data = $response['data'];

        return [
            'successful' => $data['status'] === 'successful',
            'amount' => (int) round($data['amount'] * 100),
            'currency' => $data['currency'],
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('verif-hash');
        $expected = config('services.flutterwave.webhook_secret');

        return $signature !== null && $expected !== null && hash_equals((string) $expected, $signature);
    }
}
