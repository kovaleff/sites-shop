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
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('site_domain', 253)->nullable()->after('site_id');
            $table->decimal('base_price', 10, 2)->nullable()->after('amount');
            $table->decimal('base_amount', 10, 2)->nullable()->after('base_price');
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('base_amount');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent');
            $table->string('target_status', 16)->nullable()->after('period_months');
            $table->string('receipt_email', 255)->nullable()->after('target_status');
            $table->json('receipt_payload')->nullable()->after('receipt_email');
            $table->timestamp('qr_expires_at')->nullable()->after('paid_at');
            $table->string('provider', 32)->nullable()->after('external_id');
            $table->string('payment_method', 32)->nullable()->after('provider');
            $table->json('provider_payload')->nullable()->after('payment_method');
            $table->string('failure_reason', 255)->nullable()->after('provider_payload');
            $table->string('review_reason', 255)->nullable()->after('failure_reason');
            $table->boolean('is_test')->default(false)->after('review_reason');

            $table->index(['site_id', 'status'], 'subscription_payments_site_status_index');
            $table->index(['status', 'qr_expires_at'], 'subscription_payments_status_qr_index');
            $table->index('site_domain', 'subscription_payments_site_domain_index');
            $table->index('created_at', 'subscription_payments_created_at_index');
            $table->index('paid_at', 'subscription_payments_paid_at_index');
            $table->index('payment_method', 'subscription_payments_method_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex('subscription_payments_site_status_index');
            $table->dropIndex('subscription_payments_status_qr_index');
            $table->dropIndex('subscription_payments_site_domain_index');
            $table->dropIndex('subscription_payments_created_at_index');
            $table->dropIndex('subscription_payments_paid_at_index');
            $table->dropIndex('subscription_payments_method_index');

            $table->dropColumn([
                'site_domain',
                'base_price',
                'base_amount',
                'discount_percent',
                'discount_amount',
                'target_status',
                'receipt_email',
                'receipt_payload',
                'qr_expires_at',
                'provider',
                'payment_method',
                'provider_payload',
                'failure_reason',
                'review_reason',
                'is_test',
            ]);
        });
    }
};
