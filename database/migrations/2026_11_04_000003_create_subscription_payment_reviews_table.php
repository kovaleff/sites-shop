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
        Schema::create('subscription_payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('comment');
            $table->timestamps();

            $table->index(
                ['subscription_payment_id', 'created_at'],
                'subscription_payment_reviews_payment_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_reviews');
    }
};
