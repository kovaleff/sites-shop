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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('avatar', 2048)->nullable()->after('password');
            $table->unsignedBigInteger('vk_id')->nullable()->unique()->after('avatar');
            $table->unsignedBigInteger('ya_id')->nullable()->unique()->after('vk_id');
            $table->timestamp('vk_last_login_at')->nullable()->after('ya_id');
            $table->timestamp('ya_last_login_at')->nullable()->after('vk_last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['vk_id']);
            $table->dropUnique(['ya_id']);
            $table->dropColumn([
                'avatar',
                'vk_id',
                'ya_id',
                'vk_last_login_at',
                'ya_last_login_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
