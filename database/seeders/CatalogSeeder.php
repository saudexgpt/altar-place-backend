<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Track;
use App\Support\WavGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = collect(['Gospel', 'Worship', 'Afrobeat', 'Hip-Hop', 'Inspirational'])
            ->mapWithKeys(fn (string $name) => [$name => Genre::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ])]);

        $artists = collect([
            ['name' => 'Mercy Chinwo', 'bio' => 'Award-winning Nigerian gospel singer and songwriter.'],
            ['name' => 'Sinach', 'bio' => 'Worship leader and songwriter behind global worship anthems.'],
            ['name' => 'Nathaniel Bassey', 'bio' => 'Trumpeter, worship leader, and host of Hallelujah Challenge.'],
            ['name' => 'Frank Edwards', 'bio' => 'Gospel minstrel known for high-energy praise records.'],
            ['name' => 'Judikay', 'bio' => 'Gospel recording artist known for soulful worship ballads.'],
            ['name' => 'Pastor Emmanuel Adeyemi', 'bio' => 'Bible teacher and host of the Walking in Faith sermon series.'],
            ['name' => 'The Word Today', 'bio' => 'A daily devotional and Bible-study podcast.'],
        ])->map(fn (array $attrs) => Artist::create([
            'name' => $attrs['name'],
            'slug' => Str::slug($attrs['name']),
            'bio' => $attrs['bio'],
            'is_verified' => true,
        ]))->keyBy('name');

        $albums = collect([
            'Faith Unshaken' => ['artist' => 'Mercy Chinwo', 'genre' => 'Gospel', 'year' => 2024],
            'Excess Love' => ['artist' => 'Mercy Chinwo', 'genre' => 'Gospel', 'year' => 2022],
            'Way Maker Live' => ['artist' => 'Sinach', 'genre' => 'Worship', 'year' => 2023],
            'Hallelujah Sessions' => ['artist' => 'Nathaniel Bassey', 'genre' => 'Worship', 'year' => 2024],
        ])->map(fn (array $attrs, string $title) => Album::create([
            'artist_id' => $artists[$attrs['artist']]->id,
            'genre_id' => $genres[$attrs['genre']]->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'type' => 'album',
            'release_year' => $attrs['year'],
            'description' => "{$title} by {$attrs['artist']}.",
        ]));

        $podcastShow = Album::create([
            'artist_id' => $artists['The Word Today']->id,
            'genre_id' => null,
            'title' => 'The Word Today',
            'slug' => 'the-word-today',
            'type' => 'podcast_show',
            'release_year' => 2026,
            'description' => 'Daily devotional readings and Bible study.',
        ]);

        // [title, artist, type, album|null, genre|null, frequencyHz, seconds]
        $tracks = [
            ['Excess Love', 'Mercy Chinwo', 'music', 'Excess Love', 'Gospel', 392.00, 42],
            ['Still Doing', 'Nathaniel Bassey', 'music', 'Hallelujah Sessions', 'Worship', 440.00, 38],
            ['Way Maker', 'Sinach', 'music', 'Way Maker Live', 'Worship', 349.23, 45],
            ['Too Faithful', 'Frank Edwards', 'music', null, 'Gospel', 523.25, 36],
            ['Trust Again', 'Judikay', 'music', null, 'Gospel', 293.66, 40],
            ['Bigger Than My Imagination', 'Mercy Chinwo', 'music', 'Faith Unshaken', 'Gospel', 415.30, 44],
            ['Akaehyi', 'Frank Edwards', 'music', null, 'Afrobeat', 466.16, 39],
            ['I Know Who I Am', 'Sinach', 'music', 'Way Maker Live', 'Worship', 349.23, 41],
            ['Onye Turn Turn', 'Judikay', 'music', null, 'Afrobeat', 261.63, 37],
            ['Jehovah Overdo', 'Nathaniel Bassey', 'music', 'Hallelujah Sessions', 'Worship', 329.63, 43],
            ['Walking in Faith', 'Pastor Emmanuel Adeyemi', 'sermon', null, 'Inspirational', 220.00, 65],
            ['The Power of Gratitude', 'Pastor Emmanuel Adeyemi', 'sermon', null, 'Inspirational', 233.08, 58],
            ['Renewing Your Mind', 'Pastor Emmanuel Adeyemi', 'sermon', null, 'Inspirational', 246.94, 62],
            ['Day 1: New Beginnings', 'The Word Today', 'podcast', 'The Word Today', null, 196.00, 50],
            ['Day 2: Faith Over Fear', 'The Word Today', 'podcast', 'The Word Today', null, 207.65, 52],
            ['Day 3: Grace Abounds', 'The Word Today', 'podcast', 'The Word Today', null, 174.61, 48],
        ];

        $createdTracks = collect($tracks)->map(function (array $t) use ($artists, $albums, $genres, $podcastShow) {
            [$title, $artistName, $type, $albumTitle, $genreName, $frequency, $seconds] = $t;

            $slug = Str::slug($title).'-'.Str::random(6);
            $relativePath = "audio/{$slug}.wav";

            // Generate the placeholder WAV on real local disk (WavGenerator
            // needs an actual filesystem path), then hand it to the 'audio'
            // disk — which may be local or S3-backed depending on environment.
            $localPath = Storage::disk('local')->path("seed-tmp/{$slug}.wav");
            Storage::disk('local')->makeDirectory('seed-tmp');
            WavGenerator::make($localPath, $seconds, $frequency);
            Storage::disk('audio')->putFileAs('audio', new File($localPath), "{$slug}.wav");
            Storage::disk('local')->delete("seed-tmp/{$slug}.wav");

            $album = $albumTitle === 'The Word Today' ? $podcastShow : ($albumTitle ? $albums[$albumTitle] : null);

            return Track::create([
                'artist_id' => $artists[$artistName]->id,
                'album_id' => $album?->id,
                'genre_id' => $genreName ? $genres[$genreName]->id : null,
                'title' => $title,
                'slug' => $slug,
                'type' => $type,
                'audio_path' => $relativePath,
                'duration_seconds' => $seconds,
                'is_explicit' => false,
                'plays_count' => random_int(50, 8000),
                'release_date' => now()->subDays(random_int(1, 400)),
            ]);
        })->keyBy('title');

        $playlists = [
            'Top Worship' => ['Way Maker', 'I Know Who I Am', 'Jehovah Overdo', 'Still Doing'],
            'Morning Worship' => ['Excess Love', 'Bigger Than My Imagination', 'Trust Again'],
            'Prayer & Meditation' => ['Walking in Faith', 'The Power of Gratitude', 'Renewing Your Mind'],
        ];

        foreach ($playlists as $title => $trackTitles) {
            $playlist = Playlist::create([
                'user_id' => 1,
                'title' => $title,
                'description' => "Curated {$title} playlist.",
                'is_public' => true,
                'is_curated' => true,
            ]);

            foreach (array_values($trackTitles) as $position => $trackTitle) {
                $playlist->tracks()->attach($createdTracks[$trackTitle]->id, ['position' => $position]);
            }
        }
    }
}
