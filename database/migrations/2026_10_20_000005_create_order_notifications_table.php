<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('channel', 20)->default('mail');
            $table->timestamp('created_at')->useCurrent();

            // One notification per order per status: replays and retried webhooks cannot double-send.
            $table->unique(['order_id', 'status', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};
