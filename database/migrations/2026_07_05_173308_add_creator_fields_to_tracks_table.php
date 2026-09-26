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
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('language')->default('English')->after('type');
            $table->enum('transcoding_status', ['pending', 'processing', 'ready', 'failed'])
                ->default('ready')->after('audio_path');
            $table->string('hls_playlist_path')->nullable()->after('transcoding_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['language', 'transcoding_status', 'hls_playlist_path']);
        });
    }
};
