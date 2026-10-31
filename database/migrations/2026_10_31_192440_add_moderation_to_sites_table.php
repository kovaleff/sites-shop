<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('moderation_status', 16)->default('pending')->after('status')->index();
            $table->string('moderation_note', 255)->nullable()->after('moderation_status');
            $table->timestamp('moderated_at')->nullable()->after('moderation_note');
            $table->timestamp('verification_checked_at')->nullable()->after('moderated_at');
            $table->string('verification_error', 255)->nullable()->after('verification_checked_at');
        });

        // Объявления, существовавшие до введения модерации, уже опубликованы.
        DB::table('sites')->update(['moderation_status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['moderation_status']);
            $table->dropColumn([
                'moderation_status',
                'moderation_note',
                'moderated_at',
                'verification_checked_at',
                'verification_error',
            ]);
        });
    }
};
