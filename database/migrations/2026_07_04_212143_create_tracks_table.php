<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['music', 'podcast', 'sermon'])->default('music');
            $table->string('audio_path');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('cover_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_explicit')->default(false);
            $table->unsignedBigInteger('plays_count')->default(0);
            $table->date('release_date')->nullable();
            $table->timestamps();

            $table->index(['type', 'release_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
