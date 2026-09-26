<?php

use App\Services\Payments\FakeGateway;
use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaystackGateway;
use Illuminate\Http\Request;

test('the gateway manager resolves the fake gateway in testing mode regardless of provider', function () {
    config(['services.payments.testing_mode' => true]);
    $manager = new PaymentGatewayManager;

    expect($manager->resolve('paystack'))->toBeInstanceOf(FakeGateway::class);
    expect($manager->resolve('flutterwave'))->toBeInstanceOf(FakeGateway::class);
    expect($manager->resolve('bank_transfer'))->toBeInstanceOf(FakeGateway::class);
});

test('the gateway manager resolves real gateways when testing mode is off', function () {
    config(['services.payments.testing_mode' => false]);
    $manager = new PaymentGatewayManager;

    expect($manager->resolve('paystack'))->toBeInstanceOf(PaystackGateway::class);
    expect($manager->resolve('flutterwave'))->toBeInstanceOf(FlutterwaveGateway::class);
    expect($manager->resolve('bank_transfer'))->toBeInstanceOf(PaystackGateway::class);
});

test('the gateway manager rejects an unsupported provider', function () {
    $manager = new PaymentGatewayManager;

    $manager->resolve('bitcoin');
})->throws(InvalidArgumentException::class);

test('paystack webhook signature verification matches a valid HMAC and rejects a bad one', function () {
    config(['services.paystack.secret' => 'test-secret']);
    $gateway = new PaystackGateway;
    $body = '{"event":"charge.success"}';
    $validSignature = hash_hmac('sha512', $body, 'test-secret');

    $validRequest = Request::create('/webhooks/paystack', 'POST', content: $body);
    $validRequest->headers->set('x-paystack-signature', $validSignature);
    expect($gateway->verifyWebhookSignature($validRequest))->toBeTrue();

    $invalidRequest = Request::create('/webhooks/paystack', 'POST', content: $body);
    $invalidRequest->headers->set('x-paystack-signature', 'wrong');
    expect($gateway->verifyWebhookSignature($invalidRequest))->toBeFalse();
});

test('flutterwave webhook signature verification compares the configured hash', function () {
    config(['services.flutterwave.webhook_secret' => 'expected-hash']);
    $gateway = new FlutterwaveGateway;

    $validRequest = Request::create('/webhooks/flutterwave', 'POST');
    $validRequest->headers->set('verif-hash', 'expected-hash');
    expect($gateway->verifyWebhookSignature($validRequest))->toBeTrue();

    $invalidRequest = Request::create('/webhooks/flutterwave', 'POST');
    $invalidRequest->headers->set('verif-hash', 'wrong-hash');
    expect($gateway->verifyWebhookSignature($invalidRequest))->toBeFalse();
});
