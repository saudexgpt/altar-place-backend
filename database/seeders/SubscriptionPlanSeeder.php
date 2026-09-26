<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Prices are stored in kobo (NGN's smallest unit) to avoid floating
     * point money bugs — 100 kobo = ₦1.
     */
    public function run(): void
    {
        $freeFeatures = [
            'ad_free' => false,
            'max_audio_quality' => 'standard',
            'download_limit' => 10,
            'offline_playback' => false,
        ];

        $premiumFeatures = [
            'ad_free' => true,
            'max_audio_quality' => 'high',
            'download_limit' => null,
            'offline_playback' => true,
        ];

        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'billing_interval' => 'none',
                'max_family_members' => null,
                'features' => $freeFeatures,
            ],
            [
                'name' => 'Premium Monthly',
                'slug' => 'premium-monthly',
                'price' => 150000,
                'billing_interval' => 'month',
                'max_family_members' => null,
                'features' => $premiumFeatures,
            ],
            [
                'name' => 'Premium Yearly',
                'slug' => 'premium-yearly',
                'price' => 1500000,
                'billing_interval' => 'year',
                'max_family_members' => null,
                'features' => $premiumFeatures,
            ],
            [
                'name' => 'Family Plan',
                'slug' => 'family',
                'price' => 250000,
                'billing_interval' => 'month',
                'max_family_members' => 5,
                'features' => $premiumFeatures,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
