<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckoutRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SubscriptionActivator;
use App\Support\FrontendUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private SubscriptionActivator $activator,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        return response()->json([
            'plan' => new SubscriptionPlanResource($user->currentPlan()),
            'subscription' => $subscription ? new SubscriptionResource($subscription->load('plan')) : null,
            'is_premium' => $user->isPremium(),
        ]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($request->input('plan_id'));

        abort_if($plan->isFree(), 422, 'The free plan does not require checkout.');

        $subscription = $user->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'payment_provider' => $request->string('provider'),
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'provider' => $request->string('provider'),
            'reference' => 'sub_'.Str::uuid(),
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        $callbackUrl = FrontendUrl::resolve($request).'/subscription/callback';

        $result = $this->gateways->resolve($request->string('provider'))
            ->initialize($payment, $callbackUrl);

        return response()->json([
            'authorization_url' => $result['authorization_url'],
            'reference' => $payment->reference,
        ], 201);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['reference' => ['required', 'string']]);

        $payment = Payment::where('reference', $request->string('reference'))
            ->where('user_id', $request->user()->id)
            ->with('subscription.plan')
            ->firstOrFail();

        if ($payment->status === 'successful') {
            return response()->json(['subscription' => new SubscriptionResource($payment->subscription)]);
        }

        $result = $this->gateways->resolve($payment->provider)->verify($payment);

        if (! $result['successful']) {
            $payment->update(['status' => 'failed']);

            return response()->json(['message' => 'Payment was not successful.'], 422);
        }

        $subscription = $this->activator->activate($payment);

        return response()->json(['subscription' => new SubscriptionResource($subscription)]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription();

        abort_unless($subscription, 404, 'No active subscription to cancel.');

        $subscription->update(['canceled_at' => now()]);

        return response()->json([
            'message' => 'Subscription canceled. You will keep access until it expires.',
            'subscription' => new SubscriptionResource($subscription->fresh('plan')),
        ]);
    }
}
