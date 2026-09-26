<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('transcoding_status');
            $table->string('rejection_reason')->nullable()->after('status');
            $table->foreignId('moderated_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropColumn(['status', 'rejection_reason', 'moderated_by', 'moderated_at']);
        });
    }
};
