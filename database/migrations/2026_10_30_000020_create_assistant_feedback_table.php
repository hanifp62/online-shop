<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FR-402.7: answer-quality metric for the guided assistant (research §12.3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 30);
            $table->boolean('helpful');
            $table->string('locale', 5)->nullable();
            $table->timestamps();
            $table->index(['topic', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_feedback');
    }
};
