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
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('sold_at');

            $table->index(['user_id', 'hidden_at'], 'sites_user_hidden_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex('sites_user_hidden_index');
            $table->dropColumn('hidden_at');
        });
    }
};
