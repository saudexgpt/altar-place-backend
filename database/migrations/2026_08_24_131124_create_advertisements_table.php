<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['banner', 'interstitial', 'audio', 'sponsored_playlist', 'sponsored_artist']);
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');

            $table->string('headline');
            $table->text('body')->nullable();
            $table->string('image_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();

            // Only populated for sponsored_playlist / sponsored_artist campaigns.
            $table->nullableMorphs('sponsorable');

            // Optional targeting rules: { countries: [], states: [], cities: [],
            // device_types: [], genre_ids: [], age_min: int|null, age_max: int|null }.
            // An empty/missing key means "no restriction" for that dimension.
            $table->json('targeting')->nullable();

            $table->unsignedInteger('daily_impression_cap')->nullable();
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
