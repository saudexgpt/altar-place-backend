<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'artist_id' => Artist::factory(),
            'album_id' => null,
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 100000),
            'type' => 'music',
            'audio_path' => 'audio/placeholder.wav',
            'duration_seconds' => fake()->numberBetween(120, 300),
            'is_explicit' => false,
            'plays_count' => fake()->numberBetween(0, 5000),
            'release_date' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
