<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_reason')->nullable()->after('status');
            $table->boolean('is_verified')->default(false)->after('status_reason');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->timestamp('last_active_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status_reason', 'is_verified', 'verified_at', 'last_active_at']);
        });
    }
};
