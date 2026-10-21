<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id', 64);
            $table->string('provider_order_id', 64);
            $table->string('event_status', 32);
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('outcome', 40);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_transaction_id', 'event_status'],
                'payments_provider_tx_event_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
