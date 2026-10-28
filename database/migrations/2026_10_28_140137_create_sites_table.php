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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('domain', 253);
            $table->string('status', 16)->default('active');
            $table->string('public_domain', 253)
                ->nullable()
                ->storedAs("case when status in ('active', 'inactive') then domain else null end");
            $table->text('description');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('monthly_revenue', 12, 2)->default(0);
            $table->boolean('has_trademark')->default(false);
            $table->string('verification_filename', 64)->unique();
            $table->boolean('verified')->default(false);
            $table->date('paid_until')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->unique('public_domain', 'sites_public_domain_unique');
            $table->index(['status', 'paid_until'], 'sites_visibility_index');
            $table->index('price');
            $table->index('monthly_revenue');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
