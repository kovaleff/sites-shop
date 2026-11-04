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
        Schema::create('subscription_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('base_price', 10, 2)->unsigned()->default(0);
            $table->json('periods');
            $table->json('discounts');
            $table->string('target_status', 16)->default('active');
            $table->unsignedSmallInteger('qr_ttl_minutes')->default(15);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_settings');
    }
};
