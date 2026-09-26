<?php

namespace Database\Seeders;

use App\Models\Advertiser;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds one demo advertiser with a spread of campaigns covering every ad
 * type, so the platform has something real to serve out of the box rather
 * than an empty inventory. Run after CatalogSeeder (needs Genre/Artist/
 * Playlist records to sponsor/target).
 */
class AdvertisingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'ads@example.com'],
            ['name' => 'Grace Media Co', 'email_verified_at' => now()]
        );
        $user->assignRole('advertiser');

        $advertiser = Advertiser::firstOrCreate(
            ['user_id' => $user->id],
            ['company_name' => 'Grace Media Co', 'website' => 'https://example.com', 'is_verified' => true]
        );

        $genreId = Genre::query()->value('id');
        $playlist = Playlist::query()->first();
        $artist = Artist::query()->first();

        $advertiser->advertisements()->createMany(array_filter([
            [
                'type' => 'banner',
                'status' => 'active',
                'headline' => 'New devotional series out now',
                'body' => '30 days of encouragement, one track at a time.',
                'cta_label' => 'Listen Now',
                'cta_url' => 'https://example.com/devotional',
                'targeting' => $genreId ? ['genre_ids' => [$genreId]] : null,
            ],
            [
                'type' => 'interstitial',
                'status' => 'active',
                'headline' => 'Upgrade to Premium',
                'body' => 'Uninterrupted listening, unlimited downloads.',
                'cta_label' => 'See Plans',
                'cta_url' => 'https://example.com/premium',
            ],
            [
                // No audio_url: a real campaign would upload one via the
                // advertiser's own campaign creative upload, same as banner
                // images. Left null here rather than pointing at a fake path.
                'type' => 'audio',
                'status' => 'active',
                'headline' => 'Sponsored message from Grace Media Co',
            ],
            $playlist ? [
                'type' => 'sponsored_playlist',
                'status' => 'active',
                'headline' => 'Featured Playlist',
                'sponsorable_type' => Playlist::class,
                'sponsorable_id' => $playlist->id,
            ] : null,
            $artist ? [
                'type' => 'sponsored_artist',
                'status' => 'active',
                'headline' => 'Featured Artist',
                'sponsorable_type' => Artist::class,
                'sponsorable_id' => $artist->id,
            ] : null,
        ]));
    }
}
