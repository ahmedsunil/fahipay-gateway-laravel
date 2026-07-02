<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fahipay_payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('merchant_id')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending')->index();
            $table->string('method')->nullable();
            $table->string('approval_code')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fahipay_payments');
    }
};
