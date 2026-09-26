<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Subscription;

/**
 * Shared reconciliation logic: turns a confirmed-successful payment into an
 * active subscription. Called from both the user-initiated verify endpoint
 * (the redirect-back flow) and the provider webhook, so a payment gets
 * activated exactly once no matter which path notices success first.
 */
class SubscriptionActivator
{
    public function activate(Payment $payment): Subscription
    {
        $subscription = $payment->subscription;

        if ($payment->status !== 'successful') {
            $payment->update(['status' => 'successful', 'paid_at' => now()]);
        }

        if ($subscription->isActive()) {
            return $subscription;
        }

        $plan = $subscription->plan;

        // Only one subscription should be active at a time; superseding an
        // existing one (an early renewal, or an upgrade) expires it now.
        $subscription->user->subscriptions()
            ->where('status', 'active')
            ->where('id', '!=', $subscription->id)
            ->update(['status' => 'expired']);

        $startsAt = now();
        $endsAt = match ($plan->billing_interval) {
            'month' => $startsAt->copy()->addMonthNoOverflow(),
            'year' => $startsAt->copy()->addYearNoOverflow(),
            default => null,
        };

        $subscription->update([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $subscription->fresh('plan');
    }
}
