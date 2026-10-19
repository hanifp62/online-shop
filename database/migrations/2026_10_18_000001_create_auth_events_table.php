<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('event', 64)->index();
            $table->string('outcome', 16)->index();
            $table->string('ip', 45)->nullable()->index();
            $table->json('context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
