<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_histories', function (Blueprint $table) {
            // Whether playback reached the track's natural end, rather than
            // being skipped away from early — the recommendation engine
            // weights completed plays more heavily than started-only ones.
            $table->boolean('completed')->default(false)->after('played_at');
        });
    }

    public function down(): void
    {
        Schema::table('play_histories', function (Blueprint $table) {
            $table->dropColumn('completed');
        });
    }
};
